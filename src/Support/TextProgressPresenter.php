<?php

namespace Backpack\Helpers\Support;

class TextProgressPresenter
{
    public static function prepare($entry, array $column): array
    {
        $defaultLocale = $column['locale'] ?? app()->getLocale();
        $attribute = $column['attribute'] ?? $column['name'] ?? null;
        $emptyText = $column['empty_text'] ?? __('Не заполнено');
        $availableLocales = self::resolveLocales($column['locales'] ?? null, $defaultLocale);
        $totalLocales = max(count($availableLocales), 1);
        $translations = self::collectTranslations($entry, $attribute, $availableLocales, $defaultLocale);
        $displayLocale = $column['display_locale'] ?? $defaultLocale;

        $badges = [];
        $filledLocales = 0;

        foreach ($availableLocales as $locale) {
            $value = $translations[$locale] ?? '';
            $stringValue = self::stringify($value);
            $isFilled = $stringValue !== '';

            if ($isFilled) {
                $filledLocales++;
            }

            $badges[] = [
                'code' => self::formatLocaleCode($locale),
                'filled' => $isFilled,
            ];
        }

        $displayValue = self::determineDisplayValue($translations, $displayLocale, $defaultLocale);

        $summary = $filledLocales === 0
            ? $emptyText
            : sprintf('%d из %d заполнено', $filledLocales, $totalLocales);

        $summaryClass = $filledLocales === 0 ? 'text-warning' : 'text-muted';
        $progressPercent = $totalLocales > 0
            ? round(($filledLocales / $totalLocales) * 100)
            : 0;

        return [
            'text' => $displayValue,
            'translations' => $translations,
            'summary' => $summary,
            'summary_class' => $summaryClass,
            'badges' => $badges,
            'progress_percent' => $progressPercent,
            'filled_locales' => $filledLocales,
            'total_locales' => $totalLocales,
            'empty_text' => $emptyText,
        ];
    }

    protected static function resolveLocales($configured, ?string $defaultLocale): array
    {
        $configured = (array) ($configured ?? config('backpack.crud.locales', []));
        $locales = \Illuminate\Support\Arr::isAssoc($configured)
            ? array_keys($configured)
            : array_values($configured);

        $locales = array_values(array_filter(array_unique(array_map('strval', $locales))));

        if ($locales === [] && $defaultLocale) {
            $locales[] = (string) $defaultLocale;
        }

        if ($locales === []) {
            $locales[] = app()->getLocale();
        }

        return array_values(array_filter($locales));
    }

    protected static function collectTranslations($entry, ?string $attribute, array $locales, ?string $fallbackLocale): array
    {
        $translations = [];

        if ($attribute === null) {
            return $translations;
        }

        if (
            method_exists($entry, 'isTranslatableAttribute')
            && method_exists($entry, 'getTranslations')
            && $entry->isTranslatableAttribute($attribute)
        ) {
            $translations = (array) $entry->getTranslations($attribute);
        } else {
            $value = data_get($entry, $attribute);
            foreach ($locales as $locale) {
                $translations[$locale] = $value;
            }
        }

        if (
            $translations === []
            && $fallbackLocale
            && method_exists($entry, 'isTranslatableAttribute')
            && method_exists($entry, 'getTranslation')
            && $entry->isTranslatableAttribute($attribute)
        ) {
            $fallback = $entry->getTranslation($attribute, $fallbackLocale);
            if ($fallback !== null) {
                $translations[$fallbackLocale] = $fallback;
            }
        }

        return $translations;
    }

    protected static function determineDisplayValue(array $translations, ?string $displayLocale, ?string $defaultLocale): string
    {
        $localesToCheck = array_filter([
            $displayLocale,
            $defaultLocale,
        ]);

        foreach ($localesToCheck as $locale) {
            $value = $translations[$locale] ?? null;
            $stringValue = self::stringify($value);
            if ($stringValue !== '') {
                return $stringValue;
            }
        }

        foreach ($translations as $value) {
            $stringValue = self::stringify($value);
            if ($stringValue !== '') {
                return $stringValue;
            }
        }

        return '';
    }

    protected static function stringify($value): string
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    protected static function formatLocaleCode(string $value): string
    {
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper((string) $value);
        }

        return strtoupper((string) $value);
    }
}
