<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class NameGeneratorController extends Controller
{
    private const LOCALE_MAP = [
        'US' => ['locale' => 'en_US', 'order' => 'F M L'],
        'UK' => ['locale' => 'en_GB', 'order' => 'F M L'],
        'VN' => ['locale' => 'vi_VN', 'order' => 'L M F'],
        'DE' => ['locale' => 'de_DE', 'order' => 'F M L'],
        'FR' => ['locale' => 'fr_FR', 'order' => 'F M L'],
        'JP' => ['locale' => 'ja_JP', 'order' => 'L F'],
        'ES' => ['locale' => 'es_ES', 'order' => 'F M L'],
        'IT' => ['locale' => 'it_IT', 'order' => 'F M L'],
        'RU' => ['locale' => 'ru_RU', 'order' => 'F M L'],
        'CN' => ['locale' => 'zh_CN', 'order' => 'L F'],
        'KR' => ['locale' => 'ko_KR', 'order' => 'L F'],
        'BR' => ['locale' => 'pt_BR', 'order' => 'F M L'],
    ];

    public function generateName(Request $request)
    {
        // 1️⃣ Validate
        $validator = Validator::make($request->all(), [
            'name_number' => 'required|integer|min:1|max:100',
            'country' => 'string|size:2',
            'trans_ascii' => 'boolean',
            'gender' => 'nullable|in:male,female,random',
            'name_format' => 'nullable|in:first_last,first_middle_last',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }

        // 2️⃣ Extract options
        $total = (int) $request->input('name_number', 1);
        $countryCode = strtoupper($request->input('country', 'US'));
        $transAscii = $request->boolean('trans_ascii', false);
        $genderOpt = $request->input('gender', 'random');
        $nameFormat = $request->input('name_format', 'first_middle_last');

        // 3️⃣ Resolve locale & pattern
        [$locale, $order] = $this->resolveLocaleAndOrder($countryCode);
        if ($nameFormat === 'first_last') {
            $order = str_replace('M', '', $order); // bỏ đệm
            $order = trim(preg_replace('/\s+/', ' ', $order));
        }

        // 4️⃣ Faker instance (khởi tạo 1 lần)
        $faker = Faker::create($locale);

        // 5️⃣ Generate
        $names = collect(range(1, $total))->map(function () use ($faker, $genderOpt, $order, $transAscii) {
            // Giới tính cụ thể hoặc random
            $gender = $genderOpt === 'random' ? $faker->randomElement(['male', 'female']) : $genderOpt;

            $first = $gender === 'male' ? $faker->firstNameMale : $faker->firstNameFemale;
            $middle = '';
            if (str_contains($order, 'M')) {
                do {
                    $middle = $gender === 'male' ? $faker->firstNameMale : $faker->firstNameFemale;
                } while ($middle === $first);
            }
            $last = $faker->lastName;

            // Ghép
            $tokens = ['F' => $first, 'M' => $middle, 'L' => $last];
            $full = collect(str_split(str_replace(' ', '', $order)))
                ->map(fn($t) => $tokens[$t] ?? '')
                ->filter()
                ->implode(' ');

            if ($transAscii) {
                $full = Str::ascii($full);
            }
            return $full;
        })->all();

        // 6️⃣ Output
        return response()->json([
            'status' => 'success',
            'message' => 'Tạo thành công ' . count($names) . ' tên ' . $countryCode,
            'country' => $countryCode,
            'data' => $names,
        ]);
    }

    /* --------- Helpers --------- */

    private function resolveLocaleAndOrder(string $country): array
    {
        if (!isset(self::LOCALE_MAP[$country])) {
            $country = 'US';
        }
        $info = self::LOCALE_MAP[$country];
        return [$info['locale'], $info['order']];
    }

    private function error(string $msg)
    {
        return response()->json([
            'status' => 'error',
            'message' => $msg,
        ], 422);
    }
}
