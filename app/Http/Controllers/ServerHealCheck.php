<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class ServerHealCheck extends Controller
{
  public function index()
  {
    echo "API IS ALIVE";
  }
}