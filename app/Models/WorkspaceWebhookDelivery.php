<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceWebhookDelivery extends Model
{
    /** @use HasFactory<\Database\Factories\WorkspaceWebhookDeliveryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_webhook_endpoint_id',
        'event_type',
        'payload',
        'status',
        'attempt_count',
        'response_status_code',
        'response_body',
        'last_error_message',
        'dispatched_at',
        'last_attempted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempt_count' => 'integer',
            'response_status_code' => 'integer',
            'dispatched_at' => 'datetime',
            'last_attempted_at' => 'datetime',
        ];
    }

    /**
     * Get the endpoint that owns this delivery.
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WorkspaceWebhookEndpoint::class, 'workspace_webhook_endpoint_id');
    }
}
