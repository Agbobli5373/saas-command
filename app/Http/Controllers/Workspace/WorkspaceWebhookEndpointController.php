<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\DestroyWorkspaceWebhookEndpointRequest;
use App\Http\Requests\Workspace\StoreWorkspaceWebhookEndpointRequest;
use App\Models\WorkspaceWebhookEndpoint;
use Illuminate\Http\RedirectResponse;

class WorkspaceWebhookEndpointController extends Controller
{
    /**
     * Create a new outbound webhook endpoint for the active workspace.
     */
    public function store(StoreWorkspaceWebhookEndpointRequest $request): RedirectResponse
    {
        $workspace = $request->user()->activeWorkspace();

        if ($workspace === null) {
            return to_route('workspace')->with('status', __('No active workspace selected.'));
        }

        $workspace->webhookEndpoints()->create([
            'name' => (string) $request->validated('name'),
            'url' => (string) $request->validated('url'),
            'signing_secret' => (string) $request->validated('signing_secret'),
            'events' => collect($request->validated('events'))
                ->filter(static fn (mixed $event): bool => is_string($event) && $event !== '')
                ->unique()
                ->values()
                ->all(),
            'is_active' => true,
        ]);

        return to_route('workspace')->with('status', __('Webhook endpoint created.'));
    }

    /**
     * Disable an existing outbound webhook endpoint.
     */
    public function destroy(
        DestroyWorkspaceWebhookEndpointRequest $request,
        WorkspaceWebhookEndpoint $endpoint
    ): RedirectResponse {
        $workspace = $request->user()->activeWorkspace();

        if ($workspace === null) {
            return to_route('workspace')->with('status', __('No active workspace selected.'));
        }

        if ($endpoint->workspace_id !== $workspace->id) {
            abort(404);
        }

        $endpoint->forceFill([
            'is_active' => false,
        ])->save();

        return to_route('workspace')->with('status', __('Webhook endpoint disabled.'));
    }
}
