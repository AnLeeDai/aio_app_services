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
    /**
     * Cấu hình quốc gia hỗ trợ: ISO code, tên chuẩn, alias và danh sách bang/tỉnh
     */
    private array $countryConfigs = [
        // Brazil (BR)
        'BR' => [
            'name' => 'Brazil',
            'aliases' => ['Brazil', 'Brasil', 'BR'],
            'states' => [
                'Acre', 'Amapá', 'Amazonas', 'Pará', 'Rondônia', 'Roraima', 'Tocantins',
                'Alagoas', 'Bahia', 'Ceará', 'Maranhão', 'Paraíba', 'Pernambuco', 'Piauí', 'Rio Grande do Norte', 'Sergipe',
                'Goiás', 'Mato Grosso', 'Mato Grosso do Sul', 'Distrito Federal',
                'Espírito Santo', 'Minas Gerais', 'Rio de Janeiro', 'São Paulo',
                'Paraná', 'Rio Grande do Sul', 'Santa Catarina',
            ],
        ],
        // Peru (PE)
        'PE' => [
            'name' => 'Peru',
            'aliases' => ['Peru', 'Perú', 'PE'],
            'states' => [
                'Amazonas', 'Áncash', 'Apurímac', 'Arequipa', 'Ayacucho', 'Cajamarca', 'Callao', 'Cusco',
                'Huancavelica', 'Huánuco', 'Ica', 'Junín', 'La Libertad', 'Lambayeque', 'Lima', 'Loreto',
                'Madre de Dios', 'Moquegua', 'Pasco', 'Piura', 'Puno', 'San Martín', 'Tacna', 'Tumbes', 'Ucayali',
            ],
        ],
        // Malaysia (MY)
        'MY' => [
            'name' => 'Malaysia',
            'aliases' => ['Malaysia', 'MY'],
            'states' => [
                'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Pulau Pinang',
                'Perak', 'Perlis', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu', 'Kuala Lumpur', 'Putrajaya', 'Labuan',
            ],
        ],
        // Colombia (CO)
        'CO' => [
            'name' => 'Colombia',
            'aliases' => ['Colombia', 'CO'],
            'states' => [
                'Amazonas', 'Antioquia', 'Arauca', 'Atlántico', 'Bolívar', 'Boyacá', 'Caldas', 'Caquetá', 'Casanare',
                'Cauca', 'Cesar', 'Chocó', 'Córdoba', 'Cundinamarca', 'Bogotá D.C.', 'Guainía', 'Guaviare', 'Huila',
                'La Guajira', 'Magdalena', 'Meta', 'Nariño', 'Norte de Santander', 'Putumayo', 'Quindío', 'Risaralda',
                'San Andrés y Providencia', 'Santander', 'Sucre', 'Tolima', 'Valle del Cauca', 'Vaupés', 'Vichada',
            ],
        ],
        // Jamaica (JM)
        'JM' => [
            'name' => 'Jamaica',
            'aliases' => ['Jamaica', 'JM'],
            'states' => [
                'Kingston', 'St. Andrew', 'St. Thomas', 'Portland', 'St. Mary', 'St. Ann', 'Trelawny', 'St. James',
                'Hanover', 'Westmoreland', 'St. Elizabeth', 'Manchester', 'Clarendon', 'St. Catherine',
            ],
        ],
        // Chile (CL)
        'CL' => [
            'name' => 'Chile',
            'aliases' => ['Chile', 'CL'],
            'states' => [
                'Arica y Parinacota', 'Tarapacá', 'Antofagasta', 'Atacama', 'Coquimbo', 'Valparaíso',
                'Región Metropolitana de Santiago', "Libertador General Bernardo O'Higgins", 'Maule', 'Ñuble', 'Biobío',
                'La Araucanía', 'Los Ríos', 'Los Lagos', 'Aysén', 'Magallanes y de la Antártica Chilena',
            ],
        ],
        // Paraguay (PY)
        'PY' => [
            'name' => 'Paraguay',
            'aliases' => ['Paraguay', 'PY'],
            'states' => [
                'Asunción', 'Alto Paraguay', 'Alto Paraná', 'Amambay', 'Boquerón', 'Caaguazú', 'Caazapá', 'Canindeyú', 'Central',
                'Concepción', 'Cordillera', 'Guairá', 'Itapúa', 'Misiones', 'Ñeembucú', 'Paraguarí', 'Presidente Hayes', 'San Pedro',
            ],
        ],
    ];

    public function generateAddresses(Request $request)
    {
        set_time_limit(300);

        /* -------------------------------------------------------------
         | 1‒ Validate input
         |   Chỉ còn country_code / country tùy chọn; state tùy chọn
         |-------------------------------------------------------------*/
        Validator::make($request->all(), [
            'country' => 'required|string|max:100',
            'state' => 'sometimes|string|max:100',
            'city' => 'sometimes|string|max:100',
            'limit' => 'required|integer|min:1|max:100',
            'trans_ascii' => 'sometimes|boolean',
        ])->validate();

        /* -------------------------------------------------------------
         | 2‒ Đọc tham số quốc gia, ánh xạ ISO code, chọn bang nếu cần
         |-------------------------------------------------------------*/
        $countryInput = trim($request->input('country'));
        [$countryCode, $countryName] = $this->resolveCountry($countryInput);
        if (!$countryCode) {
            return $this->error('Quốc gia không được hỗ trợ. Hỗ trợ: Brazil, Paraguay, Peru, Malaysia, Colombia, Jamaica, Chile.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $state = $request->input('state');
        $city = $request->input('city');        // vẫn cho phép lọc city
        $limit = (int) $request->input('limit');
        $transAscii = $request->boolean('trans_ascii', false);

        // Tự chọn bang/tỉnh ngẫu nhiên nếu không truyền
        if (empty($state) && !empty($this->countryConfigs[$countryCode]['states'])) {
            $list = $this->countryConfigs[$countryCode]['states'];
            $state = $list[array_rand($list)];
        }

        /* -------------------------------------------------------------
         | 3‒ Build Overpass area query
         |-------------------------------------------------------------*/
    $areaQuery = $this->buildAreaQuery($countryCode, $countryName, $state, $city);

        /* -------------------------------------------------------------
         | 4‒ Lấy hoặc tạo dữ liệu
         |-------------------------------------------------------------*/
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
            return $this->error(
                "Không đủ địa chỉ nhà dân để trả về {$limit} kết quả.",
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $data = $fresh->map(function ($e) use ($transAscii, $countryName) {
            $addr = $this->formatAddress($e['tags'], $countryName);
            return ['address' => $transAscii ? Str::ascii($addr) : $addr];
        })->values();

        /* -------------------------------------------------------------
         | 5‒ Trả về
         |-------------------------------------------------------------*/
        return response()->json([
            'status' => 'success',
            'message' => "Tạo thành công {$data->count()} địa chỉ nhà dân",
            'trans_ascii' => $transAscii,
            'country' => $countryName,
            'country_code' => $countryCode,
            'state' => $state,
            'data' => $data,
        ]);
    }

    /* =============================================================
     |  Các hàm hỗ trợ
     |=============================================================*/
    private function buildAreaQuery(?string $code, ?string $name, ?string $state, ?string $city): string
    {
        // Country area theo ISO hoặc theo tên
        if ($code) {
            $q = 'area["boundary"="administrative"]["admin_level"="2"]["ISO3166-1:alpha2"="' . addslashes($code) . '"]->.c;';
        } else {
            $q = 'area["boundary"="administrative"]["admin_level"="2"]["name"="' . addslashes((string) $name) . '"]->.c;';
        }

        // Tên bang/tỉnh nếu có; không ép admin_level cụ thể vì mỗi nước khác nhau
        if ($state) {
            $q .= 'area["name"="' . addslashes($state) . '"]["boundary"="administrative"](area.c)->.s;';
        } else {
            // Không truyền state: dùng cả country area làm scope s
            $q .= '.c->.s;';
        }

        if ($city) {
            $q .= 'area["name"="' . addslashes($city) . '"]["boundary"="administrative"](area.s)->.t;';
        } else {
            $q .= '.s->.t;';
        }

        return $q;
    }

    private function hasBasicAddress(array $tags): bool
    {
        // postcode có thể thiếu ở nhiều nước
        return isset($tags['addr:street'], $tags['addr:housenumber']);
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
        $postcode = $tags['addr:postcode'] ?? null;

        $country = $tags['addr:country'] ?? $defaultCountryName;

        $line1 = "{$street}, {$number}" . ($suburb ? " - {$suburb}" : '');
        $line2Parts = array_filter([$city, $state]);
        $line2 = $line2Parts ? implode(' - ', $line2Parts) : '';
        $parts = array_filter([$line1, $line2, $postcode, $country]);

        return implode(', ', $parts);
    }

    private function resolveCountry(string $input): array
    {
        $needle = mb_strtolower(trim($input));

        foreach ($this->countryConfigs as $code => $conf) {
            if (mb_strtolower($code) === $needle) {
                return [$code, $conf['name']];
            }
            foreach ($conf['aliases'] as $alias) {
                if (mb_strtolower($alias) === $needle) {
                    return [$code, $conf['name']];
                }
            }
        }

        return [null, null];
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

