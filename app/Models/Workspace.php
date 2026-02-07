<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;
use Throwable;

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
     * Get the invitations for the workspace.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    /**
     * Get billing audit events for the workspace.
     */
    public function billingAuditEvents(): HasMany
    {
        return $this->hasMany(BillingAuditEvent::class);
    }

    /**
     * Get monthly usage counters for this workspace.
     */
    public function usageCounters(): HasMany
    {
        return $this->hasMany(WorkspaceUsageCounter::class);
    }

    /**
     * Get usage events for this workspace.
     */
    public function usageEvents(): HasMany
    {
        return $this->hasMany(WorkspaceUsageEvent::class);
    }

    /**
     * Get outbound webhook endpoints for this workspace.
     */
    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WorkspaceWebhookEndpoint::class);
    }

    /**
     * Get the count of pending invitations.
     */
    public function pendingInvitationCount(): int
    {
        return $this->invitations()
            ->pending()
            ->count();
    }

    /**
     * Add a user to the workspace.
     */
    public function addMember(User $user, WorkspaceRole $role = WorkspaceRole::Member): void
    {
        $this->members()->syncWithoutDetaching([
            $user->id => ['role' => $role->value],
        ]);

        $this->syncSeatQuantity();
    }

    /**
     * Remove a member from the workspace.
     */
    public function removeMember(User $user): void
    {
        if ($user->id === $this->owner_id) {
            return;
        }

        $this->members()->detach($user->id);
        $this->syncSeatQuantity();
    }

    /**
     * Update a member's role in the workspace.
     */
    public function updateMemberRole(User $user, WorkspaceRole $role): void
    {
        if ($user->id === $this->owner_id) {
            return;
        }

        $this->members()->updateExistingPivot($user->id, [
            'role' => $role->value,
        ]);
    }

    /**
     * Transfer workspace ownership to another member.
     */
    public function transferOwnershipTo(User $user): bool
    {
        if ($user->id === $this->owner_id) {
            return false;
        }

        $memberExists = $this->members()
            ->where('users.id', $user->id)
            ->exists();

        if (! $memberExists) {
            return false;
        }

        $this->members()->updateExistingPivot($this->owner_id, [
            'role' => WorkspaceRole::Admin->value,
        ]);

        $this->members()->updateExistingPivot($user->id, [
            'role' => WorkspaceRole::Owner->value,
        ]);

        $this->forceFill([
            'owner_id' => $user->id,
        ])->save();

        return true;
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

    /**
     * Get the number of active seats in this workspace.
     */
    public function seatCount(): int
    {
        return $this->members()->count();
    }

    /**
     * Get the minimum billable seat quantity.
     */
    public function billableSeatQuantity(): int
    {
        return max(1, $this->seatCount());
    }

    /**
     * Sync subscription quantity with current seat usage.
     */
    public function syncSeatQuantity(string $subscriptionType = 'default'): void
    {
        $subscription = $this->subscriptions()
            ->where('type', $subscriptionType)
            ->first();

        if ($subscription === null) {
            return;
        }

        $desiredQuantity = $this->billableSeatQuantity();
        $currentQuantity = max(1, (int) ($subscription->quantity ?? 1));

        if ($currentQuantity === $desiredQuantity) {
            return;
        }

        $subscription->forceFill([
            'quantity' => $desiredQuantity,
        ])->save();

        if (! $this->shouldSyncSeatQuantityWithStripe()) {
            return;
        }

        try {
            $subscription->noProrate()->updateQuantity($desiredQuantity);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Determine if seat quantity should sync to Stripe immediately.
     */
    private function shouldSyncSeatQuantityWithStripe(): bool
    {
        return (bool) config('services.stripe.seat_quantity.sync_with_stripe', false)
            && $this->hasStripeId();
    }
}
