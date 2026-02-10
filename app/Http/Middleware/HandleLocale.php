<?php

namespace App\Http\Middleware;

use App\Support\FrontendTranslations;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class HandleLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $userLocale = $request->user()?->locale;

        if (FrontendTranslations::isSupportedLocale($userLocale)) {
            return FrontendTranslations::normalizeLocale($userLocale);
        }

        $sessionLocale = $request->session()->get('locale');

        if (FrontendTranslations::isSupportedLocale($sessionLocale)) {
            return FrontendTranslations::normalizeLocale((string) $sessionLocale);
        }

        $cookieLocale = $request->cookie('locale');

        if (FrontendTranslations::isSupportedLocale($cookieLocale)) {
            return FrontendTranslations::normalizeLocale((string) $cookieLocale);
        }

        $localeFromHeader = FrontendTranslations::localeFromAcceptLanguage(
            $request->header('Accept-Language'),
        );

        if ($localeFromHeader !== null) {
            return $localeFromHeader;
        }

        return FrontendTranslations::normalizeLocale((string) config('app.locale'));
    }
}
