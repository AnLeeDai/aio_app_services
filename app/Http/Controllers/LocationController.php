<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;

class LocationController extends Controller
{
    /**
     * Danh sách tất cả bang của Brazil (26 + Distrito Federal)
     */
    private array $brStates = [
        // Vùng Bắc
        'Acre',
        'Amapá',
        'Amazonas',
        'Pará',
        'Rondônia',
        'Roraima',
        'Tocantins',
        // Vùng Đông Bắc
        'Alagoas',
        'Bahia',
        'Ceará',
        'Maranhão',
        'Paraíba',
        'Pernambuco',
        'Piauí',
        'Rio Grande do Norte',
        'Sergipe',
        // Vùng Trung Tây
        'Goiás',
        'Mato Grosso',
        'Mato Grosso do Sul',
        'Distrito Federal',
        // Vùng Đông Nam
        'Espírito Santo',
        'Minas Gerais',
        'Rio de Janeiro',
        'São Paulo',
        // Vùng Nam
        'Paraná',
        'Rio Grande do Sul',
        'Santa Catarina',
    ];

    public function generateAddresses(Request $request)
    {
        set_time_limit(300);

        /* -------------------------------------------------------------
         | 1‒ Validate input
         |   Chỉ còn country_code / country tùy chọn; state tùy chọn
         |-------------------------------------------------------------*/
        Validator::make($request->all(), [
            'country_code' => 'nullable|string|size:2',
            'country' => 'nullable|string|max:100',
            'state' => 'sometimes|string|max:100',
            'limit' => 'required|integer|min:1|max:100',
            'trans_ascii' => 'sometimes|boolean',
        ])->validate();

        /* -------------------------------------------------------------
         | 2‒ Fix country = Brazil (BR) & đọc tham số
         |-------------------------------------------------------------*/
        $countryCode = 'BR';
        $countryName = 'Brazil';
        $state = $request->input('state');
        $city = $request->input('city');        // vẫn cho phép lọc city
        $limit = (int) $request->input('limit');
        $transAscii = $request->boolean('trans_ascii', false);

        // Tự chọn bang ngẫu nhiên nếu không truyền
        if (empty($state)) {
            $state = $this->brStates[array_rand($this->brStates)];
        }

        /* -------------------------------------------------------------
         | 3‒ Build Overpass area query
         |-------------------------------------------------------------*/
        $areaQuery = $this->buildAreaQuery($countryCode, null, $state, $city);

        /* -------------------------------------------------------------
         | 4‒ Cache key + TTL
         |-------------------------------------------------------------*/
        $cacheKey = 'address:' . md5(json_encode([
            'country_code' => $countryCode,
            'state' => $state,
            'city' => $city,
            'limit' => $limit,
            'ascii' => $transAscii,
        ]));
        $ttl = (int) config('cache.ttl', env('ADDRESS_CACHE_TTL', 3600));

        /* -------------------------------------------------------------
         | 5‒ Lấy hoặc tạo dữ liệu
         |-------------------------------------------------------------*/
        $data = Cache::remember($cacheKey, $ttl, function () use ($areaQuery, $limit, $transAscii, $countryName) {
            $batch = 3000;
            $tries = 5;
            $fresh = collect();
            $attempt = 0;

            while ($fresh->count() < $limit && $attempt < $tries) {
                $attempt++;

                $query = "[out:json][timeout:300];\n"
                    . "{$areaQuery}\n"
                    . "nwr['addr:housenumber']['building'~'^(house|residential)$'](area.t);"
                    . " out center {$batch};";

                $resp = Http::timeout(120)
                    ->get('https://overpass-api.de/api/interpreter', ['data' => $query])
                    ->json('elements', []);

                $cands = collect($resp)
                    ->filter(fn($e) => $this->hasBasicAddress($e['tags'] ?? [])
                        && $this->isResidential($e['tags'] ?? []))
                    ->shuffle();

                $fresh = $fresh->merge($cands->take($limit - $fresh->count()));
            }

            if ($fresh->isEmpty()) {
                return null; // báo lỗi bên ngoài
            }

            return $fresh->map(function ($e) use ($transAscii, $countryName) {
                $addr = $this->formatAddress($e['tags'], $countryName);
                return ['address' => $transAscii ? Str::ascii($addr) : $addr];
            })->values();
        });

        if ($data === null) {
            return $this->error(
                "Không đủ địa chỉ nhà dân để trả về {$limit} kết quả.",
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        /* -------------------------------------------------------------
         | 6‒ Trả về
         |-------------------------------------------------------------*/
        return response()->json([
            'status' => 'success',
            'message' => "Tạo thành công {$data->count()} địa chỉ nhà dân (bang {$state})",
            'trans_ascii' => $transAscii,
            'state' => $state,
            'data' => $data,
        ]);
    }

    /* =============================================================
     |  Các hàm hỗ trợ
     |=============================================================*/
    private function buildAreaQuery(?string $code, ?string $name, ?string $state, ?string $city): string
    {
        // Luôn có country_code BR
        $q = 'area["boundary"="administrative"]["admin_level"="2"]["ISO3166-1:alpha2"="BR"]->.c;';

        // Thêm bang
        $q .= 'area["name"="' . addslashes($state)
            . '"]["boundary"="administrative"](area.c)->.s;';

        if ($city) {
            $q .= 'area["name"="' . addslashes($city)
                . '"]["boundary"="administrative"](area.s)->.t;';
        } else {
            $q .= '.s->.t;';
        }

        return $q;
    }

    private function hasBasicAddress(array $tags): bool
    {
        return isset($tags['addr:street'], $tags['addr:housenumber'], $tags['addr:postcode']);
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

        $country = $defaultCountryName;

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
