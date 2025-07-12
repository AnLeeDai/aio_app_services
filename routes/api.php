<?php

use App\Http\Controllers\NameGeneratorController;
use Illuminate\Support\Facades\Route;

// generate name routes
Route::post('/names/generate', [NameGeneratorController::class, 'generateName']);
