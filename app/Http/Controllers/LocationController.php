<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LocationController extends Controller
{
    public function addresses(Request $request)
    {
        /* ---------- tham số ---------- */
        $country = $request->input('country', 'Brazil');      // cấp 1
        $state = $request->input('state');                  // cấp 2 (tỉnh/bang) – tuỳ chọn
        $city = $request->input('city');                   // cấp 3 (đô thị) – tuỳ chọn
        $limit = max(1, min((int) $request->input('limit', 10), 10_000));
        $batch = 2_000;   // phần tử/raw Overpass mỗi lần
        $tries = 3;       // tối đa 3 lần gọi REST

        /* ---------- cache key ---------- */
        $slugParts = array_filter([$country, $state, $city]);
        $cacheKey = 'issued_place_ids_' . Str::slug(implode('_', $slugParts));
        $issuedIds = Cache::get($cacheKey, []);

        /* ---------- thu thập ---------- */
        $fresh = collect();
        $attempt = 0;

        while ($fresh->count() < $limit && $attempt < $tries) {
            $attempt++;

            /* ===== 1. Ghép Overpass QL động ===== */
            $areaQuery = $this->buildAreaQuery($country, $state, $city);

            $query = <<<QL
            [out:json][timeout:300];
            $areaQuery
            nwr
            ["addr:housenumber"]
            ["building"~"^(house|residential|apartments|detached|semidetached|terrace)$"]
            (area.t);
            out center $batch;
            QL;

            $resp = Http::timeout(60)
                ->get('https://overpass-api.de/api/interpreter', ['data' => $query])
                ->json('elements', []);

            $candidates = collect($resp)
                ->shuffle()
                ->reject(fn($e) => in_array($e['id'], $issuedIds))
                ->unique('id');

            $need = $limit - $fresh->count();
            $fresh = $fresh->merge($candidates->take($need));
        }

        /* ---------- chưa đủ? ---------- */
        if ($fresh->count() < $limit) {
            return response()->json([
                'message' => "Không đủ địa chỉ mới để trả về {$limit} kết quả (còn {$fresh->count()})."
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /* ---------- ghi cache ---------- */
        Cache::put($cacheKey, array_merge($issuedIds, $fresh->pluck('id')->all()));

        /* ---------- chuẩn hoá kết quả ---------- */
        $payload = $fresh->map(fn($e) => [
            'address' => $this->formatAddress($e['tags'] ?? []),
            'lat' => $e['lat'] ?? $e['center']['lat'] ?? null,
            'lng' => $e['lon'] ?? $e['center']['lon'] ?? null,
        ])->values();

        return response()->json($payload);
    }

    /* ===== helper: dựng đoạn “area … ->.t;” tuỳ cấp ===== */
    private function buildAreaQuery(string $country, ?string $state, ?string $city): string
    {
        // 1. country (admin_level=2)
        $q = 'area["name"="' . addslashes($country) . '"]["boundary"="administrative"]["admin_level"="2"]->.c;';
        // 2. state (admin_level=4) – nếu có
        if ($state) {
            $q .= 'area["name"="' . addslashes($state) . '"]["boundary"="administrative"](area.c)->.s;';
        }
        // 3. city (admin_level>=6/8) – nếu có
        if ($city) {
            $parent = $state ? 's' : 'c';
            $q .= 'area["name"="' . addslashes($city) . '"]["boundary"="administrative"](area.' . $parent . ')->.t;';
        } else {
            $q .= ($state ? '.s->.t;' : '.c->.t;');   // gán đích là .t
        }
        return $q;
    }

    /* ===== helper: định dạng địa chỉ ===== */
    private function formatAddress(array $tags): string
    {
        $street = $tags['addr:street'] ?? null;
        $number = $tags['addr:housenumber'] ?? null;
        $suburb = $tags['addr:suburb'] ?? $tags['addr:neighbourhood'] ?? null;
        $city = $tags['addr:city'] ?? null;
        $state = $tags['addr:state'] ?? null;
        $postcode = $tags['addr:postcode'] ?? null;
        $country = $tags['addr:country'] ?? null;

        $address = collect([
            $street && $number ? "$street, $number" : null,
            $suburb ? "– $suburb" : null,
            $city,
            $state ? "– $state" : null,
            $postcode,
            $country,
        ])
            ->filter()
            ->implode(', ');

        return str_replace(', –', ' –', $address);
    }
}
