@php
    $entryId = $entry->getKey();
    $columnName = $column['name'];
    $toggle = $column['toggle'] ?? [];
    $attribute = $toggle['attribute'] ?? $columnName;
    $values = $toggle['values'] ?? ['checked' => 1, 'unchecked' => 0];
    $routeSegment = $toggle['route'] ?? 'toggle';
    $uniqueId = 'switch_'.$columnName.'_'.$entryId;
    $isChecked = (string) data_get($entry, $attribute) === (string) ($values['checked'] ?? 1);
@endphp

<span class="custom-control custom-switch">
    <input type="checkbox"
           class="custom-control-input toggle-switch"
           id="{{ $uniqueId }}"
           data-id="{{ $entryId }}"
           data-column="{{ $columnName }}"
           data-route="{{ url($crud->route) }}"
           data-segment="{{ $routeSegment }}"
           data-values='@json($values)'
           {{ $isChecked ? 'checked' : '' }}>
    <label class="custom-control-label" for="{{ $uniqueId }}"></label>
</span>

<script>
    if (typeof toggleSwitchInit != 'function') {
        function toggleSwitchInit() {
            $('body')
                .off('change.toggle-column', '.toggle-switch')
                .on('change.toggle-column', '.toggle-switch', function (e) {
                    e.preventDefault();

                    var $this = $(this);
                    var entryId = $this.data('id');
                    var values = $this.data('values') || { checked: 1, unchecked: 0 };
                    var column = $this.data('column');
                    var routeBase = ($this.data('route') || '').replace(/\/$/, '');
                    var routeSegment = $this.data('segment') || 'toggle';
                    var isChecked = $this.is(':checked');
                    var value = isChecked ? values.checked : values.unchecked;

                    $.ajax({
                        url: routeBase + '/' + entryId + '/' + routeSegment,
                        type: 'POST',
                        data: {
                            column: column,
                            value: value,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function () {
                            new Noty({
                                type: "success",
                                text: "Статус успешно обновлен"
                            }).show();
                        },
                        error: function () {
                            $this.prop('checked', !isChecked);
                            new Noty({
                                type: "error",
                                text: "Ошибка при обновлении статуса"
                            }).show();
                        }
                    });
                });
        }
    }

    crud.addFunctionToDataTablesDrawEventQueue('toggleSwitchInit');
</script>
