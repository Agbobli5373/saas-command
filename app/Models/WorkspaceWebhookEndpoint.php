<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceWebhookEndpoint extends Model
{
    /** @use HasFactory<\Database\Factories\WorkspaceWebhookEndpointFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'name',
        'url',
        'signing_secret',
        'events',
        'is_active',
        'last_dispatched_at',
        'last_error_at',
        'last_error_message',
        'failure_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signing_secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'last_dispatched_at' => 'datetime',
            'last_error_at' => 'datetime',
            'failure_count' => 'integer',
        ];
    }

    /**
     * Get the workspace that owns this webhook endpoint.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get delivery attempts for this endpoint.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WorkspaceWebhookDelivery::class);
    }
}
