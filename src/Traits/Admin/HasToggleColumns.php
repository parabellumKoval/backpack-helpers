<?php

namespace Backpack\Helpers\Traits\Admin;

use Illuminate\Http\JsonResponse;

trait HasToggleColumns
{
    /**
     * Registered toggle columns for the current controller instance.
     *
     * @var array<string, array{attribute: string, values: array{checked: mixed, unchecked: mixed}}>
     */
    protected array $toggleColumnsConfig = [];

    /**
     * Add a toggle column to the CRUD list and remember its configuration for ajax updates.
     */
    protected function addToggleColumn(array $definition): void
    {
        if (!isset($definition['name'])) {
            throw new \InvalidArgumentException('Toggle column definition must include a name.');
        }

        $columnName = $definition['name'];

        $toggleMeta = $definition['toggle'] ?? [];
        $attribute = $toggleMeta['attribute'] ?? $columnName;
        $routeSegment = $toggleMeta['route'] ?? 'toggle';
        $values = $this->normalizeToggleValues($toggleMeta['values'] ?? ($definition['values'] ?? null));

        unset($definition['values']);

        $definition['type'] = 'toggle';
        $definition['toggle'] = [
            'column' => $columnName,
            'attribute' => $attribute,
            'route' => $routeSegment,
            'values' => $values,
        ];

        $this->crud->addColumn($definition);

        $this->toggleColumnsConfig[$columnName] = [
            'attribute' => $attribute,
            'values' => $values,
        ];
    }

    /**
     * Normalize supported toggle values configuration to a unified structure.
     */
    protected function normalizeToggleValues($values): array
    {
        if (!is_array($values) || $values === []) {
            return ['checked' => 1, 'unchecked' => 0];
        }

        if (array_is_list($values)) {
            $checked = $values[0] ?? 1;
            $unchecked = $values[1] ?? 0;
        } else {
            $checked = $values['checked']
                ?? $values['on']
                ?? $values['active']
                ?? $values['true']
                ?? $values['yes']
                ?? null;

            $unchecked = $values['unchecked']
                ?? $values['off']
                ?? $values['inactive']
                ?? $values['false']
                ?? $values['no']
                ?? null;
        }

        $checked ??= 1;
        $unchecked ??= 0;

        return [
            'checked' => $checked,
            'unchecked' => $unchecked,
        ];
    }

    /**
     * Make sure toggle columns are registered before handling ajax requests.
     */
    protected function ensureToggleColumnsRegistered(): void
    {
        if (!empty($this->toggleColumnsConfig) || !method_exists($this, 'setupListOperation')) {
            return;
        }

        $this->setupListOperation();
    }

    /**
     * Generic ajax handler to toggle a field value from the list view.
     */
    public function toggleColumnRouter($id): JsonResponse
    {
        $this->crud->hasAccessOrFail('update');

        $this->ensureToggleColumnsRegistered();

        $columnName = request()->input('column');
        $submittedValue = request()->input('value');

        if (!$columnName || !array_key_exists($columnName, $this->toggleColumnsConfig)) {
            abort(404, 'Toggle column not found.');
        }

        $config = $this->toggleColumnsConfig[$columnName];
        $resolvedValue = $this->resolveToggleValue($config['values'], $submittedValue);

        $entry = $this->crud->model->findOrFail($id);
        $entry->{$config['attribute']} = $resolvedValue;
        $entry->save();

        return response()->json([
            'success' => true,
            'column' => $columnName,
            'value' => $resolvedValue,
        ]);
    }

    /**
     * Match the submitted value with one of the configured values and return the stored variant.
     */
    protected function resolveToggleValue(array $values, $submitted)
    {
        foreach ($values as $value) {
            if ((string) $submitted === (string) $value) {
                return $value;
            }
        }

        abort(422, 'Invalid toggle value.');
    }
}
