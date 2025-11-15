@extends(backpack_view('blank'))

@php
  $defaultBreadcrumbs = [
    trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
    $crud->entity_name_plural => url($crud->route),
    trans('backpack::crud.reorder') => false,
  ];
  $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;

  // ------ Скоуп/набор данных
  $scopeKey = $crud->get('reorder.scope_key') ?? 'parent';
  $scopeParentId = request($scopeKey, $crud->get('reorder.parent_id'));
  $showChildrenBtn = $crud->get('reorder.show_children_button');
  if ($showChildrenBtn === null) $showChildrenBtn = true;
  $childrenBtnLabel = $crud->get('reorder.children_button_label') ?? 'Отсортировать детей';

  // Текст заголовка/подзаголовка
  $datasetTitle = $crud->get('reorder.dataset_title') ?? null;

  // URL для переходов внутри операции reorder (с сохранением query параметров)
  $baseReorderUrl = $crud->get('reorder.url') ?? url($crud->route.'/reorder');

  // Текущий URL без query
  $currentUrl = Request::url();

  // Попробуем получить родителя (если не передан через контроллер переменной $parent)
  if (!isset($parent) && $scopeParentId) {
      try {
          $parent = $crud->getModel()::query()->find($scopeParentId);
      } catch (\Throwable $e) {
          $parent = null;
      }
  }
@endphp

@section('header')
<div class="container-fluid">
    <h2 class="d-flex align-items-center gap-2 flex-wrap">
        <span class="text-capitalize">{!! $crud->getHeading() ?? $crud->entity_name_plural !!}</span>
        <small class="text-muted">
            {!! $crud->getSubheading() ?? trans('backpack::crud.reorder').' '.$crud->entity_name_plural !!}
        </small>

        @if ($crud->hasAccess('list'))
          <small>
            <a href="{{ url($crud->route) }}" class="d-print-none font-sm">
              <i class="la la-angle-double-left"></i> {{ trans('backpack::crud.back_to_all') }}
              <span>{{ $crud->entity_name_plural }}</span>
            </a>
          </small>
        @endif
    </h2>

    {{-- Панель контекста набора данных --}}
    <div class="mt-2">
        @if($datasetTitle)
            <div class="text-muted">{{ $datasetTitle }}</div>
        @endif

        @if($scopeParentId && isset($parent))
            <div class="small text-muted">
                Сортировка детей: <strong>{{ object_get($parent, $crud->get('reorder.label')) ?? ('#'.$parent->getKey()) }}</strong>
                @php
                    $upId = null;
                    if (isset($parent->parent_id)) {
                        $upId = (int) $parent->parent_id ?: 0;
                        // если parent_id = 0 => поднимаемся на «корень» (null для query)
                        $upId = $upId === 0 ? null : $upId;
                    }
                @endphp
                @if(!is_null($upId) || $scopeParentId)
                    <a href="{{ reorder_url_with_parent($baseReorderUrl, $scopeKey, $upId) }}" class="btn btn-sm btn-outline-secondary ml-2">
                        <i class="la la-level-up"></i> Вверх
                    </a>
                @endif
                <a href="{{ reorder_url_with_parent($baseReorderUrl, $scopeKey, null) }}" class="btn btn-sm btn-outline-secondary ml-1">
                    <i class="la la-sitemap"></i> К корню
                </a>
            </div>
        @else
            <div class="small text-muted">Сортировка корневого уровня</div>
        @endif
    </div>
</div>
@endsection

@section('content')

<div class="row mt-4">
    <div class="{{ $crud->getReorderContentClass() }}">
        <div class="card p-4">
            <p>{{ trans('backpack::crud.reorder_text') }}</p>

            <ol class="sortable mt-0">
            @php
                $all_entries = collect($entries->all())->sortBy('lft')->keyBy($crud->getModel()->getKeyName());

                if ($scopeParentId) {
                    // обычный режим «дети X»
                    $root_entries = $all_entries->filter(fn($item) => (string)$item->parent_id === (string)$scopeParentId);
                } else {
                    // режим частичной выборки без явного родителя:
                    // корнем считаем любой элемент, чей parent_id = 0 ИЛИ чей parent отсутствует в текущем наборе
                    $root_entries = $all_entries->filter(function ($item) use ($all_entries) {
                        $pid = (int) $item->parent_id;
                        return $pid === 0 || !$all_entries->has($pid);
                    });
                }

                foreach ($root_entries as $key => $entry) {
                    $root_entries[$key] = render_tree_element_scoped(
                        $entry, $key, $all_entries, $crud, $scopeParentId,
                        $showChildrenBtn, $childrenBtnLabel, $baseReorderUrl, $scopeKey
                    );
                }
            @endphp
            </ol>

        </div><!-- /.card -->

        <button id="toArray" class="btn btn-success" data-style="zoom-in">
            <i class="la la-save"></i> {{ trans('backpack::crud.save') }}
        </button>
    </div>
</div>
@endsection


