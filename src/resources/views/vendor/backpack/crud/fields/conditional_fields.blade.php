
@php
    // $field ожидает:
    // 'type'    => 'conditional_fields'
    // 'label'   => '...' (необязательно)
    // 'hint'    => '...' (необязательно)
    // 'driver'  => [ ... любой backpack-поле ... ]
    // 'branches'=> [
    //     'driver_value_1' => ['fields' => [ ... массив полей ... ]],
    //     'driver_value_2' => ['fields' => [ ... ]],
    //     '*'              => ['fields' => [ ... ]] // опциональная дефолтная ветка
    // ]
    //
    // Ветки могут содержать внутри ещё один conditional_fields — будет работать рекурсивно.

    $driver = $field['driver'] ?? null;
    if (!$driver || !is_array($driver) || !isset($driver['type'])) {
        throw new \InvalidArgumentException('conditional_fields: "driver" обязателен и должен быть валидным полем Backpack.');
    }

    // Проставим id, если не задан
    $field['attributes'] = $field['attributes'] ?? [];
    $field['attributes']['id'] = $field['attributes']['id'] ?? 'cf_'.\Illuminate\Support\Str::random(6);

    $branches = $field['branches'] ?? [];
    $branchKeys = array_keys($branches);


    // Проставим значение поля из текущей записи, если Backpack его не задал
    $prefillValueFromEntry = function (array $field) use ($crud) {
        if (array_key_exists('value', $field) || ! isset($field['name'])) {
            return $field;
        }

        $entry = $crud->getCurrentEntry();
        if (! $entry) {
            return $field;
        }

        $names = (array) $field['name'];
        $resolvedValues = [];

        foreach ($names as $name) {
            if (! is_string($name)) {
                $resolvedValues[] = null;
                continue;
            }

            $key = trim(square_brackets_to_dots($name), '.');

            if ($key === '') {
                $resolvedValues[] = null;
                continue;
            }

            $resolvedValues[] = data_get($entry, $key);
        }

        $field['value'] = count($resolvedValues) === 1 ? $resolvedValues[0] : $resolvedValues;

        return $field;
    };

    // небольшая утилита для рендера произвольного поля
    $renderField = function (array $f) use ($crud, $prefillValueFromEntry) {
        $f = collect($f)->toArray();
        $f = $prefillValueFromEntry($f);

        // Само поле
        $viewName = 'crud::fields.' . $f['type'];

        echo view($viewName, ['field' => $f, 'crud' => $crud])->render();
    };
