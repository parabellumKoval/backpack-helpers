@php
    $presentation = \Backpack\Helpers\Support\SeoStatusPresenter::prepare($entry, $column);
@endphp

@if (! $presentation['has_rows'])
    <span class="text-muted">—</span>
@else
    @php
        $tooltipId = 'seo_status_' . uniqid();
    @endphp
    <div class="seo-status-compact d-inline-flex flex-column" data-seo-status-tooltip data-tooltip-id="{{ $tooltipId }}" data-toggle="tooltip" data-html="true" data-placement="auto" data-container="body">
        @foreach($presentation['rows'] as $row)
            @php
                $stateClass = 'badge-secondary';
                if ($row['filled_locales'] === $row['total_locales'] && $row['total_locales'] > 0) {
                    $stateClass = 'badge-success';
                } elseif ($row['filled_locales'] > 0) {
                    $stateClass = 'badge-warning';
                }
            @endphp
            <span class="badge badge-pill text-uppercase small {{ $stateClass }} mb-1 px-3 py-1 text-center" title="{{ $row['label'] }}">
                {{ $row['short_label'] }}
            </span>
        @endforeach
    </div>
    <div id="{{ $tooltipId }}" class="d-none">
        <div class="seo-status-compact-tooltip p-2">
            @foreach($presentation['rows'] as $row)
                <div class="seo-status-compact-tooltip-row">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong class="seo-status-compact-tooltip-label text-uppercase">{{ $row['label'] }}</strong>
                        <small class="{{ $row['summary_class'] }}">{{ $row['summary'] }}</small>
                    </div>
                    <div class="seo-status-compact-tooltip-locales mt-1 mb-2">
                        @foreach($row['badges'] as $badge)
                            <span class="badge badge-pill text-uppercase small {{ $badge['filled'] ? 'badge-success' : 'badge-secondary' }} mr-1 mb-1 px-2 py-1">
                                {{ $badge['code'] }}
                            </span>
                        @endforeach
                    </div>
                    <div class="progress" style="height: 3px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $row['progress_percent'] }}%;" aria-valuenow="{{ $row['progress_percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        if (typeof initSeoStatusCompactTooltip === 'undefined') {
            function initSeoStatusCompactTooltip() {
                $('[data-seo-status-tooltip]').each(function () {
                    var $el = $(this);
                    var tooltipId = $el.data('tooltipId');
                    if (!tooltipId) {
                        return;
                    }
                    var $template = $('#' + tooltipId);
                    if ($template.length) {
                        $el.attr('data-original-title', $template.html());
                    }
                    if (!$el.data('bs.tooltip')) {
                        $el.tooltip({
                            boundary: 'window'
                        });
                    }
                });
            }
        }

        if (typeof crud !== 'undefined') {
            crud.addFunctionToDataTablesDrawEventQueue && crud.addFunctionToDataTablesDrawEventQueue('initSeoStatusCompactTooltip');
        }

        initSeoStatusCompactTooltip();
    </script>

    @once
        @push('crud_list_styles')
            <style>
                .seo-status-compact .badge {
                    min-width: 42px;
                    letter-spacing: 0.05em;
                }

                .seo-status-compact-tooltip {
                    min-width: 240px;
                    max-width: 360px;
                }

                .seo-status-compact-tooltip-row + .seo-status-compact-tooltip-row {
                    margin-top: 0.75rem;
                    padding-top: 0.75rem;
                    border-top: 1px solid rgba(0, 0, 0, 0.08);
                }

                .seo-status-compact-tooltip-label {
                    font-size: 11px;
                    letter-spacing: 0.05em;
                }

                .seo-status-compact-tooltip-locales .badge {
                    letter-spacing: 0.05em;
                }
            </style>
        @endpush
    @endonce
@endif
