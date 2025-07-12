<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class NameGeneratorController extends Controller
{
    /** ---- Cấu hình mặc định ---- */
    private int $nameNumber = 1;          // số lượng tên cần sinh
    private string $locale = 'en_US';  // locale Faker
    private string $country = 'US';     // mã quốc gia ISO‑2

    /** ---- Bản đồ quốc gia -> locale & thứ tự họ‑tên ---- */
    private array $localeMap = [
        'US' => ['locale' => 'en_US', 'order' => 'F M L'], // Tên‑Đệm‑Họ
        'UK' => ['locale' => 'en_GB', 'order' => 'F M L'],
        'VN' => ['locale' => 'vi_VN', 'order' => 'L M F'], // Họ‑Đệm‑Tên
        'DE' => ['locale' => 'de_DE', 'order' => 'F M L'],
        'FR' => ['locale' => 'fr_FR', 'order' => 'F M L'],
        'JP' => ['locale' => 'ja_JP', 'order' => 'L F'],   // Họ trước (không có Đệm)
        'ES' => ['locale' => 'es_ES', 'order' => 'F M L'],
        'IT' => ['locale' => 'it_IT', 'order' => 'F M L'],
        'RU' => ['locale' => 'ru_RU', 'order' => 'F M L'],
        'CN' => ['locale' => 'zh_CN', 'order' => 'L F'],   // Họ trước (không có Đệm)
        'KR' => ['locale' => 'ko_KR', 'order' => 'L F'],   // Họ trước (không có Đệm)
    ];

    /**
     * GET|POST: /api/generate-name
     */
    public function generateName(Request $request)
    {
        /* 1. Kiểm tra dữ liệu đầu vào */
        $validator = Validator::make($request->all(), [
            'name_number' => 'required|integer|min:1|max:100',
            'country' => 'string|size:2',
            'trans_ascii' => 'boolean',
            'gender' => 'nullable|in:male,female,random',
            'name_format' => 'nullable|in:first_last,first_middle_last',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        /* 2. Đọc tuỳ chọn */
        $this->nameNumber = (int) $request->input('name_number', 1);
        $this->country = strtoupper($request->input('country', 'US'));
        $transAscii = $request->boolean('trans_ascii', false);
        $inputGender = $request->input('gender', 'random'); // male|female|random
        $nameFormat = $request->input('name_format', 'first_middle_last');

        // Nếu quốc gia không có trong map → mặc định US
        if (!isset($this->localeMap[$this->country])) {
            $this->country = 'US';
        }
        $this->locale = $this->localeMap[$this->country]['locale'];
        $orderPattern = $this->localeMap[$this->country]['order'];

        // Nếu người dùng chọn chỉ Tên‑Họ → loại ký hiệu M khỏi pattern
        if ($nameFormat === 'first_last') {
            $orderPattern = str_replace('M', '', $orderPattern);
            $orderPattern = preg_replace('/\s+/', ' ', trim($orderPattern)); // dọn dấu cách dư
        }

        /* 3. Tạo Faker */
        $faker = Faker::create($this->locale);

        /* 4. Sinh tên */
        $results = [];
        for ($i = 0; $i < $this->nameNumber; $i++) {
            /* 4.1 Xác định giới tính */
            $gender = $inputGender === 'random'
                ? $faker->randomElement(['male', 'female'])
                : $inputGender;

            /* 4.2 Tạo Tên chính & (nếu cần) Tên đệm */
            $firstName = $gender === 'male' ? $faker->firstNameMale : $faker->firstNameFemale;
            $middleName = '';
            if (str_contains($orderPattern, 'M')) {
                do {
                    $middleName = $gender === 'male' ? $faker->firstNameMale : $faker->firstNameFemale;
                } while ($middleName === $firstName);
            }

            /* 4.3 Tạo Họ */
            $lastName = $faker->lastName;

            /* 4.4 Ghép theo pattern */
            $tokens = ['F' => $firstName, 'M' => $middleName, 'L' => $lastName];
            $nameParts = array_map(
                fn($t) => $tokens[$t] ?? '',
                str_split(str_replace(' ', '', $orderPattern))
            );
            $fullName = preg_replace('/\s+/', ' ', trim(implode(' ', $nameParts)));

            /* 4.5 Chuyển ASCII nếu cần */
            if ($transAscii) {
                $fullName = Str::ascii($fullName);
            }

            $results[] = $fullName;
        }

        /* 5. Trả JSON */
        return response()->json([
            'status' => 'success',
            'message' => 'Tạo thành công ' . count($results) . ' tên',
            'country' => $this->country,
            'data' => $results,
        ]);
    }
}
