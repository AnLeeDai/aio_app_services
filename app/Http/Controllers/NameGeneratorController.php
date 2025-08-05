<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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

    /** Fallback dataset gọn nhẹ cho production (có thể mở rộng khi cần) */
    private array $dataset = [
        'en_US' => [
            'male' => ['James', 'John', 'Robert', 'Michael', 'William'],
            'female' => ['Mary', 'Patricia', 'Jennifer', 'Linda', 'Elizabeth'],
            'last' => ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones'],
        ],
        'vi_VN' => [
            'male' => ['Anh', 'Bình', 'Cường', 'Dũng', 'Hải'],
            'female' => ['Anh', 'Bích', 'Chi', 'Dung', 'Hà'],
            'last' => ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Huỳnh'],
        ],
        // … thêm gói nhỏ cho các locale khác nếu cần
    ];

    public function generateName(Request $request)
    {
        /* 1️⃣ Validate */
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

        /* 2️⃣ Extract opt */
        $total = (int) $request->input('name_number', 1);
        $countryCode = strtoupper($request->input('country', 'US'));
        $transAscii = $request->boolean('trans_ascii', false);
        $genderOpt = $request->input('gender', 'random');
        $nameFormat = $request->input('name_format', 'first_middle_last');

        /* 3️⃣ Locale & order */
        [$locale, $order] = $this->resolveLocaleAndOrder($countryCode);
        if ($nameFormat === 'first_last') {
            $order = str_replace('M', '', $order);
            $order = trim(preg_replace('/\s+/', ' ', $order));
        }

        /* 4️⃣ Tạo list tên */
        $names = collect(range(1, $total))->map(function () use ($locale, $genderOpt, $order, $transAscii) {
            // Nếu Faker có sẵn: dùng Faker
            if (class_exists(\Faker\Factory::class)) {
                $faker = \Faker\Factory::create($locale);
                $gender = $genderOpt === 'random'
                    ? $faker->randomElement(['male', 'female'])
                    : $genderOpt;

                $first = $gender === 'male' ? $faker->firstNameMale : $faker->firstNameFemale;
                $middle = '';
                if (str_contains($order, 'M')) {
                    do {
                        $middle = $gender === 'male' ? $faker->firstNameMale : $faker->firstNameFemale;
                    } while ($middle === $first);
                }
                $last = $faker->lastName;
            }
            // Nếu KHÔNG có Faker: dùng dataset fallback
            else {
                $data = $this->dataset[$locale] ?? $this->dataset['en_US'];
                $gender = $genderOpt === 'random'
                    ? Arr::random(['male', 'female'])
                    : $genderOpt;

                $first = Arr::random($data[$gender]);
                $middle = '';
                if (str_contains($order, 'M')) {
                    do {
                        $middle = Arr::random($data[$gender]);
                    } while ($middle === $first);
                }
                $last = Arr::random($data['last']);
            }

            // Ghép theo order
            $tokens = ['F' => $first, 'M' => $middle, 'L' => $last];
            $full = collect(str_split(str_replace(' ', '', $order)))
                ->map(fn($t) => $tokens[$t] ?? '')
                ->filter()
                ->implode(' ');

            return $transAscii ? Str::ascii($full) : $full;
        })->all();

        /* 5️⃣ Output */
        return response()->json([
            'status' => 'success',
            'message' => 'Tạo thành công ' . count($names) . ' tên ' . $countryCode,
            'country' => $countryCode,
            'data' => $names,
        ]);
    }

    /* ------ helpers giữ nguyên ------ */
    private function resolveLocaleAndOrder(string $country): array
    {
        if (!isset(self::LOCALE_MAP[$country]))
            $country = 'US';
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
