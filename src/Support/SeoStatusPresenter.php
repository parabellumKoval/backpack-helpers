<?php

namespace Backpack\Helpers\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SeoStatusPresenter
{
    /**
     * Prepare a normalized representation of SEO locales & properties.
     */
    public static function prepare($entry, array $column): array
    {
        $defaultLocale = $column['locale'] ?? app()->getLocale();
        $fieldName = $column['seo_field'] ?? $column['attribute'] ?? $column['name'] ?? 'seo';
        $properties = self::normalizeProperties((array) ($column['properties'] ?? [
            'meta_title' => 'Meta Title',
            'meta_description' => 'Meta Description',
            'h1' => 'H1',
        ]));

        $emptyText = $column['empty_text'] ?? __('Не заполнено');
        $availableLocales = self::resolveLocales($column['locales'] ?? null, $defaultLocale);
        $totalLocales = max(count($availableLocales), 1);
        $seoPerLocale = self::collectSeoPerLocale($entry, $fieldName, $availableLocales, $defaultLocale);
        $compactLabels = (array) ($column['compact_labels'] ?? []);

        $rows = [];
        foreach ($properties as $property => $label) {
            $rows[] = self::buildRow(
                $property,
                $label,
                $seoPerLocale,
                $emptyText,
                $totalLocales,
                $availableLocales,
                $compactLabels
            );
        }

        return [
            'has_rows' => $rows !== [],
            'rows' => $rows,
            'empty_text' => $emptyText,
            'total_locales' => $totalLocales,
        ];
    }

    protected static function normalizeProperties(array $properties): array
    {
        $labels = [];

        if (Arr::isAssoc($properties)) {
            foreach ($properties as $key => $label) {
                $key = (string) $key;
                if ($key === '') {
                    continue;
                }
                $labels[$key] = (string) $label;
            }

            return $labels;
        }

        foreach ($properties as $value) {
            $key = (string) $value;
            if ($key === '') {
                continue;
            }

            $labels[$key] = ucfirst(str_replace(['_', '-'], ' ', $key));
        }

        return $labels;
    }

    protected static function resolveLocales($configured, ?string $defaultLocale): array
    {
        $configured = (array) ($configured ?? config('backpack.crud.locales', []));
        $locales = Arr::isAssoc($configured)
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

    protected static function collectSeoPerLocale($entry, ?string $fieldName, array $locales, ?string $fallbackLocale): array
    {
        $seoPerLocale = [];
        if ($fieldName === null) {
            return $seoPerLocale;
        }

        $translations = [];

        if (
            method_exists($entry, 'isTranslatableAttribute')
            && method_exists($entry, 'getTranslations')
            && $entry->isTranslatableAttribute($fieldName)
        ) {
            $translations = (array) $entry->getTranslations($fieldName);
        } else {
            $rawValue = data_get($entry, $fieldName);
            foreach ($locales as $locale) {
                $translations[$locale] = $rawValue;
            }
        }

        foreach ($locales as $locale) {
            $value = $translations[$locale] ?? null;

            $decoded = self::decodeSeoValue($value);
            $seoPerLocale[$locale] = self::extractLocaleSlice($decoded, $locale, $locales);
        }

        return $seoPerLocale;
    }

    protected static function decodeSeoValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $value->toArray();
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_object($value)) {
            return (array) $value;
        }

        return [];
    }

    protected static function extractLocaleSlice(array $decoded, string $locale, array $allLocales): array
    {
        if ($decoded === []) {
            return [];
        }

        $decodedKeys = array_keys($decoded);
        $hasLocaleBuckets = count(array_intersect($decodedKeys, $allLocales)) > 0;

        if ($hasLocaleBuckets) {
            if (array_key_exists($locale, $decoded)) {
                $candidate = $decoded[$locale];
                return is_array($candidate) ? $candidate : self::decodeSeoValue($candidate);
            }

            return [];
        }

        return $decoded;
    }

    protected static function buildRow(
        string $property,
        string $label,
        array $seoPerLocale,
        string $emptyText,
        int $totalLocales,
        array $availableLocales,
        array $compactLabels
    ): array {
        $localeBadges = [];
        $filledLocales = 0;

        foreach ($seoPerLocale as $localeCode => $values) {
            $value = data_get($values, $property);
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $stringValue = is_scalar($value) ? trim((string) $value) : '';
            $isFilled = $stringValue !== '';

            if ($isFilled) {
                $filledLocales++;
            }

            $localeBadges[] = [
                'code' => self::formatLocaleCode($localeCode),
                'filled' => $isFilled,
            ];
        }

        $summary = $filledLocales === 0
            ? $emptyText
            : sprintf('%d из %d заполнено', $filledLocales, $totalLocales);

        $summaryClass = $filledLocales === 0 ? 'text-warning' : 'text-muted';
        $containerClass = ($filledLocales === $totalLocales && $totalLocales > 0)
            ? 'bg-success text-white border border-success'
            : 'bg-light text-dark border border-light';

        $progressPercent = $totalLocales > 0
            ? round(($filledLocales / $totalLocales) * 100)
            : 0;

        return [
            'property' => $property,
            'label' => $label,
            'summary' => $summary,
            'summary_class' => $summaryClass,
            'container_class' => $containerClass,
            'progress_percent' => $progressPercent,
            'badges' => $localeBadges,
            'filled_locales' => $filledLocales,
            'total_locales' => $totalLocales,
            'short_label' => self::shortLabel($property, $label, $compactLabels),
        ];
    }

    protected static function formatLocaleCode(string $value): string
    {
        $value = (string) $value;

        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($value);
        }

        return strtoupper($value);
    }

    protected static function shortLabel(string $property, string $label, array $overrides): string
    {
        if (array_key_exists($property, $overrides)) {
            return (string) $overrides[$property];
        }

        $cleanLabel = trim($label);
        $words = preg_split('/[\s\-]+/u', $cleanLabel, -1, PREG_SPLIT_NO_EMPTY);

        if ($words && count($words) > 1) {
            $letters = array_map(function ($word) {
                $word = trim($word);
                if ($word === '') {
                    return '';
                }

                if (function_exists('mb_substr')) {
                    return Str::upper(mb_substr($word, 0, 1));
                }

                return strtoupper(substr($word, 0, 1));
            }, array_slice($words, 0, 3));

            $joined = implode('', array_filter($letters));

            if ($joined !== '') {
                return $joined;
            }
        }

        if (function_exists('mb_substr')) {
            return Str::upper(mb_substr($cleanLabel, 0, 3));
        }

        return strtoupper(substr($cleanLabel, 0, 3));
    }
}
