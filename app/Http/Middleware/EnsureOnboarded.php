<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return to_route('login');
        }

        if ($user->hasCompletedOnboarding()) {
            return $next($request);
        }

        $workspace = $user->activeWorkspace();

        if ($workspace?->subscribed('default')) {
            $user->markOnboardingCompleted();

            return $next($request);
        }

        if ($workspace !== null && $user->cannot('manageBilling', $workspace)) {
            $user->markOnboardingCompleted();

            return $next($request);
        }

        if ($request->routeIs('onboarding.*')) {
            return $next($request);
        }

        if (! $request->expectsJson()) {
            return to_route('onboarding.show');
        }

        return $next($request);
    }
}
