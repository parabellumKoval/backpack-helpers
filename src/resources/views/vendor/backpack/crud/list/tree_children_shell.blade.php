@php
  /** @var \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud */
  $renderColumns = collect($columns)
    ->reject(fn($c) => in_array(($c['key'] ?? ''), ['bulk_actions','blank_first_column'], true))
    ->values();

  $columnKeys   = $renderColumns->map(fn($c) => $c['key'] ?? $c['name'])->all();
  $columnLabels = $renderColumns->map(fn($c) => $c['label'] ?? $c['name'])->all();

  $tableId = 'tree-child-table-'.$parent->getKey();
  $searchUrl = url($crud->route.'/search');
  $detailsBase = rtrim(url($crud->route), '/');
@endphp

<div class="bp-tree-children card"
     data-bp-inited="0"
     data-parent-id="{{ $parent->getKey() }}"
     data-table-id="{{ $tableId }}"
     data-search-url="{{ $searchUrl }}"
     data-details-base="{{ $detailsBase }}"
     data-columns="@json($columnKeys)"
     data-page-length="{{ (int)($pageLength ?? 10) }}">

  @if($title)
  <div class="card-header">
    <div class="d-flex justify-content-between align-items-center">
      <!-- Слева -->
      <h6 class="mb-0">{!! $title !!}</h6>

      <!-- Центр -->
      <div class="text-center">
      </div>

      <!-- Справа -->
      <div style="max-width:280px;width:100%;">
        <div class="input-group input-group-sm">
          <input type="text" class="form-control bp-tree-search" placeholder="Поиск в этом уровне">
        </div>
      </div>
    </div>
  </div>
  @endif

  <div class="table-responsive card-body">
    <table id="{{ $tableId }}" class="table table-sm table-striped mb-0">
      <thead>
        <tr>
          <th style="width:36px;"></th>
          @foreach($columnLabels as $label)
            <th class="bp-tree-sort" data-col-idx="{{ $loop->index }}">{{ $label }}</th>
          @endforeach
          <th style="width:1%">{{ trans('backpack::crud.actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr class="text-muted">
          <td colspan="{{ 2 + count($columnLabels) }}">
            <span class="spinner-border spinner-border-sm me-1"></span>
            Загрузка…
          </td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="{{ 2 + count($columnLabels) }}">
            <div class="d-flex justify-content-between align-items-center">
              <div></div>  
              <nav class="bp-tree-pager ml-auto"></nav>
            </div>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
