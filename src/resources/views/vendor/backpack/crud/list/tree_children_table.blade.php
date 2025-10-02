@php
    /** @var \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud */
    // Берём ТЕ ЖЕ колонки, что и в list
    $all = $crud->columns();


    // Отфильтруем "bulk checkbox" колонку, которую добавляет BulkDeleteOperation.
    $columns = collect($all)->reject(function ($col) {
        $view = $col['view']  ?? '';
        $name = $col['name']  ?? '';
        $type = $col['type']  ?? '';
        $key  = $col['key']   ?? '';

        if ($key === 'bulk_actions') return true;
        if ($key === 'blank_first_column') return true;

        return false;
    })->values();


    \Log::info(print_r($columns, true));

    // Получим детей — как и раньше
    $model = $crud->getModel();
    $table = $model->getTable();

    if (method_exists($entry, 'descendants')) {
        $childrenQuery = $entry->descendants();
    } else {
        $childrenQuery = $model->newQuery()->where('parent_id', $entry->getKey());
    }

    if (\Schema::hasColumn($table, 'lft')) {
        $childrenQuery->orderBy('lft');
    }

    $children = $childrenQuery->get();
    $line_buttons = $crud->buttons()->where('stack', 'line');

@endphp

@if($children->isEmpty())
    <div class="p-2 text-muted">Нет дочерних элементов.</div>
@else
    <div class="table-responsive">
        @if($title)
          <h6 class="mb-2">{!! $title !!}</h6>
        @endif
        <table class="table table-sm mb-0">
            <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{!! $column['label'] ?? $column['name'] !!}</th>
                @endforeach
                {{-- колонка действий как на базовом списке --}}
                <th>{{ trans('backpack::crud.actions') }}</th>
            </tr>
            </thead>

            <tbody>
            @foreach ($children as $child)
              @php
                $entry = $child;
              @endphp
                <tr>
                    @foreach ($columns as $column)
                        @php
                            $col  = $column; // локальная копия
                            $view = $col['view'] ?? ('crud::columns.' . ($col['type'] ?? 'text'));
                        @endphp
                        <td>
                            @include($view, [
                                'entry'  => $child,
                                'crud'   => $crud,
                                'column' => $col,
                            ])
                        </td>
                    @endforeach

                    {{-- actions: используем тот же механизм line-buttons --}}
                    @if ( $line_buttons->count() )
                      <td>
                        @foreach($line_buttons as $button)
                          @include($button->content)
                        @endforeach
                      </td>
                    @endif
                    
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
