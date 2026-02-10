<?php

namespace App\Http\Controllers;

use App\Http\Requests\Locale\UpdateLocaleRequest;
use App\Support\FrontendTranslations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;

class LocaleController extends Controller
{
    /**
     * Update the user's preferred locale.
     */
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = FrontendTranslations::normalizeLocale((string) $request->validated('locale'));
        $user = $request->user();

        if ($user !== null && $user->locale !== $locale) {
            $user->forceFill([
                'locale' => $locale,
            ])->save();
        }

        $request->session()->put('locale', $locale);
        App::setLocale($locale);

        $localeCookie = cookie()->make(
            'locale',
            $locale,
            60 * 24 * 365,
            '/',
            null,
            null,
            true,
            false,
            'lax',
        );

        return back()->withCookie($localeCookie);
    }
}
