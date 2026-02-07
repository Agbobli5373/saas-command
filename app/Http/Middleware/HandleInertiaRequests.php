<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $workspaces = $this->workspaceOptions($user);
        $currentWorkspace = $this->currentWorkspace($user, $workspaces);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'workspaces' => $workspaces,
                'current_workspace' => $currentWorkspace,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @param  array<int, array{id: int, name: string, owner_id: int, is_personal: bool, role: string}>  $workspaces
     * @return array{id: int, name: string, owner_id: int, is_personal: bool, role: string}|null
     */
    private function currentWorkspace(?User $user, array $workspaces): ?array
    {
        if ($user === null) {
            return null;
        }

        if ($user->current_workspace_id !== null) {
            foreach ($workspaces as $workspace) {
                if ($workspace['id'] === $user->current_workspace_id) {
                    return $workspace;
                }
            }
        }

        return $workspaces[0] ?? null;
    }

    /**
     * @return array<int, array{id: int, name: string, owner_id: int, is_personal: bool, role: string}>
     */
    private function workspaceOptions(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return $user->workspaces()
            ->select([
                'workspaces.id',
                'workspaces.name',
                'workspaces.owner_id',
                'workspaces.is_personal',
            ])
            ->orderBy('workspaces.name')
            ->get()
            ->map(fn ($workspace): array => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'owner_id' => $workspace->owner_id,
                'is_personal' => (bool) $workspace->is_personal,
                'role' => (string) ($workspace->pivot->role ?? ''),
            ])
            ->values()
            ->all();
    }
}
