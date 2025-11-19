<?php

namespace Backpack\Helpers\Traits\Admin;

use Illuminate\Database\Eloquent\Builder;

trait HasSeoFilters
{
    /**
     * Add a reusable "SEO filled" filter to the CRUD panel.
     */
    protected function addSeoFilledFilter(array $config = []): void
    {
        if (!isset($this->crud)) {
            return;
        }

        $field = $config['field'] ?? 'seo';
        $properties = $this->normalizeSeoProperties($config['properties'] ?? ['meta_title', 'meta_description', 'h1']);

        if ($properties === []) {
            return;
        }

        $options = $this->normalizeSeoFilterOptions($config);

        $filterDefinition = [
            'name' => $config['name'] ?? 'seo_filled',
            'label' => $config['label'] ?? 'SEO',
            'type' => $config['type'] ?? 'select2',
        ];

        $choices = $options['choices'];
        $emptyValue = (string) $options['empty_value'];
        $filledValue = (string) $options['filled_value'];
        $locale = $config['locale'] ?? app()->getLocale();

        $this->crud->addFilter($filterDefinition, function () use ($choices) {
            return $choices;
        }, function ($value) use ($field, $locale, $properties, $emptyValue, $filledValue) {
            $selected = (string) $value;

            if ($selected === $emptyValue) {
                $this->applySeoEmptyClause($field, $locale, $properties);
                return;
            }

            if ($selected === $filledValue) {
                $this->applySeoFilledClause($field, $locale, $properties);
            }
        });
    }

    /**
     * Make sure properties are a flat list of strings.
     */
    protected function normalizeSeoProperties($properties): array
    {
        $normalized = [];

        foreach ((array) $properties as $key => $value) {
            if (is_string($key) && !is_numeric($key)) {
                $normalized[] = $key;
            } else {
                $normalized[] = (string) $value;
            }
        }

        $unique = array_values(array_unique($normalized));

        return array_values(array_filter($unique, fn ($value) => $value !== ''));
    }

    /**
     * Prepare filter options and remember the values that map to empty/filled states.
     */
    protected function normalizeSeoFilterOptions(array $config): array
    {
        $emptyValue = array_key_exists('empty_value', $config) ? (string) $config['empty_value'] : 'empty';
        $filledValue = array_key_exists('filled_value', $config) ? (string) $config['filled_value'] : 'filled';

        $choices = (array) ($config['options'] ?? [
            $emptyValue => 'Не заполнено',
            $filledValue => 'Заполнено',
        ]);

        if (!array_key_exists($emptyValue, $choices)) {
            $choices[$emptyValue] = 'Не заполнено';
        }

        if (!array_key_exists($filledValue, $choices)) {
            $choices[$filledValue] = 'Заполнено';
        }

        return [
            'choices' => $choices,
            'empty_value' => $emptyValue,
            'filled_value' => $filledValue,
        ];
    }

    /**
     * Filter entries that have no SEO data for all provided properties.
     */
    protected function applySeoEmptyClause(string $field, string $locale, array $properties): void
    {
        $this->crud->query->where(function (Builder $query) use ($field, $locale, $properties) {
            $query->whereNull($field)
                ->orWhere(function (Builder $nested) use ($field, $locale, $properties) {
                    foreach ($properties as $property) {
                        $column = $this->buildSeoJsonColumn($field, $locale, $property);

                        $nested->where(function (Builder $inner) use ($column) {
                            $inner->whereNull($column)
                                ->orWhere($column, '=', '');
                        });
                    }
                });
        });
    }

    /**
     * Filter entries that have at least one SEO property filled.
     */
    protected function applySeoFilledClause(string $field, string $locale, array $properties): void
    {
        $this->crud->query->where(function (Builder $query) use ($field, $locale, $properties) {
            $first = true;

            foreach ($properties as $property) {
                $column = $this->buildSeoJsonColumn($field, $locale, $property);
                $method = $first ? 'where' : 'orWhere';

                $query->{$method}(function (Builder $inner) use ($column) {
                    $inner->whereNotNull($column)
                        ->where($column, '!=', '');
                });

                $first = false;
            }
        });
    }

    protected function buildSeoJsonColumn(string $field, string $locale, string $property): string
    {
        $localePath = $locale !== '' ? "->{$locale}" : '';

        return "{$field}{$localePath}->{$property}";
    }
}
