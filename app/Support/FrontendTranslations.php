<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class FrontendTranslations
{
    /**
     * The default locale for fallback translations.
     */
    public const DefaultLocale = 'en';

    /**
     * @var array<int, string>
     */
    private const SupportedLocales = [
        'en',
        'de',
    ];

    /**
     * @return array<int, string>
     */
    public static function supportedLocaleCodes(): array
    {
        return self::SupportedLocales;
    }

    public static function isSupportedLocale(?string $locale): bool
    {
        if (! is_string($locale)) {
            return false;
        }

        return in_array(strtolower(trim($locale)), self::SupportedLocales, true);
    }

    public static function normalizeLocale(?string $locale): string
    {
        if (! is_string($locale)) {
            return self::DefaultLocale;
        }

        $normalizedLocale = strtolower(trim($locale));

        if (! self::isSupportedLocale($normalizedLocale)) {
            return self::DefaultLocale;
        }

        return $normalizedLocale;
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    public static function supportedLocales(): array
    {
        return [
            [
                'code' => 'en',
                'label' => 'English',
            ],
            [
                'code' => 'de',
                'label' => 'Deutsch',
            ],
        ];
    }

    public static function localeFromAcceptLanguage(?string $headerValue): ?string
    {
        if (! is_string($headerValue) || trim($headerValue) === '') {
            return null;
        }

        $bestLocale = null;
        $bestQuality = -1.0;

        foreach (explode(',', strtolower($headerValue)) as $segment) {
            $segments = explode(';', trim($segment));
            $locale = trim((string) ($segments[0] ?? ''));

            if ($locale === '') {
                continue;
            }

            $quality = 1.0;

            foreach (array_slice($segments, 1) as $qualitySegment) {
                $qualitySegment = trim($qualitySegment);

                if (! str_starts_with($qualitySegment, 'q=')) {
                    continue;
                }

                $value = (float) trim(substr($qualitySegment, 2));

                if ($value >= 0.0 && $value <= 1.0) {
                    $quality = $value;
                }

                break;
            }

            $parts = explode('-', $locale);
            $baseLocale = trim($parts[0] ?? '');

            if (! self::isSupportedLocale($baseLocale) || $quality <= 0) {
                continue;
            }

            if ($quality > $bestQuality) {
                $bestLocale = $baseLocale;
                $bestQuality = $quality;
            }
        }

        return $bestLocale;
    }

    /**
     * @return array<string, string>
     */
    public static function forLocale(string $locale): array
    {
        $normalizedLocale = self::normalizeLocale($locale);
        $defaultTranslations = self::readJsonTranslations(self::DefaultLocale);

        if ($normalizedLocale === self::DefaultLocale) {
            return $defaultTranslations;
        }

        return array_replace(
            $defaultTranslations,
            self::readJsonTranslations($normalizedLocale),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function readJsonTranslations(string $locale): array
    {
        $path = base_path(sprintf('lang/%s.json', $locale));

        if (! File::exists($path)) {
            return [];
        }

        $contents = File::get($path);
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return [];
        }

        $translations = [];

        foreach ($decoded as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                continue;
            }

            $translations[$key] = $value;
        }

        return $translations;
    }
}
