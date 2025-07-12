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
    private int $nameNumber = 1;
    private string $locale = 'en_US';
    private string $country = 'US';

    /** ---- Bản đồ quốc gia -> locale & thứ tự họ‑tên ---- */
    private array $localeMap = [
        'US' => ['locale' => 'en_US', 'order' => 'F M L'], // Tên‑Đệm‑Họ
        'UK' => ['locale' => 'en_GB', 'order' => 'F M L'],
        'VN' => ['locale' => 'vi_VN', 'order' => 'L M F'], // Họ‑Đệm‑Tên
        'DE' => ['locale' => 'de_DE', 'order' => 'F M L'],
        'FR' => ['locale' => 'fr_FR', 'order' => 'F M L'],
        'JP' => ['locale' => 'ja_JP', 'order' => 'L F'],   // Họ trước
        'ES' => ['locale' => 'es_ES', 'order' => 'F M L'],
        'IT' => ['locale' => 'it_IT', 'order' => 'F M L'],
        'RU' => ['locale' => 'ru_RU', 'order' => 'F M L'],
        'CN' => ['locale' => 'zh_CN', 'order' => 'L F'],   // Họ trước
        'KR' => ['locale' => 'ko_KR', 'order' => 'L F'],   // Họ trước
    ];

    /**
     * Endpoint GET|POST : /api/generate-name
     */
    public function generateName(Request $request)
    {
        // 1. Kiểm tra dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'name_number' => 'required|integer|min:1|max:100',
            'country' => 'string|size:2',
            'trans_ascii' => 'boolean',
            'gender' => 'nullable|in:male,female,random',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // 2. Đọc tuỳ chọn từ request
        $this->nameNumber = (int) $request->input('name_number', 1);
        $this->country = strtoupper($request->input('country', 'US'));
        $transAscii = $request->boolean('trans_ascii', false);
        $inputGender = $request->input('gender', 'random'); // male|female|random

        // Nếu quốc gia không tồn tại trong map → mặc định US
        if (!isset($this->localeMap[$this->country])) {
            $this->country = 'US';
        }
        $this->locale = $this->localeMap[$this->country]['locale'];
        $orderPattern = $this->localeMap[$this->country]['order'];

        // 3. Khởi tạo Faker với locale phù hợp
        $faker = Faker::create($this->locale);

        // 4. Sinh danh sách tên
        $results = [];
        for ($i = 0; $i < $this->nameNumber; $i++) {
            // 4.1 Xác định giới tính theo yêu cầu
            $gender = $inputGender === 'random'
                ? $faker->randomElement(['male', 'female'])
                : $inputGender;

            // 4.2 Sinh tên chính & tên đệm (bắt buộc nếu pattern có "M")
            $firstName = $gender === 'male'
                ? $faker->firstNameMale
                : $faker->firstNameFemale;

            $middleName = '';
            if (str_contains($orderPattern, 'M')) {
                do {
                    $middleName = $gender === 'male'
                        ? $faker->firstNameMale
                        : $faker->firstNameFemale;
                } while ($middleName === $firstName); // tránh trùng tên chính
            }

            // 4.3 Sinh họ
            $lastName = $faker->lastName;

            // 4.4 Ghép tên theo thứ tự văn hoá
            $tokens = [
                'F' => $firstName,
                'M' => $middleName,
                'L' => $lastName,
            ];
            $nameParts = array_map(
                fn($t) => $tokens[$t] ?? '',
                str_split(str_replace(' ', '', $orderPattern))
            );
            $fullName = preg_replace('/\s+/', ' ', trim(implode(' ', $nameParts)));

            // 4.5 Chuyển ASCII nếu được yêu cầu (hữu ích cho xuất CSV)
            if ($transAscii) {
                $fullName = Str::ascii($fullName);
            }

            $results[] = $fullName;
        }

        // 5. Trả kết quả JSON về client
        return response()->json([
            'status' => 'success',
            'message' => 'Tạo thành công ' . count($results) . ' tên',
            'country' => $this->country,
            'data' => $results,
        ]);
    }
}
