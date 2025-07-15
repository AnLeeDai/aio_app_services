<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;

class LocationController extends Controller
{
    public function generateAddresses(Request $request)
    {
        set_time_limit(300);

        // 1. Validate input
        Validator::make($request->all(), [
            'country_code' => 'required_without:country|string|size:2',
            'country' => 'required_without:country_code|string|max:100',
            'state' => 'sometimes|string|max:100',
            'city' => 'sometimes|string|max:100',
            'limit' => 'required|integer|min:1|max:100',
            'trans_ascii' => 'sometimes|boolean',
        ])->validate();

        // 2. Read parameters
        $countryCode = strtoupper($request->input('country_code', ''));
        $countryName = $request->input('country', '');
        $state = $request->input('state');
        $city = $request->input('city');
        $limit = (int) $request->input('limit');
        $transAscii = $request->boolean('trans_ascii', false);

        // 3. Resolve country name
        if ($request->filled('country_code')) {
            try {
                $resolvedCountry = Countries::getName($countryCode, 'en');
            } catch (\Throwable $e) {
                $resolvedCountry = $countryCode;
            }
        } else {
            $resolvedCountry = $countryName;
        }

        // 4. Build Overpass area query
        $areaQuery = $this->buildAreaQuery(
            $request->filled('country_code') ? $countryCode : null,
            $request->filled('country_code') ? null : $countryName,
            $state,
            $city
        );

        // 5. Fetch and filter
        $batch = 3000;
        $tries = 5;
        $fresh = collect();
        $attempt = 0;

        while ($fresh->count() < $limit && $attempt < $tries) {
            $attempt++;

            // Query only houses/residential with address tags
            $query = "[out:json][timeout:300];\n"
                . "{$areaQuery}\n"
                . "nwr['addr:housenumber']['building'~'^(house|residential)$'](area.t); out center {$batch};";

            $resp = Http::timeout(120)
                ->get('https://overpass-api.de/api/interpreter', ['data' => $query])
                ->json('elements', []);

            // Require street, housenumber, postcode, and residential building
            $cands = collect($resp)
                ->filter(fn($e) => $this->hasBasicAddress($e['tags'] ?? []) && $this->isResidential($e['tags'] ?? []))
                ->shuffle();

            $fresh = $fresh->merge(
                $cands->take($limit - $fresh->count())
            );
        }

        if ($fresh->isEmpty()) {
            return $this->error(
                "Không đủ địa chỉ nhà dân để trả về {$limit} kết quả (chỉ có {$fresh->count()}).",
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // 6. Format and return
        $data = $fresh->map(function ($e) use ($transAscii, $resolvedCountry) {
            $addr = $this->formatAddress($e['tags'], $resolvedCountry);
            if ($transAscii) {
                $addr = Str::ascii($addr);
            }
            return ['address' => $addr];
        })->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo thành công ' . $data->count() . ' địa chỉ nhà dân',
            'trans_ascii' => $transAscii,
            'data' => $data,
        ]);
    }

    private function buildAreaQuery(?string $code, ?string $name, ?string $state, ?string $city): string
    {
        if ($code) {
            $q = 'area["boundary"="administrative"]["admin_level"="2"]["ISO3166-1:alpha2"="'
                . addslashes($code) . '"]->.c;';
        } else {
            $q = 'area["name"="' . addslashes($name)
                . '"]["boundary"="administrative"]["admin_level"="2"]->.c;';
        }
        if ($state) {
            $q .= 'area["name"="' . addslashes($state)
                . '"]["boundary"="administrative"](area.c)->.s;';
        }
        if ($city) {
            $parent = $state ? 's' : 'c';
            $q .= 'area["name"="' . addslashes($city)
                . '"]["boundary"="administrative"](area.' . $parent . ')->.t;';
        } else {
            $q .= ($state ? '.s->.t;' : '.c->.t;');
        }

        return $q;
    }

    /**
     * Phải có street, housenumber và postcode
     */
    private function hasBasicAddress(array $tags): bool
    {
        return isset(
            $tags['addr:street'],
            $tags['addr:housenumber'],
            $tags['addr:postcode']
        );
    }

    private function isResidential(array $tags): bool
    {
        return isset($tags['building']) && preg_match('/^(house|residential)$/i', $tags['building']);
    }

    private function formatAddress(array $tags, string $defaultCountryName): string
    {
        $street = $tags['addr:street'];
        $number = $tags['addr:housenumber'];
        $suburb = $tags['addr:suburb'] ?? $tags['addr:neighbourhood'] ?? null;
        $city = $tags['addr:city'] ?? $tags['addr:town'] ?? $tags['addr:place'] ?? '';
        $stateRaw = $tags['addr:state'] ?? $tags['addr:province'] ?? $tags['addr:region'] ?? '';
        $state = $stateRaw ? $this->normalizeState($stateRaw) : '';
        $postcode = $tags['addr:postcode'];

        if (!empty($tags['addr:country'])) {
            $code = strtoupper($tags['addr:country']);
            try {
                $country = Countries::getName($code, 'en');
            } catch (\Throwable $e) {
                $country = $code;
            }
        } else {
            $country = $defaultCountryName;
        }

        $line1 = "{$street}, {$number}" . ($suburb ? " - {$suburb}" : '');
        $line2Parts = array_filter([$city, $state]);
        $line2 = $line2Parts ? implode(' - ', $line2Parts) : '';
        $parts = array_filter([$line1, $line2, $postcode, $country]);

        return implode(', ', $parts);
    }

    private function normalizeState(string $state): string
    {
        if (mb_strlen($state) <= 3) {
            return mb_strtoupper($state);
        }

        return collect(preg_split('/\s+/', $state))
            ->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');
    }

    private function error(string $msg, int $code = 422)
    {
        return response()->json([
            'status' => 'error',
            'message' => $msg,
        ], $code);
    }
}
