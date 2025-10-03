(function ($) {
    if (!$) {
        return;
    }

    var DependentRegistry = {
        satellites: [],
        driverListeners: {},

        registerSatellite: function ($element) {
            if (!$element || !$element.length) {
                return;
            }

            if ($element.data('bp-dependent-initialized')) {
                // Already initialized for this element.
                this.updateSatellite($element.data('bp-dependent-config'));
                return;
            }

            var dependsAttr = ($element.attr('data-depends-on') || '').trim();
            if (!dependsAttr) {
                return;
            }

            var dependsOn = dependsAttr
                .split(',')
                .map(function (item) {
                    return item.trim();
                })
                .filter(Boolean);

            if (!dependsOn.length) {
                return;
            }

            var scope = ($element.attr('data-dep-scope') || '').trim();
            if (!scope) {
                scope = 'auto';
            }

            var mapAttr = $element.attr('data-dep-map');
            var map = null;
            if (mapAttr) {
                try {
                    map = JSON.parse(mapAttr);
                } catch (error) {
                    console.warn('Backpack DependentFields: invalid data-dep-map JSON for', $element.get(0), error);
                }
            }

            var url = $element.attr('data-dep-url') || null;

            var config = {
                $element: $element,
                dependsOn: dependsOn,
                scope: scope,
                map: map,
                url: url,
                observer: null,
                lastRequest: null,
                lastOptions: collectCurrentOptions($element)
            };

            $element.data('bp-dependent-initialized', true);
            $element.data('bp-dependent-config', config);

            this.satellites.push(config);

            var self = this;
            dependsOn.forEach(function (driverName) {
                self.ensureDriverListener(driverName);
            });

            this.observeDrivers(config);
            this.updateSatellite(config);
        },

        ensureDriverListener: function (driverName) {
            if (this.driverListeners[driverName]) {
                return;
            }

            var selector = this.buildDriverSelector(driverName);
            var self = this;

            $(document).on('change.bpDependentFields', selector, function (event) {
                var driverElement = event.currentTarget;
                self.handleDriverChange(driverName, driverElement);
            });

            // Some Backpack fields trigger custom events after AJAX loads.
            // Listen to common ones to refresh dependent satellites.
            $(document).on('bp.field.refresh.bpDependentFields', selector, function (event) {
                var driverElement = event.currentTarget;
                self.handleDriverChange(driverName, driverElement);
            });

            this.driverListeners[driverName] = true;
        },

        buildDriverSelector: function (driverName) {
            var hasCssEscape = typeof CSS !== 'undefined' && typeof CSS.escape === 'function';
            var escaped = hasCssEscape
                ? CSS.escape(driverName)
                : driverName.replace(/([ #.;?+*~\':"!^$\[\]()=>|\/])/g, '\\$1');
            return '[data-dep-source="' + escaped + '"]' + ',[name="' + escaped + '"]';
        },

        handleDriverChange: function (driverName, driverElement) {
            var self = this;
            this.satellites.forEach(function (config) {
                if (config.dependsOn.indexOf(driverName) === -1) {
                    return;
                }

                if (!self.isDriverInScope(config, driverElement)) {
                    return;
                }

                self.updateSatellite(config);
            });
        },

        observeDrivers: function (config) {
            if (typeof MutationObserver === 'undefined') {
                return;
            }

            var selectorList = config.dependsOn.map(this.buildDriverSelector.bind(this));
            var scopeNode = this.getScopeRoot(config);

            if (!scopeNode) {
                return;
            }

            var observer = new MutationObserver(function (mutations) {
                var shouldUpdate = false;

                mutations.forEach(function (mutation) {
                    Array.prototype.forEach.call(mutation.addedNodes || [], function (node) {
                        if (shouldUpdate || node.nodeType !== 1) {
                            return;
                        }

                        selectorList.forEach(function (selector) {
                            if (node.matches && node.matches(selector)) {
                                shouldUpdate = true;
                            } else if ($(node).find(selector).length) {
                                shouldUpdate = true;
                            }
                        });
                    });
                });

                if (shouldUpdate) {
                    DependentRegistry.updateSatellite(config);
                }
            });

            observer.observe(scopeNode, { childList: true, subtree: true });
            config.observer = observer;
        },

        updateSatellite: function (config) {
            if (!config || !config.$element || !config.$element.length) {
                return;
            }

            var driverValues = this.collectDriverValues(config);

            if (driverValues === null) {
                this.disableSatellite(config);
                return;
            }

            this.enableSatellite(config);

            if (config.lastRequest && typeof config.lastRequest.abort === 'function') {
                config.lastRequest.abort();
            }

            var self = this;

            if (config.url) {
                var requestUrl = this.interpolateUrl(config.url, driverValues, config.dependsOn);
                config.lastRequest = $.ajax({
                    url: requestUrl,
                    method: 'GET',
                    dataType: 'json'
                })
                    .done(function (data) {
                        config.lastOptions = data;
                        self.applyOptions(config, data, driverValues);
                    })
                    .fail(function (jqXHR, textStatus) {
                        if (textStatus !== 'abort') {
                            console.warn('Backpack DependentFields: request failed for', requestUrl, jqXHR);
                        }
                    });

                return;
            }

            if (config.map) {
                var optionsFromMap = this.resolveFromMap(config.map, config.dependsOn, driverValues);
                config.lastOptions = optionsFromMap;
                this.applyOptions(config, optionsFromMap, driverValues);
            }
        },

        collectDriverValues: function (config) {
            var values = {};
            var self = this;
            var missingDriver = false;

            config.dependsOn.forEach(function (driverName) {
                if (missingDriver) {
                    return;
                }

                var $driver = self.findDriver(config, driverName);
                if (!$driver || !$driver.length) {
                    missingDriver = true;
                    return;
                }

                var value = self.getFieldValue($driver);
                values[driverName] = value;
            });

            return missingDriver ? null : values;
        },

        findDriver: function (config, driverName) {
            var root = $(this.getScopeRoot(config) || document);
            var selector = this.buildDriverSelector(driverName);
            var $driver = root.find(selector).not(config.$element);

            if (!$driver.length && root.get(0) !== document) {
                $driver = $(selector).not(config.$element);
            }

            return $driver;
        },

        isDriverInScope: function (config, driverElement) {
            var scopeRoot = this.getScopeRoot(config);
            if (!scopeRoot) {
                return true;
            }

            if (scopeRoot === document) {
                return true;
            }

            return scopeRoot.contains(driverElement);
        },

        getScopeRoot: function (config) {
            var scope = config.scope;
            var element = config.$element.get(0);

            if (!scope || scope === 'auto') {
                var form = config.$element.closest('form');
                if (form.length) {
                    return form.get(0);
                }
                return document;
            }

            var $root = config.$element.closest(scope);
            if ($root.length) {
                return $root.get(0);
            }

            var $form = config.$element.closest('form');
            if ($form.length) {
                return $form.get(0);
            }

            return document;
        },

        getFieldValue: function ($field) {
            if (!$field.length) {
                return null;
            }

            var isSelectMultiple = $field.is('select[multiple]');
            var value = $field.val();

            if (isSelectMultiple && value === null) {
                return [];
            }

            return value;
        },

        disableSatellite: function (config) {
            if (!config.$element.prop('disabled')) {
                config.$element.prop('disabled', true);
                config.$element.trigger('change');
            }
        },

        enableSatellite: function (config) {
            if (config.$element.prop('disabled')) {
                config.$element.prop('disabled', false);
            }
        },

        interpolateUrl: function (url, values, dependsOn) {
            return url.replace(/\{([^}]+)\}/g, function (match, token) {
                var key = token === 'value' ? dependsOn[0] : token;
                var raw = values[key];
                if (raw === undefined || raw === null) {
                    return '';
                }

                if (Array.isArray(raw)) {
                    return encodeURIComponent(raw.join(','));
                }

                return encodeURIComponent(raw);
            });
        },

        resolveFromMap: function (map, dependsOn, values) {
            var value = values[dependsOn[0]];
            var normalized = this.normalizeValue(value);

            if (map.hasOwnProperty(normalized)) {
                return map[normalized];
            }

            var fallback = null;
            Object.keys(map).forEach(function (key) {
                if (fallback !== null) {
                    return;
                }

                if (key === '*') {
                    fallback = map[key];
                    return;
                }

                if (key.charAt(0) === '[' && key.charAt(key.length - 1) === ']') {
                    var list = key.substring(1, key.length - 1).split(',').map(function (item) {
                        return item.trim();
                    });
                    if (list.indexOf(normalized) !== -1) {
                        fallback = map[key];
                    }
                }
            });

            return fallback || {};
        },

        normalizeValue: function (value) {
            if (Array.isArray(value)) {
                if (!value.length) {
                    return '';
                }
                return value[0];
            }
            return value === null || typeof value === 'undefined' ? '' : String(value);
        },

        applyOptions: function (config, options, driverValues) {
            var $field = config.$element;
            var previousValue = $field.val();
            var previousTexts = {};

            $field.find('option:selected').each(function () {
                previousTexts[$(this).attr('value')] = $(this).text();
            });

            $field.empty();

            var addedValues = this.populateOptions($field, options, previousValue, previousTexts);

            this.ensureExistingSelections($field, previousValue, previousTexts, addedValues);

            if (previousValue !== null && typeof previousValue !== 'undefined') {
                this.restoreValue($field, previousValue);
            }

            if ($field.hasClass('select2-hidden-accessible')) {
                $field.trigger('change');
            } else {
                $field.trigger('change');
            }
        },

        populateOptions: function ($field, options, previousValue, previousTexts) {
            var self = this;
            var addedValues = [];

            if (!options) {
                options = {};
            }

            if (Array.isArray(options)) {
                options.forEach(function (option) {
                    if (typeof option === 'object' && option !== null) {
                        var value = option.value;
                        var label = option.label || option.text || value;
                        self.appendOption($field, value, label, previousValue, previousTexts);
                        addedValues.push(String(value));
                    }
                });
                return addedValues;
            }

            Object.keys(options).forEach(function (key) {
                var item = options[key];
                if ($.isPlainObject(item) && !self.isOptionLike(item)) {
                    var $group = $('<optgroup>').attr('label', key);
                    Object.keys(item).forEach(function (value) {
                        var label = item[value];
                        var selected = self.shouldSelectValue(previousValue, value);
                        var $option = $('<option>').attr('value', value).text(label);
                        if (selected) {
                            $option.prop('selected', true);
                        }
                        $group.append($option);
                        addedValues.push(String(value));
                    });
                    $field.append($group);
                } else {
                    if (self.isOptionLike(item)) {
                        var value = item.value;
                        var label = item.label || item.text || value;
                        self.appendOption($field, value, label, previousValue, previousTexts);
                        addedValues.push(String(value));
                    } else {
                        self.appendOption($field, key, item, previousValue, previousTexts);
                        addedValues.push(String(key));
                    }
                }
            });
            return addedValues;
        },

        isOptionLike: function (item) {
            return Object.prototype.hasOwnProperty.call(item, 'value');
        },

        appendOption: function ($field, value, label, previousValue, previousTexts) {
            var selected = this.shouldSelectValue(previousValue, value);
            var $option = $('<option>').attr('value', value).text(label);

            if (selected) {
                $option.prop('selected', true);
            }

            $field.append($option);

            var normalized = String(value);

            if (!selected && previousTexts && previousTexts.hasOwnProperty(normalized)) {
                $option.text(previousTexts[normalized]);
            }
        },

        ensureExistingSelections: function ($field, previousValue, previousTexts, addedValues) {
            if (previousValue === null || typeof previousValue === 'undefined') {
                return;
            }

            var existing = Array.isArray(previousValue) ? previousValue : [previousValue];
            var added = Array.isArray(addedValues) ? addedValues : [];

            existing.forEach(function (value) {
                if (value === null || typeof value === 'undefined' || value === '') {
                    return;
                }

                var normalized = String(value);
                if (added.indexOf(normalized) !== -1) {
                    return;
                }

                if ($field.find('option').filter(function () {
                    return String($(this).attr('value')) === normalized;
                }).length) {
                    return;
                }

                var text = previousTexts && previousTexts.hasOwnProperty(normalized)
                    ? previousTexts[normalized]
                    : normalized;
                var $option = $('<option>').attr('value', normalized).text(text).prop('selected', true);
                $field.append($option);
                added.push(normalized);
            });
        },

        shouldSelectValue: function (previousValue, value) {
            if (Array.isArray(previousValue)) {
                return previousValue.map(String).indexOf(String(value)) !== -1;
            }

            return String(previousValue) === String(value);
        },

        restoreValue: function ($field, previousValue) {
            if (Array.isArray(previousValue)) {
                var values = previousValue.map(String);
                $field.val(values);
            } else if (previousValue !== null && typeof previousValue !== 'undefined') {
                $field.val(String(previousValue));
            }
        }
    };

    function collectCurrentOptions($field) {
        var result = {};
        $field.find('option').each(function () {
            var $option = $(this);
            result[$option.attr('value')] = $option.text();
        });
        return result;
    }

    window.bpFieldInitDependentOptions = function (element) {
        var $element = element instanceof $ ? element : $(element);
        DependentRegistry.registerSatellite($element);
    };

    $(document).ready(function () {
        $('[data-init-function="bpFieldInitDependentOptions"]').each(function () {
            window.bpFieldInitDependentOptions($(this));
        });
    });
})(window.jQuery);
