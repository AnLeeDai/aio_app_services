<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Password extends Model
{
    use HasFactory;

    protected $table = 'passwords';

    protected $fillable = [
        'plain_password',
    ];

    protected $hidden = [
        'plain_password',
    ];

    public const UPDATED_AT = null;
}