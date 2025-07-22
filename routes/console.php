<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Schedule::call(function () {
//     Http::post(config('app.url') . '/api/locations/warm', [
//         'limit' => 100,
//     ]);
// })
//     ->name('warm_brazil_cache')
//     ->everyFifteenMinutes()
//     ->withoutOverlapping(14)
//     ->onOneServer();