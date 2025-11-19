@php
    $field['attributes'] = $field['attributes'] ?? [];
    $rows = $field['rows'] ?? $field['attributes']['rows'] ?? 3;
    $recommendedLength = (int) ($field['recommended_length'] ?? 0);
    $resizable = filter_var($field['resizable'] ?? $field['resizeable'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $textareaId = $field['attributes']['id'] ?? $field['name'].'_countable_'.uniqid();

    $field['attributes']['id'] = $textareaId;
    $field['attributes']['rows'] = $rows;
    $field['attributes']['class'] = trim(($field['attributes']['class'] ?? 'form-control').' countable-textarea-input');

    if (! $resizable) {
        $field['attributes']['style'] = trim(($field['attributes']['style'] ?? '').' resize: none;');
    } else {
        $field['attributes']['style'] = trim(($field['attributes']['style'] ?? '').' overflow: hidden;');
    }
@endphp

@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>
    @include('crud::fields.inc.translatable_icon')

    <div class="countable-textarea-wrapper" data-countable-wrapper data-recommended-length="{{ $recommendedLength }}">
        <textarea
            data-countable-textarea="true"
            data-recommended-length="{{ $recommendedLength }}"
            data-countable-resize="{{ $resizable ? 'true' : 'false' }}"
            data-countable-rows="{{ (int) $rows }}"
            name="{{ $field['name'] }}"
            @include('crud::fields.inc.attributes')
        >{{ old(square_brackets_to_dots($field['name'])) ?? $field['value'] ?? $field['default'] ?? '' }}</textarea>

        <div class="d-flex align-items-center mt-2">
            <div class="progress flex-grow-1" style="height: 4px;">
                <div class="progress-bar bg-info" data-countable-progress-bar style="width: 0%;" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <small class="ml-2 text-muted" data-countable-counter>
                {{ $recommendedLength > 0 ? '0/'.$recommendedLength : '0' }}
            </small>
        </div>
        <small class="d-block mt-1 text-muted" data-countable-status>
            @if($recommendedLength > 0)
                {{ __('Рекомендуемая длина: :count символов', ['count' => $recommendedLength]) }}
            @endif
        </small>
    </div>

    {{-- HINT --}}
    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif
@include('crud::fields.inc.wrapper_end')

@if ($crud->fieldTypeNotLoaded($field))
    @php
        $crud->markFieldTypeAsLoaded($field);
    @endphp

    @push('crud_fields_styles')
        <style>
            .countable-textarea-wrapper textarea {
                transition: height 0.2s ease;
            }
        </style>
    @endpush

    @push('crud_fields_scripts')
        <script>
            (function($){
                if (typeof window.initCountableTextareas === 'undefined') {
                    window.initCountableTextareas = function(context) {
                        var $context = context ? $(context) : $(document);

                        $context.find('[data-countable-textarea]').each(function(){
                            var $textarea = $(this);

                            if ($textarea.data('countable-bound')) {
                                updateCountableTextarea($textarea);
                                return;
                            }

                            $textarea.data('countable-bound', true);
                            $textarea.on('input', function(){
                                updateCountableTextarea($textarea);
                            });

                            updateCountableTextarea($textarea);
                        });
                    };

                    function updateCountableTextarea($textarea) {
                        var recommended = parseInt($textarea.data('recommendedLength'), 10) || 0;
                        var count = ($textarea.val() || '').length;
                        var rawPercent = recommended > 0 ? (count / recommended) * 100 : 0;
                        var percent = rawPercent > 0 ? Math.min(100, Math.max(0, rawPercent)) : 0;
                        var $wrapper = $textarea.closest('[data-countable-wrapper]');
                        var $progress = $wrapper.find('[data-countable-progress-bar]');
                        var $counter = $wrapper.find('[data-countable-counter]');
                        var $status = $wrapper.find('[data-countable-status]');
                        var autoResize = $textarea.data('countableResize') === true;
                        var baseHeight = ensureTextareaBaseHeight($textarea);

                        $progress.css('width', percent + '%');
                        $progress.attr('aria-valuenow', Math.max(0, Math.round(rawPercent)));
                        $progress.removeClass('bg-info bg-success bg-danger bg-secondary');

                        if (recommended > 0) {
                            if (rawPercent <= 15) {
                                $progress.addClass('bg-secondary');
                            } else if (rawPercent <= 70) {
                                $progress.addClass('bg-info');
                            } else if (rawPercent <= 100) {
                                $progress.addClass('bg-success');
                            } else {
                                $progress.addClass('bg-danger');
                            }
                        } else {
                            $progress.addClass('bg-info');
                        }

                        if (recommended > 0) {
                            $counter.text(count + '/' + recommended);
                        } else {
                            $counter.text(count);
                        }

                        if (recommended > 0) {
                            if (count > recommended) {
                                $status.text('Превышено на ' + (count - recommended) + ' символов').addClass('text-danger');
                                $counter.addClass('text-danger');
                            } else if (count === recommended) {
                                $status.text('Достигнута рекомендуемая длина').removeClass('text-danger');
                                $counter.removeClass('text-danger');
                            } else {
                                $status.text('Осталось ' + (recommended - count) + ' символов').removeClass('text-danger');
                                $counter.removeClass('text-danger');
                            }
                        } else {
                            $status.text('');
                            $counter.removeClass('text-danger');
                        }

                        if (autoResize) {
                            applyAutoResize($textarea, baseHeight);
                        }
                    }

                    function ensureTextareaBaseHeight($textarea) {
                        var stored = parseFloat($textarea.data('countableBaseHeight')) || 0;
                        if (stored > 0) {
                            return stored;
                        }

                        var rows = parseInt($textarea.data('countableRows'), 10) || parseInt($textarea.attr('rows'), 10) || 2;
                        var lineHeight = parseFloat($textarea.css('lineHeight'));
                        if (isNaN(lineHeight) && $textarea[0]) {
                            var computed = window.getComputedStyle($textarea[0]);
                            lineHeight = parseFloat(computed.lineHeight);
                        }
                        if (isNaN(lineHeight) || lineHeight <= 0) {
                            lineHeight = 20;
                        }

                        var paddingTop = parseFloat($textarea.css('paddingTop')) || 0;
                        var paddingBottom = parseFloat($textarea.css('paddingBottom')) || 0;
                        var borderTop = parseFloat($textarea.css('borderTopWidth')) || 0;
                        var borderBottom = parseFloat($textarea.css('borderBottomWidth')) || 0;
                        var minHeight = Math.max(0, Math.round(rows * lineHeight + paddingTop + paddingBottom + borderTop + borderBottom));

                        if (minHeight > 0) {
                            $textarea.css('min-height', minHeight + 'px');
                            if ($textarea.data('countableResize') !== true) {
                                var currentHeight = parseFloat($textarea.css('height')) || 0;
                                if (currentHeight < minHeight) {
                                    $textarea.css('height', minHeight + 'px');
                                }
                            }
                        }

                        $textarea.data('countableBaseHeight', minHeight);

                        return minHeight;
                    }

                    function applyAutoResize($textarea, baseHeight) {
                        var el = $textarea[0];
                        if (!el) {
                            return;
                        }

                        var minHeight = baseHeight || ensureTextareaBaseHeight($textarea);
                        el.style.height = 'auto';
                        var scrollHeight = el.scrollHeight || 0;
                        var target = Math.max(scrollHeight, minHeight);
                        el.style.height = target + 'px';
                    }

                    $(document).ready(function () {
                        window.initCountableTextareas();
                    });

                    if (typeof crud !== 'undefined' && typeof crud.addFunctionToCrudFieldScriptsQueue === 'function') {
                        crud.addFunctionToCrudFieldScriptsQueue(function(){
                            window.initCountableTextareas();
                        });
                    }
                } else {
                    window.initCountableTextareas();
                }
            })(jQuery);
        </script>
    @endpush
@endif
