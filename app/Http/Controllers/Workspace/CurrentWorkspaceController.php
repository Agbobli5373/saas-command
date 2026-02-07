<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\SwitchWorkspaceRequest;
use Illuminate\Http\RedirectResponse;

class CurrentWorkspaceController extends Controller
{
    /**
     * Update the user's current workspace.
     */
    public function update(SwitchWorkspaceRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'current_workspace_id' => (int) $request->validated('workspace_id'),
        ])->save();

        return back()->with('status', 'Workspace switched.');
    }
}
