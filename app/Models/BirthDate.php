<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BirthDate extends Model
{
    use HasFactory;

    protected $table = 'birth_dates';

    protected $fillable = [
        'date_of_birth',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public const UPDATED_AT = null;
}