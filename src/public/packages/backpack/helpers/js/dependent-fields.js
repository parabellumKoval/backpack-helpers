(function($) {
    'use strict';

    if (typeof $ === 'undefined') {
        return;
    }

    var NAMESPACE = '.bpDependentOptions';

    function trim(value) {
        return value == null ? '' : String(value).trim();
    }

    function parseDepends(raw) {
        if (!raw) {
            return [];
        }

        if (Array.isArray(raw)) {
            return raw.map(trim).filter(Boolean);
        }

        if (typeof raw === 'string') {
            var value = raw.trim();

            if (!value) {
                return [];
            }

            if (value.charAt(0) === '[' && value.charAt(value.length - 1) === ']') {
                try {
                    var parsed = JSON.parse(value);
                    return parseDepends(parsed);
                } catch (error) {
                    // ignore and fallback to split
                }
            }

            return value.split(/[,|]/).map(trim).filter(Boolean);
        }

        return [];
    }

    function parseJsonAttribute(raw) {
        if (!raw) {
            return null;
        }

        if (typeof raw !== 'string') {
            return raw;
        }

        var value = raw.replace(/&quot;/g, '\"');

        try {
            return JSON.parse(value);
        } catch (error) {
            return null;
        }
    }

    function escapeAttributeValue(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return String(value).replace(/"/g, '\\"');
    }

    function collectContexts($element, scopeAttr) {
        var contexts = [];

        if (scopeAttr && scopeAttr !== 'auto') {
            var $scoped = $element.closest(scopeAttr);
            if ($scoped.length) {
                contexts.push($scoped);
            }
        }

        var $repeatableElement = $element.closest('[data-repeatable-element]');
        if ($repeatableElement.length) {
            contexts.push($repeatableElement);
        }

        var $repeatableField = $element.closest('[data-repeatable]');
        if ($repeatableField.length) {
            contexts.push($repeatableField);
        }

        var $formGroup = $element.closest('.form-group');
        if ($formGroup.length) {
            contexts.push($formGroup);
        }

        var $form = $element.closest('form');
        if ($form.length) {
            contexts.push($form);
        }

        contexts.push($(document));

        var unique = [];
        $.each(contexts, function(index, item) {
            if (!item || !item.length) {
                return;
            }
            if ($.inArray(item, unique) === -1) {
                unique.push(item);
            }
        });

        return unique;
    }

    function sharesContext($satellite, $driver, scopeAttr) {
        if (!$driver || !$driver.length) {
            return false;
        }

        if (scopeAttr && scopeAttr !== 'auto') {
            var $satScope = $satellite.closest(scopeAttr);
            var $driverScope = $driver.closest(scopeAttr);

            if ($satScope.length && $driverScope.length && $satScope[0] !== $driverScope[0]) {
                return false;
            }
        }

        var $satRepeatable = $satellite.closest('[data-repeatable-element]');
        var $driverRepeatable = $driver.closest('[data-repeatable-element]');

        if ($satRepeatable.length || $driverRepeatable.length) {
            return $satRepeatable.length && $driverRepeatable.length && $satRepeatable[0] === $driverRepeatable[0];
        }

        var $satForm = $satellite.closest('form');
        var $driverForm = $driver.closest('form');

        if ($satForm.length && $driverForm.length && $satForm[0] !== $driverForm[0]) {
            return false;
        }

        return true;
    }

    function findDriver($satellite, driverName, scopeAttr, hint) {
        if (hint && hint.length && sharesContext($satellite, hint, scopeAttr)) {
            return hint;
        }

        var selectors = [];
        selectors.push('[data-dep-source="' + escapeAttributeValue(driverName) + '"]');
        selectors.push('[name="' + escapeAttributeValue(driverName) + '"]');
        selectors.push('[data-repeatable-input-name="' + escapeAttributeValue(driverName) + '"]');

        var contexts = collectContexts($satellite, scopeAttr);

        for (var c = 0; c < contexts.length; c++) {
            var $context = contexts[c];
            for (var s = 0; s < selectors.length; s++) {
                var $found = $context.find(selectors[s]).filter(function() {
                    return this !== $satellite[0] && sharesContext($satellite, $(this), scopeAttr);
                });

                if ($found.length) {
                    return $found.first();
                }
            }
        }

        return $();
    }

    function getDriverValue($driver) {
        if (!$driver || !$driver.length) {
            return null;
        }

        var value = $driver.val();

        if (Array.isArray(value)) {
            return value.filter(function(item) {
                return item !== null && item !== undefined && item !== '';
            }).map(function(item) {
                return String(item);
            });
        }

        if (value === undefined || value === null || value === '') {
            return '';
        }

        return String(value);
    }

    function buildSignature(valuesMap, orderedNames) {
        var normalized = {};
        orderedNames.forEach(function(name) {
            var value = valuesMap[name];
            if (Array.isArray(value)) {
                normalized[name] = value.slice().map(function(item) {
                    return String(item);
                });
            } else if (value === undefined) {
                normalized[name] = null;
            } else {
                normalized[name] = value === null ? null : String(value);
            }
        });

        return JSON.stringify(normalized);
    }

    function getSelectedValues($field) {
        if (!$field || !$field.length) {
            return [];
        }

        var value = $field.val();
        if (Array.isArray(value)) {
            return value.filter(function(item) {
                return item !== null && item !== undefined && item !== '';
            }).map(function(item) {
                return String(item);
            });
        }

        if (value === undefined || value === null || value === '') {
            return [];
        }

        return [String(value)];
    }

    function getExistingOptionLabels($field) {
        var map = {};
        if (!$field || !$field.length) {
            return map;
        }

        $field.find('option').each(function() {
            var $option = $(this);
            var optionValue = $option.attr('value');
            if (optionValue === undefined) {
                return;
            }
            map[String(optionValue)] = $option.text();
        });

        return map;
    }

    function getPlaceholderOptions($field) {
        if (!$field || !$field.length) {
            return [];
        }

        var stored = $field.data('depPlaceholderOptions');
        if (stored) {
            return stored;
        }

        var keepers = [];
        $field.find('option').each(function() {
            var $option = $(this);
            var keep = $option.attr('data-keep-on-refresh') !== undefined || $option.data('keepOnRefresh');
            keep = keep || $option.attr('value') === '' || $option.data('placeholder') || $option.attr('data-placeholder') !== undefined;
            keep = keep || ($option.is('[disabled]') && !$option.attr('value'));

            if (keep) {
                keepers.push(this.cloneNode(true));
            }
        });

        $field.data('depPlaceholderOptions', keepers);
        return keepers;
    }

    function normalizeOptions(raw, existingLabels, previousValues) {
        var list = [];
        var seen = {};

        function push(value, label) {
            if (value === undefined || value === null) {
                return;
            }

            var stringValue = String(value);
            if (seen[stringValue]) {
                return;
            }

            list.push({ value: stringValue, label: label != null ? String(label) : stringValue });
            seen[stringValue] = true;
        }

        if (Array.isArray(raw)) {
            raw.forEach(function(item) {
                if (item == null) {
                    return;
                }

                if (typeof item === 'object' && !Array.isArray(item)) {
                    var value = item.value;
                    if (value === undefined) {
                        value = item.id !== undefined ? item.id : item.key;
                    }
                    var label = item.label;
                    if (label === undefined) {
                        label = item.text !== undefined ? item.text : item.name;
                    }
                    if (label === undefined) {
                        label = existingLabels && value !== undefined ? existingLabels[String(value)] : undefined;
                    }
                    push(value, label);
                    return;
                }

                if (Array.isArray(item)) {
                    push(item[0], item[1] !== undefined ? item[1] : item[0]);
                    return;
                }

                push(item, item);
            });
        } else if (raw && typeof raw === 'object') {
            Object.keys(raw).forEach(function(key) {
                push(key, raw[key]);
            });
        }

        if (previousValues && previousValues.length) {
            previousValues.forEach(function(value) {
                if (!seen[value]) {
                    var label = existingLabels && existingLabels[value] !== undefined ? existingLabels[value] : value;
                    push(value, label);
                }
            });
        }

        return list;
    }

    function applyNormalizedOptions($field, normalized, previousValues) {
        if (!$field || !$field.length) {
            return;
        }

        var event = $.Event('bp.dependentOptions.beforeUpdate');
        $field.trigger(event, { options: normalized });
        if (event.isDefaultPrevented()) {
            return;
        }

        if (!$field.is('select')) {
            // Attempt to find a select element inside the same field wrapper.
            var $wrapper = $field.closest('.form-group, [data-field-name]');
            var $select = $wrapper.find('select').first();
            if ($select.length) {
                $field = $select;
            }
        }

        if (!$field.is('select')) {
            return;
        }

        var placeholders = getPlaceholderOptions($field);
        var documentFragment = document.createDocumentFragment();
        placeholders.forEach(function(node) {
            documentFragment.appendChild(node.cloneNode(true));
        });

        var valuesSet = {};
        normalized.forEach(function(option) {
            valuesSet[option.value] = true;
            var optionElement = new Option(option.label, option.value, false, false);
            documentFragment.appendChild(optionElement);
        });

        var $existingSelectedOptions = $field.find('option:selected');

        $field.empty().append(documentFragment);

        var desiredValues = [];
        if (previousValues && previousValues.length) {
            previousValues.forEach(function(value) {
                if (valuesSet[value]) {
                    desiredValues.push(value);
                }
            });
        }

        if ($field.prop('multiple')) {
            $field.val(desiredValues);
        } else if (desiredValues.length) {
            $field.val(desiredValues[0]);
        } else if ($existingSelectedOptions.length) {
            var fallback = $existingSelectedOptions.first().val();
            if (fallback !== undefined && valuesSet[fallback]) {
                $field.val(fallback);
            } else {
                $field.val('');
            }
        } else {
            $field.val('');
        }

        if ($field.hasClass('select2-hidden-accessible')) {
            $field.trigger('change.select2');
        } else {
            $field.trigger('change');
        }

        $field.trigger('bp.dependentOptions.afterUpdate', { options: normalized });
    }

    function buildSearchOrder(valuesMap, driverNames) {
        var order = [];
        var normalized = driverNames.map(function(name) {
            var value = valuesMap[name];
            if (Array.isArray(value)) {
                return value.length ? String(value[0]) : '';
            }
            return value === undefined || value === null ? '' : String(value);
        });

        normalized.forEach(function(value) {
            if (value) {
                order.push(value);
            }
        });

        if (normalized.length > 1) {
            var joinPipe = normalized.join('|').trim();
            if (joinPipe) {
                order.push(joinPipe);
            }

            var joinComma = normalized.join(',').trim();
            if (joinComma) {
                order.push(joinComma);
            }

            var bracket = '[' + normalized.join(',') + ']';
            order.push(bracket);

            var json = {};
            driverNames.forEach(function(name, index) {
                json[name] = normalized[index];
            });
            order.push(JSON.stringify(json));
        }

        return order.filter(Boolean);
    }

    function resolveOptionsFromMap(map, valuesMap, driverNames) {
        if (!map) {
            return null;
        }

        var order = buildSearchOrder(valuesMap, driverNames);

        for (var i = 0; i < order.length; i++) {
            var key = order[i];
            if (map.hasOwnProperty(key)) {
                return map[key];
            }
        }

        for (var mapKey in map) {
            if (!map.hasOwnProperty(mapKey)) {
                continue;
            }

            var trimmed = trim(mapKey);
            if (trimmed === '*') {
                return map[mapKey];
            }

            if (trimmed.charAt(0) === '[' && trimmed.charAt(trimmed.length - 1) === ']') {
                var list = trimmed.substring(1, trimmed.length - 1).split(/[,|]/).map(trim).filter(Boolean);
                var values = [];
                Object.keys(valuesMap).forEach(function(name) {
                    var value = valuesMap[name];
                    if (Array.isArray(value)) {
                        values = values.concat(value.map(String));
                    } else if (value !== null && value !== undefined && value !== '') {
                        values.push(String(value));
                    }
                });

                var matches = list.some(function(item) {
                    return values.indexOf(item) !== -1;
                });

                if (matches) {
                    return map[mapKey];
                }
            } else if (order.indexOf(trimmed) !== -1) {
                return map[mapKey];
            }
        }

        if (map.hasOwnProperty('*')) {
            return map['*'];
        }

        return null;
    }

    function valueToString(value) {
        if (value === null || value === undefined) {
            return '';
        }

        if (Array.isArray(value)) {
            return value.map(function(item) {
                return item == null ? '' : String(item);
            }).join(',');
        }

        return String(value);
    }

    function buildUrl(template, valuesMap, driverNames) {
        if (!template) {
            return null;
        }

        var url = template;
        driverNames.forEach(function(name) {
            var token = '{' + name + '}';
            if (url.indexOf(token) !== -1) {
                url = url.split(token).join(encodeURIComponent(valueToString(valuesMap[name])));
            }
        });

        if (url.indexOf('{value}') !== -1) {
            var first = '';
            for (var i = 0; i < driverNames.length; i++) {
                var candidate = valuesMap[driverNames[i]];
                if (Array.isArray(candidate)) {
                    if (candidate.length) {
                        first = String(candidate[0]);
                        break;
                    }
                } else if (candidate !== undefined && candidate !== null && candidate !== '') {
                    first = String(candidate);
                    break;
                }
            }
            url = url.split('{value}').join(encodeURIComponent(first));
        }

        if (/{[^}]+}/.test(url)) {
            url = url.replace(/{[^}]+}/g, '');
        }

        var params = {};
        driverNames.forEach(function(name) {
            params[name] = valueToString(valuesMap[name]);
        });

        var query = $.param(params);
        if (query) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + query;
        }

        return url;
    }

    function cancelPendingRequest($field) {
        var pending = $field.data('depPendingRequest');
        if (pending && typeof pending.abort === 'function') {
            pending.abort();
        }
        $field.removeData('depPendingRequest');
    }

    function updateSatellite($field, options) {
        if (!$field || !$field.length) {
            return;
        }

        var dependsAttr = $field.attr('data-depends-on') || $field.data('dependsOn');
        var driverNames = parseDepends(dependsAttr);
        if (!driverNames.length) {
            return;
        }

        var scopeAttr = $field.attr('data-dep-scope') || $field.data('depScope');
        var driverHints = options && options.changedDriver ? options.changedDriver : {};
        var valuesMap = {};
        var missingDriver = false;

        driverNames.forEach(function(name) {
            var hint = driverHints && driverHints[name] ? driverHints[name] : null;
            var $driver = findDriver($field, name, scopeAttr, hint);
            if ($driver && $driver.length) {
                valuesMap[name] = getDriverValue($driver);
            } else {
                missingDriver = true;
            }
        });

        if (missingDriver) {
            return;
        }

        var signature = buildSignature(valuesMap, driverNames);
        if (!options || !options.force) {
            var previousSignature = $field.data('depLastSignature');
            if (previousSignature === signature) {
                return;
            }
        }
        $field.data('depLastSignature', signature);

        cancelPendingRequest($field);

        var previousValues = getSelectedValues($field);
        var existingLabels = getExistingOptionLabels($field);

        var mapAttr = $field.data('depMapParsed');
        if (mapAttr === undefined) {
            mapAttr = parseJsonAttribute($field.attr('data-dep-map') || $field.data('depMap'));
            $field.data('depMapParsed', mapAttr);
        }

        if (mapAttr) {
            var rawOptions = resolveOptionsFromMap(mapAttr, valuesMap, driverNames);
            var normalized = normalizeOptions(rawOptions || [], existingLabels, previousValues);
            applyNormalizedOptions($field, normalized, previousValues);
            return;
        }

        var urlTemplate = $field.attr('data-dep-url') || $field.data('depUrl');
        if (urlTemplate) {
            var finalUrl = buildUrl(urlTemplate, valuesMap, driverNames);
            if (!finalUrl) {
                applyNormalizedOptions($field, normalizeOptions([], existingLabels, previousValues), previousValues);
                return;
            }

            var requestToken = signature + ':' + Date.now();
            $field.data('depActiveRequest', requestToken);

            var request = $.ajax({
                url: finalUrl,
                method: 'GET',
                dataType: 'json'
            });

            $field.data('depPendingRequest', request);

            request.done(function(response) {
                if ($field.data('depActiveRequest') !== requestToken) {
                    return;
                }
                var normalized = normalizeOptions(response, existingLabels, previousValues);
                applyNormalizedOptions($field, normalized, previousValues);
            }).fail(function() {
                if ($field.data('depActiveRequest') !== requestToken) {
                    return;
                }
                applyNormalizedOptions($field, normalizeOptions([], existingLabels, previousValues), previousValues);
            }).always(function() {
                if ($field.data('depActiveRequest') === requestToken) {
                    $field.removeData('depActiveRequest');
                }
                if ($field.data('depPendingRequest') === request) {
                    $field.removeData('depPendingRequest');
                }
            });

            return;
        }

        // Nothing to do without map or url.
    }

    function refreshSatellitesForDriver($driver, driverName, options) {
        var $targets = $('[data-depends-on]').filter(function() {
            var $field = $(this);
            if (!$field.data('depInitialized')) {
                return false;
            }
            var depends = parseDepends($field.attr('data-depends-on') || $field.data('dependsOn'));
            if ($.inArray(driverName, depends) === -1) {
                return false;
            }
            return sharesContext($field, $driver, $field.attr('data-dep-scope') || $field.data('depScope'));
        });

        var hints = {};
        hints[driverName] = $driver;

        $targets.each(function() {
            updateSatellite($(this), $.extend({}, options, { changedDriver: hints }));
        });
    }

    function scheduleInitialDriverTrigger($driver) {
        if (!$driver || !$driver.length) {
            return;
        }

        if ($driver.data('depDriverBootstrapped')) {
            return;
        }

        $driver.data('depDriverBootstrapped', true);
        setTimeout(function() {
            $driver.trigger('change');
        }, 0);
    }

    function handleDriverAppearance(element) {
        var $element = $(element);
        if (!$element.length) {
            return;
        }

        if ($element.is('[data-dep-source]')) {
            var driverName = $element.attr('data-dep-source');
            scheduleInitialDriverTrigger($element);
            refreshSatellitesForDriver($element, driverName, { force: true });
        }

        $element.find('[data-dep-source]').each(function() {
            var $driver = $(this);
            var driverName = $driver.attr('data-dep-source');
            scheduleInitialDriverTrigger($driver);
            refreshSatellitesForDriver($driver, driverName, { force: true });
        });
    }

    $(document).on('change' + NAMESPACE, '[data-dep-source]', function() {
        var $driver = $(this);
        var driverName = $driver.attr('data-dep-source');
        if (!driverName) {
            return;
        }
        refreshSatellitesForDriver($driver, driverName, { force: true });
    });

    if (window.MutationObserver) {
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        handleDriverAppearance(node);
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    $(function() {
        $('[data-dep-source]').each(function() {
            scheduleInitialDriverTrigger($(this));
        });
    });

    window.bpFieldInitDependentOptions = function(element) {
        var $field = $(element);
        if (!$field.length) {
            return;
        }

        if ($field.data('depInitialized')) {
            updateSatellite($field, { force: true });
            return;
        }

        $field.data('depInitialized', true);
        updateSatellite($field, { force: true });
    };
})(jQuery);
