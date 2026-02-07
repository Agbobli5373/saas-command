<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceUsageCounter extends Model
{
    /** @use HasFactory<\Database\Factories\WorkspaceUsageCounterFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'metric_key',
        'period_start',
        'used',
        'quota',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used' => 'integer',
            'quota' => 'integer',
        ];
    }

    /**
     * Get the workspace this usage counter belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
