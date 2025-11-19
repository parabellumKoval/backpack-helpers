@php
    $presentation = \Backpack\Helpers\Support\TextProgressPresenter::prepare($entry, $column);
    $value = $presentation['text'];

    if (array_key_exists('value', $column)) {
        $value = $column['value'];
        if (is_callable($value)) {
            $value = $value($entry, $column);
        }
    }

    if (($value === null || $value === '') && array_key_exists('default', $column)) {
        $value = $column['default'];
    }

    $value = is_null($value) ? '' : (string) $value;
    $value = ($column['prefix'] ?? '') . $value . ($column['suffix'] ?? '');
    $limit = $column['limit'] ?? null;

    if ($limit !== null) {
        $end = $column['limit_end'] ?? '...';
        $value = \Illuminate\Support\Str::limit($value, (int) $limit, $end);
    }

    $escaped = $column['escaped'] ?? true;
@endphp

@include('crud::columns.partials.translation_progress_styles')

<div class="translation-text-progress d-flex flex-column">
    <div class="translation-text-progress-value">
        @if(trim($value) === '')
            <span class="text-muted">—</span>
        @else
            @if($escaped)
                {{ $value }}
            @else
                {!! $value !!}
            @endif
        @endif
    </div>
    <div class="translation-progress-meta d-flex justify-content-between align-items-center mt-1">
        <small class="translation-progress-summary {{ $presentation['summary_class'] }}">
            {{ $presentation['summary'] }}
        </small>
        <div class="translation-locale-tags translation-locale-tags--compact">
            @foreach($presentation['badges'] as $badge)
                <span class="translation-locale-tag {{ $badge['filled'] ? 'is-filled' : '' }}">
                    {{ $badge['code'] }}
                </span>
            @endforeach
        </div>
    </div>
    <div class="progress translation-progress-bar mt-1">
        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $presentation['progress_percent'] }}%;" aria-valuenow="{{ $presentation['progress_percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
</div>
