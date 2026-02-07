<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Cashier\Billable;

class Workspace extends Model
{
    /** @use HasFactory<\Database\Factories\WorkspaceFactory> */
    use Billable, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'owner_id',
        'is_personal',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the workspace.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the workspace members.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Add a user to the workspace.
     */
    public function addMember(User $user, WorkspaceRole $role = WorkspaceRole::Member): void
    {
        $this->members()->syncWithoutDetaching([
            $user->id => ['role' => $role->value],
        ]);
    }

    /**
     * Get the name that should be synced to Stripe.
     */
    public function stripeName(): ?string
    {
        return $this->name;
    }

    /**
     * Get the email address that should be synced to Stripe.
     */
    public function stripeEmail(): ?string
    {
        return $this->owner()->value('email');
    }
}
