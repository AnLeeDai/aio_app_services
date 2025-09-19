<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class PhoneNumberController extends Controller
{
    use ApiResponse;

    /**
     * Quy tắc cơ bản cho từng quốc gia hỗ trợ.
     * length = tổng độ dài số nội địa (không gồm mã quốc gia).
     * area_length = số chữ số mã vùng (nếu có). subscriber = phần còn lại.
     * mobile_start = bắt buộc chữ số đầu của subscriber (ví dụ BR = 9 cho di động).
     */
    private array $countryRules = [
        'BR' => ['code' => '55', 'length' => 11, 'area_length' => 2, 'mobile_start' => 9], // (AA)9XXXXXXX
        'PE' => ['code' => '51', 'length' => 9],                                          // 9XXXXXXXX
        'MY' => ['code' => '60', 'length' => 9],                                          // XXXXXXXXX (đơn giản hoá)
        'CO' => ['code' => '57', 'length' => 10],                                         // XXXXXXXXXX
        'JM' => ['code' => '1', 'length' => 10],                                         // NANP
        'CL' => ['code' => '56', 'length' => 9],                                          // 9XXXXXXXX
    ];

    /**
     * POST /api/generate/phones
     * Tham số:
     *  - phone_number (int, 1..100) : số lượng cần tạo
     *  - country (optional, ISO2)   : nếu truyền sẽ cố định, nếu không sẽ random theo từng số
     *  - format (optional)          : E164|international|national (mặc định E164)
     *  - unique (optional,bool)     : đảm bảo không trùng (default true)
     */
    public function generatePhoneNumber(Request $request)
    {
        /* 1. Validate */
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|integer|min:1|max:100',
            'country' => 'sometimes|string|size:2',
            'format' => 'sometimes|string|in:E164,international,national',
            'unique' => 'sometimes|boolean',
            'strip_cc' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->first(), $validator->errors());
        }

        /* 2. Lấy option */
        $total = (int) $request->input('phone_number');
        $countryOpt = strtoupper($request->input('country', ''));
        $format = $request->input('format', 'E164');
        $ensureUnique = $request->boolean('unique', true);
        $stripCc = $request->boolean('strip_cc', false);

        if ($countryOpt && !isset($this->countryRules[$countryOpt])) {
            // fallback về random nếu country không hỗ trợ
            $countryOpt = '';
        }

        $results = [];
        $seen = [];
        $maxAttempts = max(200, $total * 15); // tránh vòng lặp vô hạn khi yêu cầu unique
        $attempts = 0;

        while (count($results) < $total && $attempts < $maxAttempts) {
            $attempts++;
            $cc = $countryOpt ?: array_rand($this->countryRules);
            $rule = $this->countryRules[$cc];
            $local = $this->buildLocalNumber($rule);
            $formatted = $this->formatNumber($rule['code'], $local, $rule, $format, $stripCc);

            if ($ensureUnique) {
                if (isset($seen[$formatted])) {
                    continue; // trùng -> sinh lại
                }
                $seen[$formatted] = true;
            }

            $results[] = $formatted;
        }

        if (count($results) < $total) {
            return $this->error('Không tạo đủ số điện thoại duy nhất. Vui lòng giảm số lượng hoặc bỏ unique.');
        }

        $msg = 'Tạo thành công ' . count($results) . ' số điện thoại'
            . ($countryOpt ? ' ' . $countryOpt : '');
        return $this->success($results, $msg);
    }

    /* ========= Helpers ========= */
    private function buildLocalNumber(array $rule): string
    {
        $length = $rule['length'];
        $areaLen = $rule['area_length'] ?? 0;
        $subscriberLen = $length - $areaLen;

        $area = '';
        if ($areaLen > 0) {
            // chữ số đầu không = 0
            $area = (string) random_int(1, 9);
            for ($i = 1; $i < $areaLen; $i++) {
                $area .= (string) random_int(0, 9);
            }
        }

        $subscriber = '';
        for ($i = 0; $i < $subscriberLen; $i++) {
            if ($i === 0 && isset($rule['mobile_start'])) {
                $subscriber .= (string) $rule['mobile_start'];
                continue;
            }
            $subscriber .= (string) random_int(0, 9);
        }

        return $area . $subscriber;
    }

    private function formatNumber(string $countryCode, string $local, array $rule, string $format, bool $stripCc = false): string
    {
        $areaLen = $rule['area_length'] ?? 0;
        $area = $areaLen ? substr($local, 0, $areaLen) : '';
        $subscriber = $areaLen ? substr($local, $areaLen) : $local;

        // Nếu chỉ cần số thuần không kèm mã quốc gia => trả local dạng sạch
        if ($stripCc) {
            return preg_replace('/\D+/', '', $local);
        }

        switch ($format) {
            case 'international':
                if ($area) {
                    return $countryCode . ' (' . $area . ') ' . $this->chunkSubscriber($subscriber);
                }
                return $countryCode . ' ' . $this->chunkSubscriber($subscriber);
            case 'national':
                if ($area) {
                    return '(' . $area . ') ' . $this->chunkSubscriber($subscriber);
                }
                return $this->chunkSubscriber($subscriber);
            case 'E164':
            default:
                // E.164: country code + số sạch
                $clean = preg_replace('/\D+/', '', $local);
                return $countryCode . $clean;
        }
    }

    private function chunkSubscriber(string $subscriber): string
    {
        // đơn giản: tách nhóm 3-3-... cho dễ đọc, không ràng buộc thực tế
        return trim(implode(' ', str_split($subscriber, 3)));
    }
}
