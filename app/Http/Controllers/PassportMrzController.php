<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PassportMrzController extends Controller
{
    /**
     * Nhận dữ liệu đầu vào và trả về MRZ (Machine Readable Zone)
     *
     * @param Request $request Dữ liệu đầu vào JSON
     * @return \Illuminate\Http\JsonResponse
     */
    public function generate(Request $request)
    {
        /* ---------- Nhận & chuẩn hoá dữ liệu đầu vào ---------- */
        $rows = $this->normalizeInput($request->json()->all());

        // Ép tất cả trường ngày về định dạng ISO Y-m-d để dễ xử lý và kiểm tra
        foreach ($rows as &$row) {
            foreach (['dob', 'expiry'] as $field) {
                if (!empty($row[$field])) {
                    $row[$field] = $this->toIsoDate($row[$field]);
                }
            }
        }
        unset($row); // Hủy tham chiếu sau vòng lặp

        /* ---------- Validate dữ liệu đầu vào ---------- */
        $rules = [
            '*.subtype' => 'nullable|size:1|alpha', // Ký tự phụ của loại tài liệu (ví dụ: 'V' cho Visa, thường là '<' cho hộ chiếu)
            '*.given_names' => 'required|string',   // Tên riêng của người giữ hộ chiếu
            '*.surname' => 'required|string',       // Họ của người giữ hộ chiếu
            '*.dob' => 'required|date_format:Y-m-d',// Ngày sinh (YYYY-MM-DD)
            '*.sex' => 'required|in:M,F',          // Giới tính (M: Nam, F: Nữ, <: Không xác định/Không áp dụng)
            '*.issuer' => 'required|size:3|alpha',  // Quốc gia cấp phát (mã 3 chữ cái theo ISO 3166-1 alpha-3)
            '*.expiry' => 'required|date_format:Y-m-d|after:dob', // Ngày hết hạn (YYYY-MM-DD), phải sau ngày sinh
            '*.passport_num' => 'required',   // Số hộ chiếu (tối đa 9 ký tự)
            '*.personal_num' => 'nullable',  // Số cá nhân hoặc dữ liệu tùy chọn (tối đa 14 ký tự)
            '*.nationality' => 'required|size:3|alpha', // Quốc tịch (mã 3 chữ cái theo ISO 3166-1 alpha-3)
        ];

        $v = Validator::make($rows, $rules);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $rows = $v->validated();

        /* ---------- Tính toán và xây dựng MRZ ---------- */
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'input' => $row,
                'mrz' => $this->buildMrz($row),
            ];
        }
        return response()->json($out);
    }

    /* ========== Các Hàm Hỗ Trợ ========== */

    /**
     * Chuyển đổi chuỗi ngày bất kỳ về định dạng ISO (Y-m-d).
     * Sẽ giữ nguyên giá trị nếu không thể phân tích được, để validator báo lỗi sau đó.
     *
     * @param string $value Chuỗi ngày đầu vào
     * @return string Ngày ở định dạng Y-m-d hoặc chuỗi gốc nếu không thể phân tích
     */
    private function toIsoDate(string $value): string
    {
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'Y/m/d'];
        foreach ($formats as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $value);
                if ($dt !== false) {
                    return $dt->format('Y-m-d');
                }
            } catch (\Exception $e) {
                // Tiếp tục thử các định dạng khác nếu có lỗi
            }
        }
        // Fallback: để Carbon tự đoán định dạng
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return $value; // Trả về giá trị gốc để validator báo lỗi
        }
    }

    /**
     * Chuẩn hoá mọi định dạng đầu vào thành một mảng các đối tượng (array<object>).
     * Hỗ trợ đối tượng đơn, mảng đối tượng, hoặc mảng 2D (ví dụ: từ CSV).
     *
     * @param mixed $input Dữ liệu đầu vào
     * @return array Mảng các đối tượng đã được chuẩn hoá
     */
    private function normalizeInput(mixed $input): array
    {
        if ($this->isAssoc($input)) {
            return [$input];                       // Xử lý trường hợp đối tượng đơn
        }
        if (is_array($input) && isset($input[0]) && $this->isAssoc($input[0])) {
            return $input;                         // Xử lý trường hợp mảng đối tượng
        }
        if (is_array($input) && isset($input[0]) && is_array($input[0])) {
            // Xử lý trường hợp mảng 2D (ví dụ: hàng đầu tiên là tiêu đề)
            $headers = array_map(fn($h) => trim(strtolower($h)), $input[0]);
            $result = [];
            foreach (array_slice($input, 1) as $row) {
                if (!is_array($row) || !count($row)) continue;
                $obj = [];
                foreach ($headers as $i => $k) {
                    $obj[$k] = $row[$i] ?? '';
                }
                $result[] = $obj;
            }
            return $result;
        }
        return [];
    }

    /**
     * Sinh ra 2 dòng MRZ theo tiêu chuẩn TD3 (Passport).
     *
     * @param array $d Mảng dữ liệu hộ chiếu đã được validate
     * @return array Mảng chứa 2 chuỗi MRZ (dòng 1, dòng 2)
     */
    private function buildMrz(array $d): array
    {
        // Hàm làm sạch chuỗi: chuyển đổi sang chữ hoa, thay khoảng trắng bằng '<', loại bỏ ký tự không hợp lệ
        $clean = fn($s) => preg_replace('/[^A-Z0-9<]/', '<',
            str_replace(' ', '<', strtoupper($s)));

        /* ===== Dòng 1 của MRZ (44 ký tự) ===== */
        // Cấu trúc: Loại tài liệu (1) + Loại phụ (1) + Mã quốc gia cấp (3) + Tên (39)
        $docType = 'P'; // Ký tự cố định cho Hộ chiếu (Passport)
        // Ký tự loại phụ: thường là '<' cho hộ chiếu tiêu chuẩn, hoặc có thể là 'V' cho Visa
        $subType = !empty($d['subtype']) ? strtoupper($d['subtype'][0]) : '<';
        $issuerCode = strtoupper($d['issuer']); // Mã quốc gia cấp (ví dụ: BRA)

        // Tên: Họ << Tên riêng. Điền đầy bằng '<' đến 39 ký tự.
        $nameField = str_pad(
            $clean($d['surname']) . '<<' . $clean($d['given_names']),
            39,
            '<'
        );

        $line1 = $docType . $subType . $issuerCode . $nameField;
        // Đảm bảo dòng 1 luôn đủ 44 ký tự, mặc dù logic trên đã đảm bảo
        $line1 = str_pad($line1, 44, '<');

        /* ===== Dòng 2 của MRZ (44 ký tự) ===== */
        // Cấu trúc:
        // Số hộ chiếu (9) + CD (1) + Quốc tịch (3) + Ngày sinh (6) + CD (1) + Giới tính (1) +
        // Ngày hết hạn (6) + CD (1) + Số cá nhân/Dữ liệu tùy chọn (14) + CD (1) + CD tổng thể (1)

        // 1. Số hộ chiếu (9 ký tự), điền đầy bằng '<'
        $passportNumField = str_pad(strtoupper($d['passport_num']), 9, '<');
        // 2. Ký tự kiểm tra cho số hộ chiếu
        $passportNumCd = $this->cd($passportNumField);

        // 3. Quốc tịch (3 ký tự)
        $nationalityCode = strtoupper($d['nationality']);

        // 4. Ngày sinh (YYMMDD)
        $dobField = date('ymd', strtotime($d['dob']));
        // 5. Ký tự kiểm tra cho ngày sinh
        $dobCd = $this->cd($dobField);

        // 6. Giới tính (M, F, hoặc '<' nếu không xác định)
        $sexField = !empty($d['sex']) ? strtoupper($d['sex'][0]) : '<';

        // 7. Ngày hết hạn (YYMMDD)
        $expiryField = date('ymd', strtotime($d['expiry']));
        // 8. Ký tự kiểm tra cho ngày hết hạn
        $expiryCd = $this->cd($expiryField);

        // 9. Số cá nhân hoặc dữ liệu tùy chọn (14 ký tự), điền đầy bằng '<'
        $personalNumField = str_pad(strtoupper($d['personal_num'] ?? ''), 14, '<');
        // 10. Ký tự kiểm tra cho số cá nhân/dữ liệu tùy chọn
        $personalNumCd = $this->cd($personalNumField);

        // Chuỗi kết hợp cho ký tự kiểm tra tổng thể
        $compositeString = $passportNumField . $passportNumCd .
            $dobField . $dobCd .
            $expiryField . $expiryCd .
            $personalNumField . $personalNumCd;

        // 11. Ký tự kiểm tra tổng thể cho toàn bộ dòng 2 (trừ ký tự kiểm tra tổng thể đó)
        $overallCd = $this->cd($compositeString);

        // Xây dựng dòng 2
        $line2 = $passportNumField . $passportNumCd .
            $nationalityCode .
            $dobField . $dobCd .
            $sexField .
            $expiryField . $expiryCd .
            $personalNumField . $personalNumCd .
            $overallCd;

        // Đảm bảo dòng 2 luôn đủ 44 ký tự
        $line2 = str_pad($line2, 44, '<');

        return [$line1, $line2];
    }

    /**
     * Tính toán ký tự kiểm tra (Check Digit) theo tiêu chuẩn ICAO Doc 9303.
     *
     * @param string $field Chuỗi ký tự cần tính check digit
     * @return int Ký tự kiểm tra (một chữ số từ 0-9)
     */
    private function cd(string $field): int
    {
        // Ánh xạ các ký tự sang giá trị số học
        // 0-9 -> 0-9
        // A-Z -> 10-35
        // '<' -> 0
        static $vals = null;
        if (!$vals) {
            $vals = array_combine(
                array_merge(range('0', '9'), range('A', 'Z'), ['<']),
                array_merge(range(0, 9), range(10, 35), [0])
            );
        }

        // Trọng số được sử dụng trong phép tính (7, 3, 1 lặp lại)
        $weights = [7, 3, 1];
        $sum = 0;

        // Duyệt qua từng ký tự trong chuỗi
        foreach (str_split($field) as $i => $ch) {
            // Lấy giá trị số của ký tự, mặc định là 0 nếu không tìm thấy (ví dụ: ký tự không hợp lệ)
            $charValue = $vals[$ch] ?? 0;
            // Cộng vào tổng theo công thức: giá trị ký tự * trọng số tương ứng
            $sum += $charValue * $weights[$i % 3];
        }

        // Ký tự kiểm tra là phần dư của tổng khi chia cho 10
        return $sum % 10;
    }

    /**
     * Kiểm tra xem một mảng có phải là mảng kết hợp (associative array) hay không.
     *
     * @param array $a Mảng cần kiểm tra
     * @return bool True nếu là mảng kết hợp, False nếu là mảng tuần tự
     */
    private function isAssoc(array $a): bool
    {
        // Nếu các khóa của mảng không khớp với một dãy số nguyên liên tiếp từ 0,
        // thì đó là mảng kết hợp.
        return array_keys($a) !== range(0, count($a) - 1);
    }
}
