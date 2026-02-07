<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeWebhookEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'stripe_event_id',
        'event_type',
        'status',
        'message',
        'payload',
        'handled_by_cashier_at',
        'processed_at',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'handled_by_cashier_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
