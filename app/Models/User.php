<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'current_workspace_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Boot model event hooks.
     */
    protected static function booted(): void
    {
        static::created(function (self $user): void {
            $user->provisionPersonalWorkspace();
        });
    }

    /**
     * Get the workspaces owned by the user.
     */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * Get all workspaces this user belongs to.
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the user's active workspace.
     */
    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    /**
     * Resolve the user's active workspace.
     */
    public function activeWorkspace(): ?Workspace
    {
        $workspace = $this->currentWorkspace()->first();

        if ($workspace !== null) {
            return $workspace;
        }

        $workspace = $this->workspaces()
            ->orderBy('workspaces.id')
            ->first();

        if ($workspace !== null) {
            $this->forceFill([
                'current_workspace_id' => $workspace->id,
            ])->save();
        }

        return $workspace;
    }

    /**
     * Switch the user's active workspace.
     */
    public function switchWorkspace(Workspace $workspace): bool
    {
        $belongsToWorkspace = $this->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->exists();

        if (! $belongsToWorkspace) {
            return false;
        }

        $this->forceFill([
            'current_workspace_id' => $workspace->id,
        ])->save();

        return true;
    }

    /**
     * Create the default personal workspace for the user.
     */
    public function provisionPersonalWorkspace(): Workspace
    {
        $existingWorkspace = $this->ownedWorkspaces()
            ->where('is_personal', true)
            ->first();

        if ($existingWorkspace !== null) {
            if ($this->current_workspace_id === null) {
                $this->forceFill([
                    'current_workspace_id' => $existingWorkspace->id,
                ])->saveQuietly();
            }

            return $existingWorkspace;
        }

        $workspace = $this->ownedWorkspaces()->create([
            'name' => sprintf('%s Workspace', $this->name),
            'is_personal' => true,
        ]);

        $workspace->addMember($this, WorkspaceRole::Owner);

        $this->forceFill([
            'current_workspace_id' => $workspace->id,
        ])->saveQuietly();

        return $workspace;
    }
}
