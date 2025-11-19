@php
    $presentation = \Backpack\Helpers\Support\SeoStatusPresenter::prepare($entry, $column);
@endphp

@if (! $presentation['has_rows'])
    <span class="text-muted">—</span>
@else
    <div class="d-flex flex-column">
        @include('crud::columns.partials.seo_status_details', ['rows' => $presentation['rows']])
    </div>
@endif
