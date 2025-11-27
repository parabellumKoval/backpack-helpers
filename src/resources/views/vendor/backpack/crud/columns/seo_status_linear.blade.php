@php
    $presentation = \Backpack\Helpers\Support\SeoStatusPresenter::prepare($entry, $column);
@endphp

@if (! $presentation['has_rows'])
    <span class="text-muted">—</span>
@else
    @include('crud::columns.partials.translation_progress_styles')
    <div class="seo-status-linear d-flex flex-column">
        @foreach($presentation['rows'] as $row)
            <div class="seo-status-linear-row rounded mb-1 px-3 py-2 {{ $row['container_class'] }}">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <strong class="text-uppercase" style="font-size: 12px; font-weight: 500; letter-spacing: 0.05em;">
                        {{ $row['label'] }}
                    </strong>
                    <small class="{{ $row['summary_class'] }}">{{ $row['summary'] }}</small>
                </div>
                <div class="translation-locale-tags translation-locale-tags--spaced mt-1">
                    @foreach($row['badges'] as $badge)
                        <span class="translation-locale-tag {{ $badge['filled'] ? 'is-filled' : '' }}">
                            {{ $badge['code'] }}
                        </span>
                    @endforeach
                </div>
                <div class="progress translation-progress-bar mt-1">
                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $row['progress_percent'] }}%;" aria-valuenow="{{ $row['progress_percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        @endforeach
    </div>
@endif
