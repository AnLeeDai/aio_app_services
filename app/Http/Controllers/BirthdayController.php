<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BirthdayController extends Controller
{
    use ApiResponse;

    public function generateBirthday(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dob_num' => 'required|integer|min:1|max:100',
            'min_age' => 'required|integer|min:18',
            'max_age' => 'required|integer|min:0|max:100',
            'date_format' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->first(), $validator->errors());
        }

        /* 2. Đọc tham số */
        $dobNum = (int) $request->input('dob_num', 1);
        $minAge = (int) $request->input('min_age', 0);
        $maxAge = (int) $request->input('max_age', 100);
        $dateFormat = $request->input('date_format', 'Y-m-d');

        // Bảo đảm $minAge <= $maxAge
        if ($minAge > $maxAge) {
            [$minAge, $maxAge] = [$maxAge, $minAge];
        }

        /* 3. Tính mốc ngày hợp lệ */
        $today = Carbon::today();
        $startDate = $today->copy()->subYears($maxAge)->addDay(); // Già nhất
        $endDate = $today->copy()->subYears($minAge);           // Trẻ nhất
        $diffDays = $startDate->diffInDays($endDate);            // Luôn >= 0

        /* 4. Sinh ngày sinh */
        $results = [];
        for ($i = 0; $i < $dobNum; $i++) {
            $randDays = $diffDays > 0 ? random_int(0, $diffDays) : 0; // phòng khi diffDays = 0
            $dob = $startDate->copy()->addDays($randDays);
            $results[] = $dob->format($dateFormat);
        }

        /* 5. Trả kết quả */
        return $this->success($results, 'Tạo thành công ' . count($results) . ' ngày sinh');
    }
}
