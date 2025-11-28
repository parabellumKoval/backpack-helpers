<?php

namespace Backpack\Helpers\Traits;

use function e;

trait FormatsUniqAttribute
{
    protected function formatUniqString(array $parts): string
    {
        $normalized = $this->normalizeUniqParts($parts);

        return $normalized === []
            ? sprintf('#%s', (string) $this->getKey())
            : implode(', ', $normalized);
    }

    protected function formatUniqHtml(string $headline, array $details = []): string
    {
        $headline = trim($headline);
        if ($headline === '') {
            $headline = sprintf('#%s', (string) $this->getKey());
        }

        $details = $this->normalizeUniqParts($details);

        $html = '<strong>'.e($headline).'</strong>';

        if ($details !== []) {
            $html .= '<br><span class="text-muted">'.e(implode(' | ', $details)).'</span>';
        }

        return '<div>' . $html . '</div>';
    }

    /**
     * @param  array<int, mixed>  $parts
     * @return array<int, string>
     */
    protected function normalizeUniqParts(array $parts): array
    {
        return array_values(array_filter(array_map(function ($value) {
            if ($value === null) {
                return null;
            }

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i');
            } elseif ($value instanceof \Stringable) {
                $value = (string) $value;
            } elseif (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $value = $value ? 'yes' : 'no';
            } elseif (!is_string($value) && !is_numeric($value)) {
                return null;
            }

            $value = trim((string) $value);

            return $value === '' ? null : $value;
        }, $parts), static fn (?string $value) => $value !== null));
    }
}
