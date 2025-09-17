<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API IS ALIVE',
        'data' => [
            'timestamp' => now()->toISOString()
        ]
    ]);
});
