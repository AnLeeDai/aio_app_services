<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PassportController extends Controller
{
    /* Hai quốc gia hỗ trợ */
    private const LOCALE_MAP = [
        'US' => ['locale' => 'en_US', 'order' => 'F M L'],
        'BR' => ['locale' => 'pt_BR', 'order' => 'F M L'],
    ];

    /* ---------- Sinh số hộ chiếu ---------- */
    public function generatePassport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_number' => 'required|integer|min:1|max:100',
            'country' => 'required|string|in:US,BR',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }

        $total = (int) $request->input('id_number');
        $countryCode = strtoupper($request->input('country'));

        $ids = collect(range(1, $total))
            ->map(fn() => $this->generateNumberForCountry($countryCode))
            ->all();

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo thành công ' . count($ids) . ' passport ID ' . $countryCode,
            'data' => $ids,
        ]);
    }

    /* ---------- Sinh (issue_date, expiry_date) ---------- */
    public function generatePassportDate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_number' => 'required|integer|min:1|max:100',
            'country' => 'required|string|in:US,BR',
            'format' => 'sometimes|string|in:Y-m-d,d/m/Y,d-m-Y,m/d/Y',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }

        $total = (int) $request->input('date_number');
        $country = strtoupper($request->input('country'));
        $formatOut = $request->input('format', 'Y-m-d');          // mặc định ISO
        $validYrs = $this->getValidityYears($country);

        $pairs = collect(range(1, $total))->map(function () use ($validYrs, $formatOut) {
            /* Issue date ngẫu nhiên trong khoảng vẫn còn hiệu lực */
            $maxPastDays = $validYrs * 365 - 2;                 // -2 để chắc chắn chưa hết hạn
            $issue = Carbon::today()->subDays(random_int(0, $maxPastDays));
            $expiry = $issue->copy()->addYears($validYrs)->subDay();

            return [
                'issue_date' => $issue->format($formatOut),
                'expiry_date' => $expiry->format($formatOut),
            ];
        })->all();

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo thành công ' . count($pairs) . ' cặp ngày passport',
            'data' => $pairs,  // chỉ issue_date & expiry_date đã theo format yêu cầu
        ]);
    }

    /* ---------- Helpers ---------- */

    /** US: 1 letter + 8 digits | BR: 2 letters + 6 digits */
    private function generateNumberForCountry(string $country): string
    {
        return match ($country) {
            'US' => chr(random_int(65, 90)) .
            str_pad(random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT),
            'BR' => chr(random_int(65, 90)) .
            chr(random_int(65, 90)) .
            str_pad(random_int(0, 999_999), 6, '0', STR_PAD_LEFT),
            default => throw new \InvalidArgumentException('Unsupported country code'),
        };
    }

    /** Số năm hiệu lực (người lớn) */
    private function getValidityYears(string $country): int
    {
        return 10;
    }

    private function error(string $msg)
    {
        return response()->json([
            'status' => 'error',
            'message' => $msg,
        ], 422);
    }
}
