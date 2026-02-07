<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Operations\ReadinessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    /**
     * Show the production readiness dashboard.
     */
    public function show(Request $request, ReadinessService $readiness): Response
    {
        $workspace = $request->user()->activeWorkspace();
        abort_if($workspace === null, 403);

        $this->authorize('manageBilling', $workspace);

        $snapshot = $readiness->snapshot($workspace);

        return Inertia::render('settings/operations', [
            'status' => $request->session()->get('status'),
            'workspaceName' => $workspace->name,
            ...$snapshot,
        ]);
    }
}
