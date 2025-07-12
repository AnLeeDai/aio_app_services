<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NameGenerator extends Model
{
    protected $table = "names";

    protected $fillable = ['full_name'];

    public const UPDATED_AT = null;
}
