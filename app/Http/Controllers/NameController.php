<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class NameController extends Controller
{
    use ApiResponse;

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
        'PY' => ['locale' => 'es_ES', 'order' => 'F M L'], // Use es_ES for Paraguay as es_PY may not be available in Faker
    ];

    public function generateName(Request $request)
    {
        /*  Validate */
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

        /*  Extract opt */
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

        /* Kiểm tra Faker availability */
        if (!class_exists(\Faker\Factory::class)) {
            return $this->error('Faker library không khả dụng. Vui lòng cài đặt faker/faker package.');
        }

        // Tạo Faker instance với fallback locale
        try {
            $faker = \Faker\Factory::create($locale);
        } catch (\InvalidArgumentException $e) {
            // Nếu locale không được hỗ trợ, fallback về en_US
            $faker = \Faker\Factory::create('en_US');
        }

        $names = [];
        $seen = [];
        $attempts = 0;
        $maxAttempts = max(100, $total * 20); // giới hạn an toàn tránh vòng lặp vô hạn

        while (count($names) < $total && $attempts < $maxAttempts) {
            $attempts++;

            $gender = $genderOpt === 'random'
                ? $faker->randomElement(['male', 'female'])
                : $genderOpt;

            try {
                // Thử sử dụng gender-specific methods trước
                if ($gender === 'male' && method_exists($faker, 'firstNameMale')) {
                    $first = $faker->firstNameMale;
                } elseif ($gender === 'female' && method_exists($faker, 'firstNameFemale')) {
                    $first = $faker->firstNameFemale;
                } else {
                    $first = $faker->firstName;
                }

                $middle = '';
                if (str_contains($order, 'M')) {
                    $middleAttempts = 0;
                    do {
                        if ($gender === 'male' && method_exists($faker, 'firstNameMale')) {
                            $middle = $faker->firstNameMale;
                        } elseif ($gender === 'female' && method_exists($faker, 'firstNameFemale')) {
                            $middle = $faker->firstNameFemale;
                        } else {
                            $middle = $faker->firstName;
                        }
                        $middleAttempts++;
                    } while ($middle === $first && $middleAttempts < 5);
                }

                $last = $faker->lastName;
            } catch (\Exception $e) {
                // Fallback to generic methods nếu có lỗi
                $first = $faker->firstName;
                $middle = str_contains($order, 'M') ? $faker->firstName : '';
                $last = $faker->lastName;
            }

            // Ghép theo order
            $tokens = ['F' => $first, 'M' => $middle, 'L' => $last];
            $full = collect(str_split(str_replace(' ', '', $order)))
                ->map(fn($t) => $tokens[$t] ?? '')
                ->filter()
                ->implode(' ');

            $final = $transAscii ? Str::ascii($full) : $full;

            if (!isset($seen[$final])) {
                $seen[$final] = true;
                $names[] = $final;
            }
        }

        if (count($names) < $total) {
            return $this->error('Không tạo đủ tên duy nhất. Vui lòng giảm số lượng hoặc đổi tuỳ chọn.');
        }

        /* 5️⃣ Output */
        return $this->success($names, 'Tạo thành công ' . count($names) . ' tên ' . $countryCode, 200);
    }

    /* ------ helpers giữ nguyên ------ */
    private function resolveLocaleAndOrder(string $country): array
    {
        if (!isset(self::LOCALE_MAP[$country]))
            $country = 'US';
        $info = self::LOCALE_MAP[$country];
        return [$info['locale'], $info['order']];
    }
}
