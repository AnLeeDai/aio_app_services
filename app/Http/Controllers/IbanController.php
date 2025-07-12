<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class IbanController extends Controller
{
    /* ---------- Endpoint ---------- */
    public function generateIban(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'iban_number' => 'required|integer|min:1|max:100',
            'country' => 'required|string|in:BR',            // chỉ BR
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }

        $total = (int) $request->input('iban_number');
        $ibans = [];

        while (count($ibans) < $total) {
            $iban = $this->buildBrazilIban();                   // sinh mới
            if ($this->verifyIban($iban)) {                     // mod-97 = 1
                $ibans[] = $iban;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo thành công ' . count($ibans) . ' IBAN BR',
            'data' => $ibans,
        ]);
    }

    /* ---------- Generator ---------- */
    private function buildBrazilIban(): string
    {
        // 8-digit ISPB (có thể cố định Citibank = 33479023 :contentReference[oaicite:3]{index=3})
        $bankCode = str_pad(random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
        // 5-digit branch
        $branch = str_pad(random_int(0, 99_999), 5, '0', STR_PAD_LEFT);
        // 10-digit account
        $account = str_pad(random_int(0, 9_999_999_999), 10, '0', STR_PAD_LEFT);

        // Account-type = LETTER (A-Z), Owner-type = DIGIT (0-9)  ➜ khớp mẫu
        $acctType = chr(random_int(65, 90));                   // A-Z
        $ownerType = random_int(0, 9);                          // 0-9

        $bban = $bankCode . $branch . $account . $acctType . $ownerType;
        $check = $this->calcCheckDigits('BR', $bban);           // ISO 7064

        return 'BR' . $check . $bban;                           // 29 ký tự
    }

    /* ---------- Validation ---------- */
    private function calcCheckDigits(string $cc, string $bban): string
    {
        // Chuyển CC & “00” ra cuối theo chuẩn MOD-97 :contentReference[oaicite:4]{index=4}
        $numeric = $bban . $this->lettersToDigits($cc) . '00';
        $rem = $this->mod97($numeric);
        return str_pad((string) (98 - $rem), 2, '0', STR_PAD_LEFT);
    }

    private function verifyIban(string $iban): bool
    {
        // Đưa BBAN ra đầu rồi CC+CD ra cuối, chuyển chữ→số
        $rearr = substr($iban, 4)
            . $this->lettersToDigits(substr($iban, 0, 2))
            . substr($iban, 2, 2);
        return $this->mod97($this->lettersToDigits($rearr)) === 1;
    }

    /* ---------- Utils ---------- */
    private function mod97(string $num): int
    {
        $chk = 0;
        foreach (str_split($num) as $d) {
            $chk = ($chk * 10 + (int) $d) % 97;
        }
        return $chk;
    }

    private function lettersToDigits(string $s): string
    {
        // A=10 … Z=35  :contentReference[oaicite:5]{index=5}
        return preg_replace_callback('/[A-Z]/', fn($m) => ord($m[0]) - 55, $s);
    }

    private function error(string $msg)
    {
        return response()->json(['status' => 'error', 'message' => $msg], 422);
    }
}
