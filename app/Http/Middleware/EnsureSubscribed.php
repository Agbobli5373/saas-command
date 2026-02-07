<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $subscription = 'default'): Response
    {
        $user = $request->user();
        $workspace = $user?->activeWorkspace();

        if ($workspace === null || (! $workspace->subscribed($subscription) && ! $workspace->onTrial($subscription))) {
            return to_route('billing.edit');
        }

        return $next($request);
    }
}
