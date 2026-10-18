<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Model;

class FormToken extends Model
{
    use BelongsToOutlet;

    public const UPDATED_AT = null;

    protected $fillable = ['token', 'action', 'result_url'];
}
