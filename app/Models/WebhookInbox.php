<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Model;

class WebhookInbox extends Model
{
    use BelongsToOutlet;

    protected $table = 'webhook_inbox';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
