<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use BelongsToOutlet;

    protected $guarded = [];
}