@endphp

 
<div class="conditional-container">
    {{-- Рендерим "драйвер" --}}
    @php
        $driver['wrapper'] = $driver['wrapper'] ?? [];
        $driver['wrapper'] = ($driver['wrapper'] ?? []) + [
            'data-bp-cf-driver' => '1',
            'data-bp-cf-for'    => $field['attributes']['id'],
        ];

        // гарантируем уникальные id/names в пределах формы, Backpack сам с этим обычно справляется
        $renderField($driver);
    @endphp


    <input type="hidden"
       data-init-function="bpFieldInitConditionalFields"
       data-cf-root="{{ $field['attributes']['id'] }}"
       value="">

    {{-- Контейнер веток --}}
    <div class="bp-cf-branches col-sm-12" id="{{ $field['attributes']['id'] }}"
         data-driver-name="{{ e($driver['name']) }}"
         data-allow-default="{{ array_key_exists('*', $branches) ? '1' : '0' }}"
         data-driver-default="{{ e($driver['default'] ?? '') }}"
         data-branch-keys='@json($branchKeys)'>
        @foreach($branches as $value => $conf)
            @php
                $groupId = $field['attributes']['id'].'__branch__'.md5((string)$value);
                $fieldsInBranch = $conf['fields'] ?? [];
            @endphp
            <div class="bp-cf-branch d-none row" data-branch-value="{{ $value }}" id="{{ $groupId }}">
                @foreach($fieldsInBranch as $sub)
                    @php
                      $renderField($sub); 
                    @endphp
                @endforeach
            </div>
        @endforeach
    </div>

    @if(!empty($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif
</div>

{{-- ########################################## --}}
{{-- Extra CSS and JS for this particular field --}}
{{-- If a field type is shown multiple times on a form, the CSS and JS will only be loaded once --}}
@if ($crud->fieldTypeNotLoaded($field))
    @php
        $crud->markFieldTypeAsLoaded($field);
    @endphp

    {{-- FIELD EXTRA CSS  --}}
    {{-- push things in the after_styles section --}}
    @push('crud_fields_styles')
        <!-- no styles -->
        <style type="text/css">
          .conditional-container {
            width: 100%;
          }
        </style>
    @endpush

    {{-- FIELD JS - will be loaded in the after_scripts section --}}
    @push('crud_fields_scripts')
        <script>
            // HELPERS
            // HELPERS
            // HELPERS
            function qsa(root, sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); }

            function setDisabled(container, disabled) {
              qsa(container, 'input, select, textarea, button').forEach(function (el) {
                if (disabled) {
                  el.disabled = true;
                  el.setAttribute('disabled', 'disabled');
                } else {
                  el.disabled = false;
                  el.removeAttribute('disabled');
                }
              });
            }

            function valueOf(el) {
              if (!el) return null;

              if (el.__bp_cf_value_source) {
                return el.__bp_cf_value_source.value;
              }

              if (el.type === 'checkbox') return el.checked ? (el.value || '1') : '';
              if (el.type === 'radio') {
                var form = el.form || document;
                var group = form.querySelectorAll('input[type=radio][name="'+el.name+'"]');
                for (var i=0;i<group.length;i++) if (group[i].checked) return group[i].value;
                return '';
              }
              if (el.tagName === 'SELECT' && el.multiple) {
                return Array.from(el.options).filter(o => o.selected).map(o => o.value);
              }
              return el.value;
            }

            function matchBranch(branchValue, current) {
              if (branchValue === '*') return true;

              // Зачем-то раньше было нужно
              // if (Array.isArray(current)) return current.indexOf(branchValue) !== -1;

              if (Array.isArray(branchValue)) {
                branchValue = branchValue.map((item) => String(item))
                return branchValue.indexOf(current) !== -1;
              }
              
              return String(current) === String(branchValue);
            }
            
            // поиск драйвер
            function findDriver(root) {
              if (!root) return null;

              var container = root.closest('.conditional-container');
              if (!container) return null;

              var wrapper = container.querySelector('[data-bp-cf-driver="1"]');
              if (!wrapper) return null;

              // предпочитаем интерактивные элементы (не hidden), чтобы change-события срабатывали корректно
              var preferred = wrapper.querySelector('input:not([type=hidden]), select, textarea');
              var fallback = wrapper.querySelector('input, select, textarea');

              var driver = preferred || fallback;
              if (!driver) return null;

              var driverName = root.getAttribute('data-driver-name');
              if (driverName) {
                var escaped = driverName;
                if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
                  escaped = CSS.escape(driverName);
                } else {
                  escaped = driverName.replace(/["\\]/g, '\\$&');
                }

                var hidden = wrapper.querySelector('input[type="hidden"][name="'+escaped+'"]') ||
                             wrapper.querySelector('[name="'+escaped+'"]');

                if (hidden && hidden !== driver) {
                  driver.__bp_cf_value_source = hidden;
                }
              }

              return driver;
            }

            // MAIN MAIN MAIN
            // MAIN MAIN MAIN
            // MAIN MAIN MAIN
            
            // Инициализируем JS каждой ветки (а надо каждого поля ветки)
            function lazyInitChildrenOnce(branch) {
              // console.log('lazyInitChildrenOnce', branch.__bp_cf_children_init, branch)
              // if (branch.__bp_cf_children_init) return;

              let name = $(branch).find('input[name-copy]').attr("name-copy");
              let holder = $(branch).find('[data-repeatable-holder='+CSS.escape(name)+']');
              // console.log('lazyInitChildrenOnce', branch, name, holder)

              try {
                initializeFieldsWithJavascript(branch);
                // if (typeof window.initializeFieldsWithJavascript === 'function') {
                //   if (window.jQuery) {
                //     window.initializeFieldsWithJavascript(window.jQuery(branch));
                //   } else {
                //     window.initializeFieldsWithJavascript(branch);
                //   }
                // }
              } catch (e) { /* no-op */ }

              // Записываем что ветка уже инициализирована в сам теш
              // ПРОБЛЕМА ЧТО ТЕГ ОДИН РАЗ ДОБАВЛЕН ВНИЗ СТРАНИЦЫ И ПОТОМ КЛОНИРУЕТСЯ
              // Если произошла инициализация, а потом весь контейнер удален, то запись все равно остается что ветка инициализирована
              // хотя при повторном добавлении возможно требуется новая инициализация
              branch.__bp_cf_children_init = true;
            }

            // Показывает или скрывает ветки в зависимости от выбраного драйвера
            // При переключении в активное состояние инциализирует JS в активных полях
            function updateBranches(root, driver, allowDefault) {
              // Берем активное значение у Драйвера
              var val = valueOf(driver);
              var shown = false;

              // Тут для каждой ветки показываем или прячем ее в зависимости от значения драйвера
              Array.prototype.filter.call(root.children, function (el) {
                return el.classList && el.classList.contains('bp-cf-branch');
              }).forEach(function (branch) {
                var v = $(branch).data('branch-value');
                var ok = matchBranch(v, val);


                if (ok && !shown) {
                  branch.classList.remove('d-none');
                  setDisabled(branch, false);
                  // Когда показываем ветку пытаемся инициализировать ее значения через JS
                  // lazyInitChildrenOnce(branch);
                  shown = true;
                } else {
                  branch.classList.add('d-none');
                  setDisabled(branch, true);
                }
              });

              // allowDefault говорит о том установлены ли дефолтный значения для всех веток в крудКонтроллере под ключем "*"
              if (!shown && allowDefault) {
                var def = Array.prototype.find.call(root.children, function (el) {
                return el.classList && el.classList.contains('bp-cf-branch') &&
                      el.getAttribute('data-branch-value') === '*';
                });

                if (def) {
                  def.classList.remove('d-none');
                  setDisabled(def, false);
                  lazyInitChildrenOnce(def);
                }
              }
            }


            function initOne(root) {
              if (!root || root.__bp_cf_init) return;

              var driver = findDriver(root);
              if (!driver) return;

              root.__bp_cf_init = true;

              var allowDefault = root.getAttribute('data-allow-default') === '1';

              var doUpdate = function () { updateBranches(root, driver, allowDefault); };
              
              // первый прогон
              doUpdate();
              
              // прогон после изменения активного значения в драйвере
              driver.addEventListener('change', doUpdate);
            }

            // Инициализация всего сразу как загрузся дом
            // function initAll(context) {
            //   var nodes = context
            //     ? (context.matches && context.matches('.bp-cf-branches') ? [context] : qsa(context, '.bp-cf-branches'))
            //     : qsa(document, '.bp-cf-branches');

            //   nodes.forEach(initOne);
            // }
            // document.addEventListener('DOMContentLoaded', function () { initAll(); });

            // var mo = new MutationObserver(function (mutations) {
            //   mutations.forEach(function (m) {
            //     if (!m.addedNodes) return;
            //     Array.prototype.forEach.call(m.addedNodes, function (node) {
            //       if (node.nodeType !== 1) return;
            //       if (node.matches && node.matches('.bp-cf-branches')) initOne(node);
            //       qsa(node, '.bp-cf-branches').forEach(initOne);
            //     });
            //   });
            // });
            // mo.observe(document.documentElement || document.body, { childList: true, subtree: true });

            // инициализатор, который вызывается initializeFieldsWithJavascript
            function bpFieldInitConditionalFields(element) {
              var id = element.attr('data-cf-root');
              if (!id) return;
              var root = element.parent('.conditional-container').find("#"+id).get(0);

              initOne(root);
            };

        </script>
    @endpush

@endif
{{-- End of Extra CSS and JS --}}
{{-- ########################################## --}}
