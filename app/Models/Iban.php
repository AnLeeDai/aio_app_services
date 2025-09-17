<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Iban extends Model
{
    use HasFactory;

    protected $table = 'ibans';

    protected $fillable = [
        'iban',
        'bank_name',
    ];

    public const UPDATED_AT = null;
}