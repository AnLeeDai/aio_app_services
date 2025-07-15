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
        $prefix = strtoupper($request->input('prefix', ''));

        $ids = collect(range(1, $total))
            ->map(fn() => $this->generateNumberForCountry($countryCode, $prefix))
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
        Validator::make($request->all(), [
            'date_number' => 'required|integer|min:1|max:100',
            'country' => 'required|string|in:US,BR',
            'format' => 'sometimes|string|in:Y-m-d,d/m/Y,d-m-Y,m/d/Y',
        ])->validate();

        $total = (int) $request->input('date_number');
        $country = strtoupper($request->input('country'));
        $formatOut = $request->input('format', 'Y-m-d');
        $validYrs = $this->getValidityYears($country);

        // 1. Muộn nhất để passport còn ít nhất 2 ngày hiệu lực
        $latestIssue = Carbon::today()->subDays(2);

        // 2. Lấy số năm tối thiểu
        $minYearsAgo = 5;

        // 3. Lấy số năm thực tế để sub đi: không quá validYrs, và không quá minYearsAgo
        $yearsToSubtract = min($validYrs, $minYearsAgo);

        // 4. Tính startDate = ngày 1/1 của (hôm nay - $yearsToSubtract năm)
        $startDate = Carbon::today()
            ->copy()
            ->subYears($yearsToSubtract)
            ->startOfYear();

        $pairs = collect(range(1, $total))->map(function () use ($startDate, $latestIssue, $validYrs, $formatOut) {
            $ts = random_int($startDate->timestamp, $latestIssue->timestamp);
            $issue = Carbon::createFromTimestamp($ts);
            $expiry = $issue->copy()->addYears($validYrs)->subDay();

            return [
                'issue_date' => $issue->format($formatOut),
                'expiry_date' => $expiry->format($formatOut),
            ];
        })->all();

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo thành công ' . count($pairs) . ' cặp ngày passport',
            'data' => $pairs,
        ]);
    }

    /* ---------- Helpers ---------- */

    /**
     *  US: 1 letter + 8 digits   – digit#1 ≠ 0
     *  BR: 2 letters + 6 digits – digit#1 ≠ 0
     */
    private function generateNumberForCountry(string $country, string $prefix = ''): string
    {
        return match ($country) {
            'US' => $this->buildId(
                $prefix ?: chr(random_int(65, 90)), // 1 letter
                8                                   // total digits
            ),

            'BR' => $this->buildId(
                $prefix ?: (chr(random_int(65, 90)) . chr(random_int(65, 90))), // 2 letters
                6                                   // total digits
            ),

            default => throw new \InvalidArgumentException('Unsupported country code'),
        };
    }

    /** Ghép $letters + $totalDigits; chữ số đầu tiên luôn 1-9 (≠ 0) */
    private function buildId(string $letters, int $totalDigits): string
    {
        $firstDigit = random_int(1, 9); // không bao giờ 0
        $rest = str_pad(
            (string) random_int(0, (10 ** ($totalDigits - 1)) - 1),
            $totalDigits - 1,
            '0',
            STR_PAD_LEFT
        );

        return $letters . $firstDigit . $rest;
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