@section('after_styles')
<style>
    .ui-sortable .placeholder { outline: 1px dashed #4183C4; }
    .ui-sortable .mjs-nestedSortable-error { background: #fbe3e4; border-color: transparent; }
    .ui-sortable ol { margin: 0; padding: 0 0 0 30px; }
    ol.sortable, ol.sortable ol { margin: 0 0 0 25px; padding: 0; list-style-type: none; }
    ol.sortable { margin: 2em 0; }
    .sortable li { margin: 5px 0 0 0; padding: 0; }
    .sortable li div  {
      border: 1px solid #ddd; border-radius: 3px; padding: 6px; margin: 0; cursor: move;
      background-color: #f4f4f4; color: #444; border-color: #00acd6;
    }
    .sortable li.mjs-nestedSortable-leaf div { border: 1px solid #ddd; }
    li.mjs-nestedSortable-collapsed.mjs-nestedSortable-hovering div { border-color: #999; background: #fafafa; }
    .ui-sortable .disclose { cursor: pointer; width: 10px; display: none; }
    .sortable li.mjs-nestedSortable-branch > div > .disclose { display: inline-block; }
    .sortable li.mjs-nestedSortable-collapsed > ol { display: none; }
    .sortable li.mjs-nestedSortable-collapsed > div > .disclose > span:before { content: '+ '; }
    .sortable li.mjs-nestedSortable-expanded > div > .disclose > span:before { content: '- '; }
</style>
<link rel="stylesheet" href="{{ asset('packages/backpack/crud/css/crud.css').'?v='.config('backpack.base.cachebusting_string') }}">
<link rel="stylesheet" href="{{ asset('packages/backpack/crud/css/reorder.css').'?v='.config('backpack.base.cachebusting_string') }}">
@endsection

@section('after_scripts')
<script src="{{ asset('packages/backpack/crud/js/crud.js').'?v='.config('backpack.base.cachebusting_string') }}" type="text/javascript"></script>
<script src="{{ asset('packages/backpack/crud/js/reorder.js').'?v='.config('backpack.base.cachebusting_string') }}" type="text/javascript"></script>
<script src="{{ asset('packages/jquery-ui-dist/jquery-ui.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('packages/nestedSortable/jquery.mjs.nestedSortable2.js') }}" type="text/javascript"></script>

<script type="text/javascript">
jQuery(function($) {
    // Инициализация nestedSortable
    $('.sortable').nestedSortable({
        forcePlaceholderSize: true,
        handle: 'div',
        helper: 'clone',
        items: 'li',
        opacity: .6,
        placeholder: 'placeholder',
        revert: 250,
        tabSize: 25,
        tolerance: 'pointer',
        toleranceElement: '> div',
        maxLevels: {{ $crud->get('reorder.max_level') ?? 3 }},
        isTree: true,
        expandOnHover: 700,
        startCollapsed: false
    });

    $('.disclose').on('click', function() {
        $(this).closest('li').toggleClass('mjs-nestedSortable-collapsed').toggleClass('mjs-nestedSortable-expanded');
    });

    var scopeParentId = {!! $scopeParentId ? (int) $scopeParentId : 'null' !!};
    var postUrl = {!! json_encode(Request::fullUrl()) !!};

    // Собираем карту исходных parent_id
    var originalParents = {};
    $('ol.sortable li').each(function(){
        var id = parseInt(this.id.replace('list_', ''), 10);
        var op = parseInt($(this).data('original-parent-id'), 10);
        originalParents[id] = isNaN(op) ? 0 : op;
    });

    $('#toArray').on('click', function(){
        var data = $('ol.sortable').nestedSortable('toArray', {startDepthCount: 0});

        // Если сортируем "детей X" — верхним уровням присваиваем parent_id = X (как раньше)
        if (scopeParentId) {
        data = data.map(function(row){
            if (row && (row.parent_id === null || row.parent_id === 0)) {
            row.parent_id = scopeParentId;
            }
            return row;
        });
        } else {
        // Частичная выборка БЕЗ явного родителя:
        // если узел оказался на «верхнем уровне» (parent_id = null),
        // но изначально имел родителя (originalParents[id] != 0), то НЕ трогаем его связь
        data = data.map(function(row){
            if (!row) return row;
            var id = parseInt(row.item_id, 10);
            if ((row.parent_id === null || row.parent_id === 0) && originalParents[id] && originalParents[id] !== 0) {
            row.parent_id = originalParents[id];
            }
            return row;
        });
        }

        $.ajax({
        url: postUrl,
        type: 'POST',
        data: { tree: data, scope_parent_id: scopeParentId },
        })
        .done(function(){ new Noty({type:"success",text:"<strong>{{ trans('backpack::crud.reorder_success_title') }}</strong><br>{{ trans('backpack::crud.reorder_success_message') }}"}).show(); })
        .fail(function(){ new Noty({type:"error",text:"<strong>{{ trans('backpack::crud.reorder_error_title') }}</strong><br>{{ trans('backpack::crud.reorder_error_message') }}"}).show(); });
    });

    $.ajaxPrefilter(function(options, originalOptions, xhr) {
        var token = $('meta[name="csrf_token"]').attr('content');
        if (token) xhr.setRequestHeader('X-XSRF-TOKEN', token);
    });
});
</script>
@endsection
