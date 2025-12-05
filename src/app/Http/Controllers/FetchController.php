<?php

namespace Backpack\Helpers\app\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FetchController extends Controller
{
    public function __invoke(Request $request, string $key)
    {
        $config = config("helpers.fetchables.$key");

        abort_unless($config, 404);

        $modelClass = Arr::get($config, 'model');

        abort_unless($modelClass && class_exists($modelClass), 404);

        $modelInstance = new $modelClass();
        $perPage = (int) Arr::get($config, 'paginate', config('helpers.fetch_default_paginate', 50));
        $with = Arr::wrap(Arr::get($config, 'with', []));
        $columns = Arr::wrap(Arr::get($config, 'columns', []));
        $relationColumns = Arr::get($config, 'relation_columns', []);
        $relationColumns = is_array($relationColumns) ? $relationColumns : [];
        $relationColumns = collect($relationColumns)
            ->map(fn ($value) => Arr::wrap($value))
            ->all();
        $searchId = Arr::get($config, 'search_id', true);
        $idPrefixes = Arr::wrap(Arr::get($config, 'id_prefixes', ['#']));
        $keyColumn = Arr::get($config, 'key_column');
        $keyColumn = $keyColumn ? (string) $keyColumn : null;
        $keyColumn = $keyColumn ?: $modelInstance->getKeyName();

        if (empty($columns)) {
            $columns = [$keyColumn];
        }

        if (! empty($relationColumns)) {
            $with = array_values(array_unique(array_merge($with, array_keys($relationColumns))));
        }

        $query = $modelClass::query();

        if (! empty($with)) {
            $query->with($with);
        }

        if ($queryCallback = Arr::get($config, 'query')) {
            if (is_callable($queryCallback)) {
                $query = $queryCallback($query) ?? $query;
            }
        }

        if ($request->has('keys')) {
            $keys = Arr::wrap($request->input('keys'));

            $results = $modelClass::query()
                ->when(! empty($with), fn ($builder) => $builder->with($with))
                ->whereIn($keyColumn, $keys)
                ->get();

            return $this->formatResponse($results);
        }

        $searchString = $request->input('q', false);


        if ($searchString === false) {
            return $this->formatResponse(
                $query->paginate($perPage)
            );
        }

        $searchString = (string) $searchString;

        $this->applySearch(
            $query,
            $columns,
            $relationColumns,
            $searchString,
            $searchId,
            $idPrefixes,
            $keyColumn
        );

        return $this->formatResponse(
            $query->paginate($perPage)
        );
    }

    protected function applySearch(
        Builder $query,
        array $columns,
        array $relationColumns,
        string $searchString,
        bool $searchId,
        array $idPrefixes,
        string $keyColumn
    ): void {
        $query->where(function (Builder $builder) use ($columns, $relationColumns, $searchString, $searchId, $idPrefixes, $keyColumn) {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $builder->{$method}($column, 'LIKE', '%'.$searchString.'%');
            }

            if ($searchId) {
                $idCandidate = $this->normalizeIdSearch($searchString, $idPrefixes);

                if (is_numeric($idCandidate)) {
                    $builder->orWhere($keyColumn, $idCandidate);
                }
            }

            foreach ($relationColumns as $relation => $relationFields) {
                $builder->orWhereHas($relation, function (Builder $relationQuery) use ($relationFields, $searchString) {
                    foreach ($relationFields as $index => $relationColumn) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $relationQuery->{$method}($relationColumn, 'LIKE', '%'.$searchString.'%');
                    }
                });
            }
        });
    }

    protected function normalizeIdSearch(string $searchString, array $prefixes): string
    {
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && Str::startsWith($searchString, $prefix)) {
                return Str::after($searchString, $prefix);
            }
        }

        return $searchString;
    }

    protected function formatResponse($response)
    {
        if ($response instanceof LengthAwarePaginator || $response instanceof Paginator) {
            return $response->setCollection(
                $response->getCollection()->map(fn ($model) => $this->modelToArrayWithUniq($model))
            );
        }

        if ($response instanceof Collection) {
            return $response->map(fn ($model) => $this->modelToArrayWithUniq($model));
        }

        if ($response instanceof Model) {
            return $this->modelToArrayWithUniq($response);
        }

        return $response;
    }

    protected function modelToArrayWithUniq(Model $model): array
    {
        $this->ensureUniqAttributes($model);

        $payload = $model->toArray();
        $payload['uniqString'] = $model->getAttribute('uniqString');
        $payload['uniqHtml'] = $model->getAttribute('uniqHtml');

        return $payload;
    }

    protected function ensureUniqAttributes(Model $model): Model
    {
        $uniqString = $model->getAttribute('uniqString');


        if ($uniqString === null || $uniqString === '') {
            $uniqString = '#'.$model->getKey();
        }

        $model->setAttribute('uniqString', $uniqString);

        $uniqHtml = $model->getAttribute('uniqHtml');

        if ($uniqHtml === null || $uniqHtml === '') {
            $uniqHtml = $uniqString;
        }

        $model->setAttribute('uniqHtml', $uniqHtml);

        return $model;
    }
}
