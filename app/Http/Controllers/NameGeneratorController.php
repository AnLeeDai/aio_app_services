<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class NameGeneratorController extends Controller
{
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
    ];

    private array $dataset = [
        'en_US' => [
            'male' => ['Liam', 'Noah', 'Oliver', 'Elijah', 'James', 'William', 'Benjamin', 'Lucas', 'Henry', 'Alexander'],
            'female' => ['Olivia', 'Emma', 'Ava', 'Charlotte', 'Sophia', 'Amelia', 'Isabella', 'Mia', 'Evelyn', 'Harper'],
            'last' => ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'],
        ],
        'en_GB' => [
            'male' => ['Muhammad', 'Noah', 'Oliver', 'Arthur', 'George', 'Leo', 'Oscar', 'Harry', 'Henry', 'Charlie'],
            'female' => ['Olivia', 'Amelia', 'Isla', 'Ava', 'Ivy', 'Freya', 'Lily', 'Florence', 'Mia', 'Evie'],
            'last' => ['Smith', 'Jones', 'Williams', 'Taylor', 'Brown', 'Davies', 'Evans', 'Thomas', 'Wilson', 'Johnson'],
        ],
        'vi_VN' => [
            'male' => ['Anh', 'Minh', 'Dũng', 'Huy', 'Phong', 'Tuấn', 'Khang', 'Đạt', 'Bảo', 'Khánh'],
            'female' => ['Anh', 'Linh', 'Ngọc', 'Trang', 'Phương', 'Mai', 'Thảo', 'Hà', 'Hương', 'Yến'],
            'last' => ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Huỳnh', 'Hoàng', 'Phan', 'Vũ', 'Đặng', 'Bùi'],
        ],
        'de_DE' => [
            'male' => ['Noah', 'Matteo', 'Elias', 'Luca', 'Leon', 'Theo', 'Finn', 'Paul', 'Emil', 'Henry'],
            'female' => ['Emilia', 'Sophia', 'Emma', 'Hannah', 'Mia', 'Lina', 'Ella', 'Lia', 'Leni', 'Mila'],
            'last' => ['Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Schulz', 'Becker', 'Hoffmann'],
        ],
        'fr_FR' => [
            'male' => ['Lucas', 'Hugo', 'Gabriel', 'Louis', 'Arthur', 'Raphaël', 'Adam', 'Léo', 'Ethan', 'Nathan'],
            'female' => ['Emma', 'Jade', 'Louise', 'Alice', 'Chloé', 'Lina', 'Rose', 'Anna', 'Léa', 'Léna'],
            'last' => ['Martin', 'Bernard', 'Thomas', 'Petit', 'Robert', 'Richard', 'Durand', 'Dubois', 'Moreau', 'Laurent'],
        ],
        'ja_JP' => [
            'male' => ['Haruto', 'Sōta', 'Yuto', 'Riku', 'Ren', 'Yuki', 'Sora', 'Kaito', 'Ryūsei', 'Yūma'],
            'female' => ['Yui', 'Hina', 'Rin', 'Mio', 'Sakura', 'Tsumugi', 'Aoi', 'Miyu', 'Yuna', 'Hiyori'],
            'last' => ['Satō', 'Suzuki', 'Takahashi', 'Tanaka', 'Watanabe', 'Itō', 'Yamamoto', 'Nakamura', 'Kobayashi', 'Katō'],
        ],
        'es_ES' => [
            'male' => ['Martín', 'Hugo', 'Mateo', 'Lucas', 'Leo', 'Daniel', 'Alejandro', 'Pablo', 'Enzo', 'Manuel'],
            'female' => ['Lucía', 'Sofía', 'Martina', 'Valeria', 'Julia', 'Paula', 'Alba', 'Emma', 'Sara', 'Carmen'],
            'last' => ['García', 'Rodríguez', 'González', 'Fernández', 'López', 'Martínez', 'Sánchez', 'Pérez', 'Romero', 'Torres'],
        ],
        'it_IT' => [
            'male' => ['Leonardo', 'Francesco', 'Alessandro', 'Lorenzo', 'Matteo', 'Tommaso', 'Edoardo', 'Gabriele', 'Riccardo', 'Andrea'],
            'female' => ['Sofia', 'Giulia', 'Aurora', 'Ginevra', 'Alice', 'Beatrice', 'Emma', 'Vittoria', 'Martina', 'Chiara'],
            'last' => ['Rossi', 'Russo', 'Ferrari', 'Esposito', 'Bianchi', 'Romano', 'Colombo', 'Ricci', 'Marino', 'Greco'],
        ],
        'ru_RU' => [
            'male' => ['Mikhail', 'Alexander', 'Artem', 'Matvey', 'Maxim', 'Ivan', 'Dmitry', 'Nikita', 'Kirill', 'Yegor'],
            'female' => ['Sofia', 'Anna', 'Maria', 'Eva', 'Victoria', 'Olga', 'Natalia', 'Elena', 'Daria', 'Polina'],
            'last' => ['Ivanov', 'Smirnov', 'Kuznetsov', 'Popov', 'Volkov', 'Sokolov', 'Lebedev', 'Morozov', 'Petrov', 'Novikov'],
        ],
        'zh_CN' => [
            'male' => ['Wei', 'Hao', 'Jun', 'Lei', 'Qiang', 'Ming', 'Peng', 'Jie', 'Chao', 'Bo'],
            'female' => ['Fang', 'Li', 'Na', 'Jing', 'Yan', 'Xia', 'Ling', 'Mei', 'Ying', 'Hui'],
            'last' => ['Wang', 'Li', 'Zhang', 'Liu', 'Chen', 'Yang', 'Huang', 'Zhao', 'Wu', 'Zhou'],
        ],
        'ko_KR' => [
            'male' => ['Min-jun', 'Seo-jun', 'Ji-hoon', 'Hyun-woo', 'Jun-seo', 'Ha-joon', 'Ji-hu', 'Do-hyun', 'Joon-woo', 'Sung-min'],
            'female' => ['Seo-yeon', 'Ji-woo', 'Ha-yoon', 'Ji-yoon', 'Soo-min', 'Seo-ah', 'I-seo', 'Arin', 'Harin', 'Ji-yu'],
            'last' => ['Kim', 'Lee', 'Park', 'Choi', 'Jung', 'Kang', 'Cho', 'Yoon', 'Jang', 'Lim'],
        ],
        'pt_BR' => [
            'male' => ['Miguel', 'Arthur', 'Heitor', 'Theo', 'Davi', 'Gabriel', 'Gael', 'Ravi', 'Benício', 'Samuel'],
            'female' => ['Helena', 'Alice', 'Laura', 'Manuela', 'Isabella', 'Sophia', 'Valentina', 'Luna', 'Maria', 'Luiza'],
            'last' => ['Silva', 'Santos', 'Oliveira', 'Souza', 'Pereira', 'Costa', 'Rodrigues', 'Almeida', 'Ribeiro', 'Ferreira'],
        ],
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

        /* Tạo list tên */
        $names = collect(range(1, $total))->map(function () use ($locale, $genderOpt, $order, $transAscii) {
            // Nếu Faker có sẵn: dùng Faker
            if (class_exists(\Faker\Factory::class)) {
                $faker = \Faker\Factory::create($locale);
                $gender = $genderOpt === 'random'
                    ? $faker->randomElement(['male', 'female'])
                    : $genderOpt;

                $first = $gender === 'male' ? $faker->firstNameMale : $faker->firstNameFemale;
                $middle = '';
                if (str_contains($order, 'M')) {
                    do {
                        $middle = $gender === 'male' ? $faker->firstNameMale : $faker->firstNameFemale;
                    } while ($middle === $first);
                }
                $last = $faker->lastName;
            }
            // Nếu KHÔNG có Faker: dùng dataset fallback
            else {
                $data = $this->dataset[$locale] ?? $this->dataset['en_US'];
                $gender = $genderOpt === 'random'
                    ? Arr::random(['male', 'female'])
                    : $genderOpt;

                $first = Arr::random($data[$gender]);
                $middle = '';
                if (str_contains($order, 'M')) {
                    do {
                        $middle = Arr::random($data[$gender]);
                    } while ($middle === $first);
                }
                $last = Arr::random($data['last']);
            }

            // Ghép theo order
            $tokens = ['F' => $first, 'M' => $middle, 'L' => $last];
            $full = collect(str_split(str_replace(' ', '', $order)))
                ->map(fn($t) => $tokens[$t] ?? '')
                ->filter()
                ->implode(' ');

            return $transAscii ? Str::ascii($full) : $full;
        })->all();

        /* 5️⃣ Output */
        return response()->json([
            'status' => 'success',
            'message' => 'Tạo thành công ' . count($names) . ' tên ' . $countryCode,
            'country' => $countryCode,
            'data' => $names,
        ]);
    }

    /* ------ helpers giữ nguyên ------ */
    private function resolveLocaleAndOrder(string $country): array
    {
        if (!isset(self::LOCALE_MAP[$country]))
            $country = 'US';
        $info = self::LOCALE_MAP[$country];
        return [$info['locale'], $info['order']];
    }

    private function error(string $msg)
    {
        return response()->json([
            'status' => 'error',
            'message' => $msg,
        ], 422);
    }
}
