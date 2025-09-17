<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EmailEbayGenerateController extends Controller
{
    use ApiResponse;

    /**
     * Generate eBay email addresses with passwords and names
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateEmail(Request $request)
    {
        // Validate input - chỉ cần số lượng email
        $validator = Validator::make($request->all(), [
            'email_num' => 'required|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->first(), $validator->errors());
        }

        $emailNum = (int) $request->input('email_num');
        $domain = 'outlook.com'; // Fixed domain

        // Generate emails
        $emails = [];
        for ($i = 0; $i < $emailNum; $i++) {
            $emails[] = $this->generateSingleEmail($domain);
        }

        return $this->success($emails, 'Tạo thành công ' . count($emails) . ' email');
    }

    /**
     * Generate a single email address with password and name
     *
     * @param string $domain
     * @return array
     */
    private function generateSingleEmail(string $domain): array
    {
        // Generate name first
        $nameData = $this->generateRandomName();
        
        // Create username from name
        $username = $this->createUsernameFromName($nameData['first_name'], $nameData['last_name']);
        
        // Generate password
        $password = $this->generatePassword();
        
        // Create email format: email|password
        $emailWithPassword = $username . '@' . $domain . '|' . $password;
        
        return [
            'full_name' => $nameData['full_name'],
            'email' => $emailWithPassword,
        ];
    }

    /**
     * Generate random name
     *
     * @return array
     */
    private function generateRandomName(): array
    {
        // Prefer Faker when available, otherwise fallback to a small static pool
        if (class_exists(\Faker\Factory::class)) {
            $faker = \Faker\Factory::create('pt_BR'); // Diverse names
            $firstName = Str::ascii($faker->firstName);
            $lastName = Str::ascii($faker->lastName);
        } else {
            $firstNames = [
                'Liam','Noah','Oliver','Elijah','James','William','Benjamin','Lucas','Henry','Alexander',
                'Emma','Olivia','Ava','Isabella','Sophia','Charlotte','Amelia','Mia','Harper','Evelyn'
            ];
            $lastNames = [
                'Smith','Johnson','Williams','Brown','Jones','Garcia','Miller','Davis','Rodriguez','Martinez',
                'Hernandez','Lopez','Gonzalez','Wilson','Anderson','Thomas','Taylor','Moore','Jackson','Martin'
            ];
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $firstName . ' ' . $lastName,
        ];
    }

    /**
     * Create username from name
     *
     * @param string $firstName
     * @param string $lastName
     * @return string
     */
    private function createUsernameFromName(string $firstName, string $lastName): string
    {
        // Convert to lowercase and remove accents
        $firstName = Str::lower(Str::ascii($firstName));
        $lastName = Str::lower(Str::ascii($lastName));
        
        // Create username: firstname + lastname + random numbers/letters
        $randomSuffix = $this->generateRandomSuffix();
        
        return $firstName . $lastName . $randomSuffix;
    }

    /**
     * Generate random suffix for username
     *
     * @return string
     */
    private function generateRandomSuffix(): string
    {
        $patterns = [
            'dr' . sprintf('%02d', rand(1, 99)) . Str::random(2),
            sprintf('%02d', rand(1, 99)) . Str::random(2),
            Str::random(2) . sprintf('%02d', rand(1, 99)),
            sprintf('%04d', rand(1000, 9999)),
        ];

        return $patterns[array_rand($patterns)];
    }

    /**
     * Generate password
     *
     * @return string
     */
    private function generatePassword(): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        
        $password = '';
        $password .= $uppercase[rand(0, strlen($uppercase) - 1)]; // 1 uppercase
        $password .= $lowercase[rand(0, strlen($lowercase) - 1)]; // 1 lowercase
        $password .= $lowercase[rand(0, strlen($lowercase) - 1)]; // 1 lowercase
        $password .= $uppercase[rand(0, strlen($uppercase) - 1)]; // 1 uppercase
        $password .= $uppercase[rand(0, strlen($uppercase) - 1)]; // 1 uppercase
        $password .= $numbers[rand(0, strlen($numbers) - 1)]; // 1 number
        $password .= $numbers[rand(0, strlen($numbers) - 1)]; // 1 number
        $password .= $uppercase[rand(0, strlen($uppercase) - 1)]; // 1 uppercase
        $password .= $uppercase[rand(0, strlen($uppercase) - 1)]; // 1 uppercase
        $password .= $lowercase[rand(0, strlen($lowercase) - 1)]; // 1 lowercase
        $password .= $lowercase[rand(0, strlen($lowercase) - 1)]; // 1 lowercase
        $password .= $lowercase[rand(0, strlen($lowercase) - 1)]; // 1 lowercase

        return $password;
    }
}
