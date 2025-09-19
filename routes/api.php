<?php

use App\Http\Controllers\BirthdayController;
use App\Http\Controllers\EmailEbayController;
use App\Http\Controllers\IbanController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NameController;
use App\Http\Controllers\PassportController;
use App\Http\Controllers\PassportMrzController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ServerHealthCheckController;
use App\Http\Controllers\PhoneNumberController;
use Illuminate\Support\Facades\Route;

// Health check route
Route::get('/health-check', [ServerHealthCheckController::class, 'index']);

// Generator routes
Route::prefix('generate')->group(function () {
    // Name generation
    Route::post('/names', [NameController::class, 'generateName']);

    // Password generation
    Route::post('/passwords', [PasswordController::class, 'generatePassword']);

    // Birthday generation
    Route::post('/birthdays', [BirthdayController::class, 'generateBirthday']);

    // Passport generation
    Route::post('/passports', [PassportController::class, 'generatePassport']);
    Route::post('/passports/dates', [PassportController::class, 'generatePassportDate']);
    Route::post('/passports/mrz', [PassportMrzController::class, 'generate']);

    // IBAN generation
    Route::post('/ibans', [IbanController::class, 'generateIban']);

    // Location generation
    Route::post('/locations', [LocationController::class, 'generateAddresses']);

    // Email generation
    Route::post('/emails/ebay', [EmailEbayController::class, 'generateEmail']);

    // Phone number generation
    Route::post('/phones', [PhoneNumberController::class, 'generatePhoneNumber']);
});
