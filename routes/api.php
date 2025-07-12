<?php

use App\Http\Controllers\NameGeneratorController;
use App\Http\Controllers\PasswordGeneratorController;
use Illuminate\Support\Facades\Route;

// generate name routes
Route::post('/names/generate', [NameGeneratorController::class, 'generateName']);

// generate password routes
Route::post('/passwords/generate', [PasswordGeneratorController::class, 'generatePassword']);

// generate birthday routes
Route::post('/birthdays/generate', [\App\Http\Controllers\BirthdayController::class, 'generateBirthday']);


// generate passport routes
Route::post('/passports/generate', [\App\Http\Controllers\PassportController::class, 'generatePassport']);

// generate passport date routes
Route::post('/passports/generate/data', [\App\Http\Controllers\PassportController::class, 'generatePassportDate']);