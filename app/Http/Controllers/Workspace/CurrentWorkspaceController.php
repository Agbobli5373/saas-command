<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\SwitchWorkspaceRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;

class CurrentWorkspaceController extends Controller
{
    /**
     * Update the user's current workspace.
     */
    public function update(SwitchWorkspaceRequest $request): RedirectResponse
    {
        $workspace = Workspace::query()->findOrFail((int) $request->validated('workspace_id'));

        $this->authorize('switchTo', $workspace);

        $request->user()->switchWorkspace($workspace);

        return back()->with('status', __('Workspace switched.'));
    }
}
