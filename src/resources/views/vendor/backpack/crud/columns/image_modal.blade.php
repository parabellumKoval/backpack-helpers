@php
    $columnName = $column['name'] ?? null;
    $value = $columnName ? data_get($entry, $columnName) : null;

    if (is_object($value) && method_exists($value, 'toArray')) {
        $value = $value->toArray();
    }

    $imageIndex = max(0, (int) ($column['image_index'] ?? 0));
    $srcKey = $column['src_key'] ?? 'src';
    $prefix = (string) ($column['prefix'] ?? '');

    $height = $column['height'] ?? '60px';
    $width = $column['width'] ?? 'auto';
    $radius = $column['radius'] ?? '4px';
    $emptyText = $column['empty_text'] ?? '-';
    $modalTitle = $column['modal_title'] ?? ($column['label'] ?? 'Preview');

    $path = null;

    if (is_array($value)) {
        if (array_is_list($value)) {
            $candidate = $value[$imageIndex] ?? null;

            if (is_array($candidate)) {
                $path = data_get($candidate, $srcKey);
            } elseif (is_string($candidate)) {
                $path = $candidate;
            }
        } else {
            $path = data_get($value, $srcKey);
        }
    } elseif (is_string($value)) {
        $path = $value;
    }

    $url = null;

    if (is_string($path)) {
        $path = trim($path);
    }

    if (is_string($path) && $path !== '') {
        $isAbsolutePath = \Illuminate\Support\Str::startsWith($path, ['http://', 'https://', '//', 'data:image/']);

        if ($isAbsolutePath) {
            $url = $path;
        } elseif ($columnName && method_exists($entry, 'formatImageUrlForAttribute')) {
            $url = $entry->formatImageUrlForAttribute($columnName, $path, $prefix);
        } elseif (!empty($column['disk'])) {
            $url = \Illuminate\Support\Facades\Storage::disk($column['disk'])->url($prefix . $path);
        } else {
            $url = asset(ltrim($prefix . $path, '/'));
        }
    }

    $resolvedUrl = null;

    if (is_string($url) && $url !== '') {
        $isAbsoluteUrl = \Illuminate\Support\Str::startsWith($url, ['http://', 'https://', '//', 'data:image/']);
        $resolvedUrl = $isAbsoluteUrl ? $url : url(ltrim($url, '/'));
    }
@endphp

@if(empty($resolvedUrl))
    <span>{{ $emptyText }}</span>
@else
    <a href="#"
       class="bp-image-modal-trigger"
       data-image-src="{{ $resolvedUrl }}"
       data-image-title="{{ $modalTitle }}"
       style="display: inline-block; line-height: 0;">
        <img src="{{ $resolvedUrl }}"
             alt=""
             loading="lazy"
             style="max-height: {{ $height }}; max-width: {{ $width }}; border-radius: {{ $radius }}; cursor: zoom-in; display: block;" />
    </a>
@endif

@once
    <div class="modal fade" id="bpImageModalColumnPreview" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bpImageModalColumnPreviewTitle">Preview</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <img id="bpImageModalColumnPreviewImage"
                         src=""
                         alt=""
                         style="max-width: 100%; max-height: 75vh; border-radius: 6px;" />
                </div>
            </div>
        </div>
    </div>

    <script>
        if (typeof imageModalColumnInit !== 'function') {
            function imageModalColumnInit() {
                $('body')
                    .off('click.image-modal-column', '.bp-image-modal-trigger')
                    .on('click.image-modal-column', '.bp-image-modal-trigger', function (e) {
                        e.preventDefault();

                        var $trigger = $(this);
                        var src = ($trigger.attr('data-image-src') || '').trim();
                        var title = $trigger.data('image-title') || 'Preview';
                        var $modals = $('#bpImageModalColumnPreview');
                        var $modal = $modals.first();

                        if (!src || !$modal.length) {
                            return;
                        }

                        if ($modals.length > 1) {
                            $modals.not(':first').remove();
                        }

                        if (!$modal.parent().is('body')) {
                            $modal.appendTo('body');
                        }

                        $modal.find('#bpImageModalColumnPreviewImage').attr('src', src);
                        $modal.find('#bpImageModalColumnPreviewTitle').text(title);

                        if (typeof $modal.modal === 'function') {
                            $modal.modal('show');
                        } else if (window.bootstrap && window.bootstrap.Modal) {
                            window.bootstrap.Modal.getOrCreateInstance($modal[0]).show();
                        }
                    });

                $('body')
                    .off('hidden.bs.modal.image-modal-column', '#bpImageModalColumnPreview')
                    .on('hidden.bs.modal.image-modal-column', '#bpImageModalColumnPreview', function () {
                        $(this).find('#bpImageModalColumnPreviewImage').attr('src', '');
                    });
            }
        }

        if (typeof crud !== 'undefined' && typeof crud.addFunctionToDataTablesDrawEventQueue === 'function') {
            crud.addFunctionToDataTablesDrawEventQueue('imageModalColumnInit');
        }

        imageModalColumnInit();
    </script>
@endonce
