<?php

use App\Http\Controllers\LocationController;
use App\Http\Controllers\NameGeneratorController;
use App\Http\Controllers\PasswordGeneratorController;
use App\Http\Controllers\BirthdayController;
use App\Http\Controllers\PassportController;
use App\Http\Controllers\IbanController;
use App\Http\Controllers\ServerHealCheck;
use Illuminate\Support\Facades\Route;

Route::get('/heal-check', [ServerHealCheck::class, 'index']);

// generate name routes
Route::post('/names/generate', [NameGeneratorController::class, 'generateName']);

// generate password routes
Route::post('/passwords/generate', [PasswordGeneratorController::class, 'generatePassword']);

// generate birthday routes
Route::post('/birthdays/generate', [BirthdayController::class, 'generateBirthday']);


// generate passport routes
Route::post('/passports/generate', [PassportController::class, 'generatePassport']);

// generate passport date routes
Route::post('/passports/generate/date', [PassportController::class, 'generatePassportDate']);

// generate iban routes
Route::post('/ibans/generate', [IbanController::class, 'generateIban']);

// generate location routes
Route::post('/locations/generate', [LocationController::class, 'generateAddresses']);

// warm location
Route::post('/locations/warm', [LocationController::class, 'warmUp']);