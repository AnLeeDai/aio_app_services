<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Passport extends Model
{
    use HasFactory;

    protected $table = 'passports';

    protected $fillable = [
        'iso_code',
        'passport_number',
        'date_issued',
        'date_expiry',
    ];

    protected $casts = [
        'date_issued' => 'date',
        'date_expiry' => 'date',
    ];

    public const UPDATED_AT = null;
}