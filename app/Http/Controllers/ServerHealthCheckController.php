<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Routing\Controller;

class ServerHealthCheckController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $data = [
            'timestamp' => now()->toISOString()
        ];
        
        return $this->success($data, 'API IS ALIVE');
    }
}