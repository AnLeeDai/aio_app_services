<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LocationController extends Controller
{
    public function generateAddresses(Request $request)
    {
        /* Cho phép PHP chạy tới 5 phút cho toàn bộ request */
        set_time_limit(300);

        /* ---------- 1. Validate ---------- */
        $validator = Validator::make($request->all(), [
            'country' => 'required|string|max:100',
            'state' => 'sometimes|string|max:100',
            'city' => 'sometimes|string|max:100',
            'limit' => 'required|integer|min:1|max:10000',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }

        /* ---------- 2. Đọc tham số ---------- */
        $country = $request->input('country', 'Brazil');
        $state = $request->input('state');
        $city = $request->input('city');
        $limit = (int) $request->input('limit');

        $batch = 3_000;   // phần tử/raw Overpass mỗi lần
        $tries = 5;       // số lần gọi Overpass tối đa

        /* ---------- 3. Thu thập (không lọc trùng) ---------- */
        $fresh = collect();
        $attempt = 0;

        while ($fresh->count() < $limit && $attempt < $tries) {
            $attempt++;

            $areaQuery = $this->buildAreaQuery($country, $state, $city);
            $query = <<<QL
            [out:json][timeout:300];
            $areaQuery
            nwr["addr:housenumber"](area.t);
            out center $batch;
            QL;

            $resp = Http::timeout(120)
                ->get('https://overpass-api.de/api/interpreter', ['data' => $query])
                ->json('elements', []);

            $candidates = collect($resp)
                ->filter(fn($e) => $this->hasFullAddress($e['tags'] ?? []))
                ->shuffle();                               // không reject/unique

            $need = $limit - $fresh->count();
            $fresh = $fresh->merge($candidates->take($need));
        }

        /* ---------- 4. Đủ hay chưa? ---------- */
        if ($fresh->count() < $limit) {
            return $this->error(
                "Không đủ địa chỉ mới để trả về {$limit} kết quả (chỉ có {$fresh->count()}).",
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        /* ---------- 5. Chuẩn hoá & trả về ---------- */
        $data = $fresh->map(fn($e) => [
            'address' => $this->formatAddress($e['tags']),
        ])->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo thành công ' . $data->count() . ' địa chỉ',
            'data' => $data,
        ]);
    }

    /* ===================== HELPERS ===================== */

    private function buildAreaQuery(string $country, ?string $state, ?string $city): string
    {
        $q = 'area["name"="' . addslashes($country) . '"]["boundary"="administrative"]["admin_level"="2"]->.c;';
        if ($state) {
            $q .= 'area["name"="' . addslashes($state) . '"]["boundary"="administrative"](area.c)->.s;';
        }
        if ($city) {
            $parent = $state ? 's' : 'c';
            $q .= 'area["name"="' . addslashes($city) . '"]["boundary"="administrative"](area.' . $parent . ')->.t;';
        } else {
            $q .= ($state ? '.s->.t;' : '.c->.t;');
        }
        return $q;
    }

    private function hasFullAddress(array $tags): bool
    {
        return isset(
            $tags['addr:street'],
            $tags['addr:housenumber'],
            $tags['addr:city'],
            $tags['addr:state'],
            $tags['addr:postcode']
        );
    }

    private function formatAddress(array $tags): string
    {
        $street = $tags['addr:street'];
        $number = $tags['addr:housenumber'];
        $suburb = $tags['addr:suburb'] ?? $tags['addr:neighbourhood'] ?? null;
        $city = $tags['addr:city'];
        $state = $this->normalizeState($tags['addr:state']);
        $postcode = $tags['addr:postcode'];

        /* ---- country: đổi mã → tên đầy đủ ---- */
        $country = $tags['addr:country'] ?? 'Brazil';

        // Nếu chuỗi <= 3 ký tự ⇒ khả năng cao là mã quốc gia
        if (mb_strlen($country) <= 3) {
            $country = match (strtoupper($country)) {
                'BR', 'BRA' => 'Brazil',
                'US', 'USA' => 'United States',
                default => $country,
            };
        }

        /* ---- ghép địa chỉ ---- */
        $line1 = "{$street}, {$number}";
        if ($suburb)
            $line1 .= " - {$suburb}";

        $line2 = "{$city} - {$state}";

        return "{$line1}, {$line2}, {$postcode}, {$country}";
    }

    private function normalizeState(string $state): string
    {
        if (mb_strlen($state) <= 3) {
            return mb_strtoupper($state);
        }

        $abbr = '';
        foreach (preg_split('/\s+/', $state) as $w) {
            $abbr .= mb_strtoupper(mb_substr($w, 0, 1));
        }
        return $abbr;
    }

    private function error(string $msg, int $code = 422)
    {
        return response()->json([
            'status' => 'error',
            'message' => $msg,
        ], $code);
    }
}
