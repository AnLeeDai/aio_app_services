<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class PasswordController extends Controller
{
    use ApiResponse;

    public function generatePassword(Request $request)
    {
        /* 1. Validate đầu vào */
        $validator = Validator::make($request->all(), [
            'password_num' => 'required|integer|min:1|max:100',
            'password_length' => 'required|integer|min:6|max:64',
            'include_special_chars' => 'boolean',
            'is_uppercase' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->first(), $validator->errors());
        }

        /* 2. Lấy tuỳ chọn */
        $passwordNum = (int) $request->input('password_num', 1);
        $passwordLength = (int) $request->input('password_length', 12);
        $includeSpecialChars = $request->boolean('include_special_chars', true);
        $forceUppercase = $request->boolean('is_uppercase', false);

        /* 3. Bộ ký tự cơ bản */
        $lowerChars = range('a', 'z');
        $upperChars = range('A', 'Z');
        $digits = range('0', '9');
        $specialChars = str_split('!@#$%^&*()-_=+[]{};:,.<>?');

        $results = [];

        /* 4. Sinh từng mật khẩu */
        for ($i = 0; $i < $passwordNum; $i++) {
            // 4.1 Bắt buộc ít nhất 1 ký tự của mỗi nhóm cần thiết
            $passwordArray = [
                $upperChars[array_rand($upperChars)],     // 1 in hoa
                $lowerChars[array_rand($lowerChars)],     // 1 in thường
                $digits[array_rand($digits)],             // 1 số
            ];

            if ($includeSpecialChars) {
                $passwordArray[] = $specialChars[array_rand($specialChars)];
            }

            // 4.2 Gộp pool được phép
            $charPool = array_merge(
                $upperChars,
                $lowerChars,
                $digits,
                $includeSpecialChars ? $specialChars : []
            );

            // 4.3 Bổ sung ngẫu nhiên cho đủ độ dài
            while (count($passwordArray) < $passwordLength) {
                $passwordArray[] = $charPool[array_rand($charPool)];
            }

            // 4.4 Xáo trộn để vị trí ký tự bắt buộc không cố định
            shuffle($passwordArray);
            $password = implode('', $passwordArray);

            // 4.5 Nếu ép in hoa, chuyển mọi chữ cái sang in hoa
            if ($forceUppercase) {
                $password = preg_replace_callback(
                    '/[a-z]/i',
                    fn($m) => strtoupper($m[0]),
                    $password
                );
            }

            $results[] = $password;
        }

        /* 5. Trả kết quả */
        return $this->success($results, 'Tạo thành công ' . count($results) . ' mật khẩu');
    }
}
