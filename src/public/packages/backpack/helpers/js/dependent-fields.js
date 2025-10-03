(function($) {
    'use strict';

    if (typeof $ === 'undefined') {
        return;
    }

    var DependentFieldManager = (function() {
        var contexts = [];
        var observer = null;
        var driverEventBound = false;

        function init() {
            if (!driverEventBound) {
                $(document).on('change', '[data-dep-source]', onDriverChanged);
                driverEventBound = true;
            }

            if (!observer && typeof MutationObserver !== 'undefined') {
                observer = new MutationObserver(function() {
                    contexts.forEach(function(context) {
                        if (!context.driverElement || !document.body.contains(context.driverElement)) {
                            ensureDriver(context, true);
                        }
                    });
                });

                if (document.body) {
                    observer.observe(document.body, { childList: true, subtree: true });
                }
            }
        }

        function register(element) {
            init();

            var $field = $(element);

            if (!$field.length || $field.data('dependentFieldInitialized')) {
                var context = $field.data('dependentFieldContext');
                if (context) {
                    ensureDriver(context, true);
                    triggerUpdate(context);
                }
                return;
            }

            var dependency = $field.data('depends-on');
            if (!dependency) {
                return;
            }

            var context = {
                fieldElement: $field.get(0),
                $field: $field,
                dependency: dependency,
                scope: $field.data('dep-scope') || 'auto',
                map: parseMap($field.data('dep-map')),
                url: $field.data('dep-url') || null,
                driverElement: null,
                lastDriverValue: null,
                placeholder: capturePlaceholder($field),
                pendingRequest: null,
                pendingTimeout: null
            };

            $field.data('dependentFieldInitialized', true);
            $field.data('dependentFieldContext', context);

            contexts.push(context);
            ensureDriver(context, true);
            triggerUpdate(context);
        }

        function onDriverChanged(event) {
            contexts = contexts.filter(function(context) {
                if (!context.fieldElement || !document.body.contains(context.fieldElement)) {
                    return false;
                }
                return true;
            });

            contexts.forEach(function(context) {
                if (!context.fieldElement || context.fieldElement.disabled) {
                    return;
                }

                if (!context.driverElement) {
                    ensureDriver(context, true);
                }

                if (context.driverElement === event.target) {
                    triggerUpdate(context);
                }
            });
        }

        function ensureDriver(context, searchGlobal) {
            if (!context.fieldElement || !document.body.contains(context.fieldElement)) {
                return null;
            }

            if (context.driverElement && document.body.contains(context.driverElement)) {
                return context.driverElement;
            }

            var selector = '[data-dep-source="' + cssEscape(context.dependency) + '"]';
            var $scope;

            var rowNumber = context.$field.attr('data-row-number') || context.$field.closest('[data-row-number]').attr('data-row-number');
            if (rowNumber) {
                var repeatableSelector = '[data-repeatable-input-name="' + cssEscape(context.dependency) + '"][data-row-number="' + cssEscape(rowNumber) + '"]' +
                    ',[data-repeatable-input-name="' + cssEscape(context.dependency) + '[]"][data-row-number="' + cssEscape(rowNumber) + '"]';
                var $repeatableDriver = $(repeatableSelector).filter(function() {
                    return document.body.contains(this);
                }).first();
                if ($repeatableDriver.length) {
                    context.driverElement = $repeatableDriver.get(0);
                    return context.driverElement;
                }
            }

            if (context.scope && context.scope !== 'auto') {
                $scope = context.$field.closest(context.scope);
                if ($scope && $scope.length) {
                    var $driverScoped = $scope.find(selector).first();
                    if ($driverScoped.length) {
                        context.driverElement = $driverScoped.get(0);
                        return context.driverElement;
                    }
                }
            }

            if (searchGlobal) {
                var $closestForm = context.$field.closest('form');
                if ($closestForm.length) {
                    var $driverForm = $closestForm.find(selector).filter(function() {
                        return document.body.contains(this);
                    }).first();
                    if ($driverForm.length) {
                        context.driverElement = $driverForm.get(0);
                        return context.driverElement;
                    }
                }

                var $driver = $(selector).filter(function() {
                    if (!document.body.contains(this)) {
                        return false;
                    }
                    if (context.scope === 'auto') {
                        return true;
                    }
                    return !context.$field.closest(context.scope).length;
                }).first();

                if ($driver.length) {
                    context.driverElement = $driver.get(0);
                    return context.driverElement;
                }
            }

            context.driverElement = null;
            return null;
        }

        function triggerUpdate(context) {
            if (!context.fieldElement || !document.body.contains(context.fieldElement)) {
                return;
            }

            if (context.pendingTimeout) {
                clearTimeout(context.pendingTimeout);
                context.pendingTimeout = null;
            }

            var driver = ensureDriver(context, true);
            if (!driver) {
                context.pendingTimeout = setTimeout(function() {
                    triggerUpdate(context);
                }, 250);
                return;
            }

            var driverValue = getDriverValue($(driver));

            if (context.lastDriverValue !== null && valuesEqual(context.lastDriverValue, driverValue)) {
                return;
            }

            context.lastDriverValue = cloneValue(driverValue);
            loadOptions(context, driverValue);
        }

        function loadOptions(context, driverValue) {
            if (context.pendingRequest && context.pendingRequest.abort) {
                context.pendingRequest.abort();
            }

            var $field = context.$field;
            $field.trigger('dependent.start');

            var request;
            if (context.map) {
                request = $.Deferred();
                request.resolve(resolveFromMap(context.map, driverValue));
            } else if (context.url) {
                var url = buildUrl(context.url, driverValue);
                request = $.ajax({
                    url: url,
                    method: 'GET',
                    dataType: 'json'
                });
            } else {
                request = $.Deferred();
                request.resolve([]);
            }

            context.pendingRequest = request;

            request.done(function(data) {
                applyOptions(context, data);
                $field.trigger('dependent.success', [data]);
            }).fail(function(jqXHR, status) {
                if (status !== 'abort') {
                    console.error('Dependent field request failed for', context.dependency, status);
                    $field.trigger('dependent.error', [status]);
                }
            }).always(function() {
                context.pendingRequest = null;
                $field.trigger('dependent.finish');
            });
        }

        function applyOptions(context, data) {
            var $field = context.$field;
            if (!$field.length) {
                return;
            }

            var previousLabels = {};
            $field.find('option').each(function() {
                var value = $(this).attr('value');
                if (typeof value === 'undefined') {
                    value = '';
                }
                previousLabels[value] = $(this).text();
            });

            var currentValue = getFieldValue($field);
            var currentValues = Array.isArray(currentValue) ? currentValue.slice() : (currentValue === null ? [] : [currentValue]);

            var options = normalizeOptions(data);
            var optionValues = {};

            $field.empty();

            if (context.placeholder) {
                $field.append(context.placeholder.clone());
            }

            options.forEach(function(option) {
                if (typeof option.value === 'undefined' || option.value === null) {
                    option.value = '';
                }
                var isSelected = currentValues.indexOf(option.value.toString()) !== -1;
                var optionElement = new Option(option.label, option.value, isSelected, isSelected);
                optionValues[option.value] = true;
                $field.append(optionElement);
            });

            currentValues.forEach(function(value) {
                if (value === null || typeof value === 'undefined' || value === '') {
                    return;
                }
                var key = value.toString();
                if (!optionValues[key]) {
                    var label = previousLabels[key] || key;
                    var optionElement = new Option(label, key, true, true);
                    $field.append(optionElement);
                }
            });

            setFieldValue($field, currentValues);
        }

        function parseMap(map) {
            if (!map) {
                return null;
            }

            if (typeof map === 'object') {
                return map;
            }

            try {
                return JSON.parse(map);
            } catch (error) {
                console.error('Invalid data-dep-map JSON', error);
                return null;
            }
        }

        function capturePlaceholder($field) {
            var placeholderOption = null;
            var $firstEmpty = $field.find('option[value=""]').first();
            if ($firstEmpty.length) {
                placeholderOption = $firstEmpty.clone();
            } else {
                var placeholderText = $field.attr('data-placeholder') || $field.attr('placeholder');
                if (placeholderText) {
                    placeholderOption = $('<option></option>').attr('value', '').text(placeholderText);
                }
            }
            return placeholderOption;
        }

        function resolveFromMap(map, driverValue) {
            var values = normalizeToArray(driverValue);
            var resolved = {};
            var matched = false;

            values.forEach(function(value) {
                var key = value === null || typeof value === 'undefined' ? '' : value.toString();
                if (!key) {
                    return;
                }

                if (Object.prototype.hasOwnProperty.call(map, key)) {
                    $.extend(resolved, map[key]);
                    matched = true;
                    return;
                }

                Object.keys(map).forEach(function(mapKey) {
                    if (mapKey.charAt(0) === '[' && mapKey.charAt(mapKey.length - 1) === ']') {
                        var tokens = mapKey.substring(1, mapKey.length - 1).split(/\s*,\s*/);
                        if (tokens.indexOf(key) !== -1) {
                            $.extend(resolved, map[mapKey]);
                            matched = true;
                        }
                    }
                });
            });

            if (!matched && Object.prototype.hasOwnProperty.call(map, '*')) {
                $.extend(resolved, map['*']);
            }

            return resolved;
        }

        function buildUrl(urlTemplate, driverValue) {
            var value = '';
            if (Array.isArray(driverValue)) {
                value = driverValue.join(',');
            } else if (driverValue !== null && typeof driverValue !== 'undefined') {
                value = driverValue;
            }
            return urlTemplate.replace('{value}', encodeURIComponent(value));
        }

        function normalizeToArray(value) {
            if (Array.isArray(value)) {
                return value.slice();
            }
            if (value === null || typeof value === 'undefined' || value === '') {
                return [];
            }
            return [value];
        }

        function normalizeOptions(data) {
            var options = [];

            if (!data) {
                return options;
            }

            if ($.isArray(data)) {
                data.forEach(function(item) {
                    if (typeof item === 'string' || typeof item === 'number') {
                        options.push({ value: item, label: item });
                    } else if ($.isPlainObject(item)) {
                        var value = item.value;
                        if (typeof value === 'undefined') {
                            value = item.id;
                        }
                        var label = item.label;
                        if (typeof label === 'undefined') {
                            label = item.text || item.name || value;
                        }
                        options.push({ value: value, label: label });
                    }
                });
            } else if ($.isPlainObject(data)) {
                Object.keys(data).forEach(function(key) {
                    var label = data[key];
                    if ($.isPlainObject(label)) {
                        var value = label.value;
                        if (typeof value === 'undefined') {
                            value = key;
                        }
                        var text = label.label || label.text || label.name || value;
                        options.push({ value: value, label: text });
                    } else {
                        options.push({ value: key, label: label });
                    }
                });
            }

            return options;
        }

        function getDriverValue($driver) {
            if (!$driver.length) {
                return null;
            }
            var value = $driver.val();
            if ($driver.prop('multiple')) {
                return value || [];
            }
            return value;
        }

        function getFieldValue($field) {
            if (!$field.length) {
                return null;
            }
            var value = $field.val();
            if ($field.prop('multiple')) {
                return value || [];
            }
            return value;
        }

        function setFieldValue($field, value) {
            if (!$field.length) {
                return;
            }
            if ($field.prop('multiple')) {
                $field.val(value).trigger('change');
            } else {
                var selectedValue = Array.isArray(value) ? (value.length ? value[0] : null) : value;
                if (selectedValue === null) {
                    selectedValue = '';
                }
                $field.val(selectedValue).trigger('change');
            }
        }

        function valuesEqual(a, b) {
            if (Array.isArray(a) && Array.isArray(b)) {
                if (a.length !== b.length) {
                    return false;
                }
                for (var i = 0; i < a.length; i++) {
                    if (a[i] !== b[i]) {
                        return false;
                    }
                }
                return true;
            }
            return a === b;
        }

        function cloneValue(value) {
            if (Array.isArray(value)) {
                return value.slice();
            }
            return value;
        }

        function cssEscape(value) {
            if (value === null || typeof value === 'undefined') {
                return '';
            }
            if (window.CSS && window.CSS.escape) {
                return window.CSS.escape(String(value));
            }
            return String(value).replace(/"/g, '\\"');
        }

        return {
            register: register
        };
    })();

    window.bpFieldInitDependentOptions = function(element) {
        DependentFieldManager.register(element);
    };

})(window.jQuery);

