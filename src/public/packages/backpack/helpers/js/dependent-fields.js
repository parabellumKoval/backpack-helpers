(function($) {
    'use strict';

    var instanceCounter = 0;

    function cssEscape(value) {
        if (typeof value !== 'string') {
            value = value + '';
        }

        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return value.replace(/[^a-zA-Z0-9_-]/g, function(character) {
            return '\\' + character;
        });
    }

    function uniqueScopes(scopes) {
        var seen = [];
        var unique = [];

        $.each(scopes, function(_, $scope) {
            if (!$scope || !$scope.length) {
                return;
            }

            var element = $scope.get(0);

            if (seen.indexOf(element) === -1) {
                seen.push(element);
                unique.push($scope);
            }
        });

        return unique;
    }

    function parseDependsOn(value) {
        if (!value) {
            return [];
        }

        if ($.isArray(value)) {
            return value;
        }

        return value
            .toString()
            .split(',')
            .map(function(item) {
                return $.trim(item);
            })
            .filter(function(item) {
                return item.length > 0;
            });
    }

    function parseMap(mapString) {
        if (!mapString) {
            return null;
        }

        if ($.isPlainObject(mapString)) {
            return mapString;
        }

        try {
            return JSON.parse(mapString);
        } catch (error) {
            console.warn('Backpack dependent fields: unable to parse map', error);
            return null;
        }
    }

    function getScopeRoots($field, scopeAttribute) {
        if (scopeAttribute && scopeAttribute !== 'auto') {
            var $custom = $field.closest(scopeAttribute);
            if ($custom.length) {
                return uniqueScopes([$custom]);
            }
        }

        var scopes = [];

        var $repeatable = $field.closest('[data-repeatable-identifier]');
        if ($repeatable.length) {
            scopes.push($repeatable);
        }

        var $conditional = $field.closest('[data-conditional-field]');
        if ($conditional.length) {
            scopes.push($conditional);
        }

        var $formGroup = $field.closest('.form-group');
        if ($formGroup.length) {
            scopes.push($formGroup);
        }

        var $form = $field.closest('form');
        if ($form.length) {
            scopes.push($form);
        }

        if (!scopes.length) {
            scopes.push($(document));
        }

        return uniqueScopes(scopes);
    }

    function buildDriverSelector(name) {
        var escaped = cssEscape(name);
        var selectors = [
            '[data-dep-source="' + escaped + '"]',
            '[data-repeatable-input-name="' + escaped + '"]',
            '[name="' + escaped + '"]',
            '[name="' + escaped + '[]"]',
            '[name$="[' + escaped + ']"]',
            '[name$="[' + escaped + '][]"]'
        ];

        return selectors.join(',');
    }

    function findDriverInScopes(scopes, name, $exclude) {
        var selector = buildDriverSelector(name);
        var $found = $();

        $.each(scopes, function(_, $scope) {
            if ($found.length) {
                return false;
            }

            $found = $scope.find(selector).filter(function() {
                return !$exclude || this !== $exclude.get(0);
            });
        });

        return $found.first();
    }

    function normalizeDriverValue(value) {
        if (value === null || typeof value === 'undefined') {
            return [];
        }

        if ($.isArray(value)) {
            return value.filter(function(item) {
                return item !== null && typeof item !== 'undefined' && item !== '';
            }).map(function(item) {
                return item + '';
            });
        }

        if (typeof value === 'string') {
            if (value === '') {
                return [];
            }

            return [value];
        }

        return [value + ''];
    }

    function normalizeOption(option, key) {
        if (typeof option === 'undefined' || option === null) {
            return null;
        }

        if ($.isPlainObject(option)) {
            var value = option.value;
            var label = option.label;

            if (typeof value === 'undefined') {
                value = typeof key !== 'undefined' ? key : option.id;
            }

            if (typeof label === 'undefined') {
                label = option.text || option.name;
            }

            if (typeof value === 'undefined' || typeof label === 'undefined') {
                return null;
            }

            var normalized = {
                value: value + '',
                label: label + ''
            };

            if (option.disabled) {
                normalized.disabled = true;
            }

            if (option.placeholder) {
                normalized.placeholder = true;
            }

            return normalized;
        }

        if (typeof option === 'string' || typeof option === 'number') {
            var normalizedOption = {
                value: (typeof key !== 'undefined' ? key + '' : option + ''),
                label: option + ''
            };

            return normalizedOption;
        }

        return null;
    }

    function normalizeOptionsCollection(options) {
        var normalized = [];

        if (!options && options !== 0) {
            return normalized;
        }

        if ($.isArray(options)) {
            $.each(options, function(index, option) {
                var normalizedOption = normalizeOption(option);

                if (normalizedOption) {
                    normalized.push(normalizedOption);
                }
            });

            return normalized;
        }

        if ($.isPlainObject(options)) {
            $.each(options, function(key, option) {
                var normalizedOption = normalizeOption(option, key);

                if (normalizedOption) {
                    normalized.push(normalizedOption);
                }
            });

            return normalized;
        }

        return normalized;
    }

    function escapeHtml(value) {
        return (value + '').replace(/[&<>"']/g, function(character) {
            switch (character) {
                case '&': return '&amp;';
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '"': return '&quot;';
                case "'": return '&#39;';
            }
            return character;
        });
    }

    function collectExistingOptionLabels($field) {
        var labels = {};

        $field.find('option').each(function() {
            labels[this.value] = $(this).text();
        });

        return labels;
    }

    function getPlaceholderData($field) {
        var placeholderText = $field.attr('data-placeholder');
        var $placeholderOption = $field.find('option').filter(function() {
            return this.value === '';
        }).first();

        if ($placeholderOption.length) {
            placeholderText = $placeholderOption.text();
        }

        if (typeof placeholderText === 'undefined') {
            return null;
        }

        return {
            value: '',
            label: placeholderText + '',
            placeholder: true
        };
    }

    function DependentField($field) {
        this.$field = $field;
        this.instanceId = ++instanceCounter;
        this.dependsOn = parseDependsOn($field.data('depends-on'));
        this.scopeAttribute = $field.data('dep-scope');
        this.map = parseMap($field.data('dep-map'));
        this.urlTemplate = $field.data('dep-url') || null;
        this.scopeRoots = getScopeRoots($field, this.scopeAttribute);
        this.driverElements = {};
        this.observer = null;
        this.pendingRequest = null;
        this.initialPopulation = true;

        this.init();
    }

    DependentField.prototype.init = function() {
        if (!this.dependsOn.length) {
            return;
        }

        this.bindDrivers();
        this.refresh();
    };

    DependentField.prototype.bindDrivers = function() {
        var self = this;

        this.detachDriverListeners();
        var resolution = this.resolveDrivers();
        this.driverElements = resolution.drivers;

        $.each(this.driverElements, function(name, $driver) {
            if (!$driver || !$driver.length) {
                return;
            }

            var namespace = '.dependentOptions.' + self.instanceId + '.' + name;

            $driver.off(namespace);
            $driver.on('change' + namespace + ' input' + namespace, function() {
                self.refresh();
            });
        });

        if (!resolution.allFound) {
            this.observeForDrivers();
        } else {
            this.disconnectObserver();
        }
    };

    DependentField.prototype.detachDriverListeners = function() {
        var self = this;

        $.each(this.driverElements, function(name, $driver) {
            if (!$driver || !$driver.length) {
                return;
            }

            var namespace = '.dependentOptions.' + self.instanceId + '.' + name;
            $driver.off(namespace);
        });
    };

    DependentField.prototype.observeForDrivers = function() {
        var self = this;

        if (this.observer) {
            return;
        }

        this.observer = new MutationObserver(function() {
            var resolution = self.resolveDrivers();

            if (!resolution.allFound) {
                return;
            }

            self.driverElements = resolution.drivers;
            self.bindDrivers();
            self.refresh();
            self.disconnectObserver();
        });

        $.each(this.scopeRoots, function(_, $scope) {
            if (!$scope || !$scope.length) {
                return;
            }

            self.observer.observe($scope.get(0), { childList: true, subtree: true });
        });
    };

    DependentField.prototype.disconnectObserver = function() {
        if (this.observer) {
            this.observer.disconnect();
            this.observer = null;
        }
    };

    DependentField.prototype.resolveDrivers = function() {
        var self = this;
        var drivers = {};
        var allFound = true;

        $.each(this.dependsOn, function(_, name) {
            var $driver = findDriverInScopes(self.scopeRoots, name, self.$field);

            if (!$driver || !$driver.length) {
                allFound = false;
            }

            drivers[name] = $driver;
        });

        return {
            drivers: drivers,
            allFound: allFound
        };
    };

    DependentField.prototype.hasAllDrivers = function() {
        var ready = true;

        $.each(this.dependsOn, function(_, name) {
            var $driver = this.driverElements[name];

            if (!$driver || !$driver.length) {
                ready = false;
                return false;
            }
        }.bind(this));

        return ready;
    };

    DependentField.prototype.getDriverValues = function() {
        var self = this;
        var values = {};

        $.each(this.dependsOn, function(_, name) {
            var $driver = self.driverElements[name];

            if (!$driver || !$driver.length) {
                values[name] = [];
                return;
            }

            values[name] = normalizeDriverValue($driver.val());
        });

        return values;
    };

    DependentField.prototype.findMapEntryForValue = function(value) {
        if (!this.map) {
            return null;
        }

        if (Object.prototype.hasOwnProperty.call(this.map, value)) {
            return this.map[value];
        }

        var found = null;

        $.each(this.map, function(key, entry) {
            if (key === '*' || found) {
                return;
            }

            if (key.charAt(0) === '[' && key.charAt(key.length - 1) === ']') {
                var keys = key.substring(1, key.length - 1).split(',').map(function(item) {
                    return $.trim(item);
                });

                if (keys.indexOf(value) !== -1) {
                    found = entry;
                }
            }
        });

        if (found) {
            return found;
        }

        if (Object.prototype.hasOwnProperty.call(this.map, '*')) {
            return this.map['*'];
        }

        return null;
    };

    DependentField.prototype.getOptionsFromMap = function(driverValues) {
        if (!this.map) {
            return [];
        }

        var self = this;
        var aggregated = [];
        var hasMatch = false;

        $.each(this.dependsOn, function(_, name) {
            var values = driverValues[name] || [];

            $.each(values, function(_, value) {
                var entry = self.findMapEntryForValue(value);

                if (entry !== null && typeof entry !== 'undefined') {
                    hasMatch = true;
                    aggregated = aggregated.concat(normalizeOptionsCollection(entry));
                }
            });
        });

        if (!hasMatch && Object.prototype.hasOwnProperty.call(this.map, '*')) {
            aggregated = normalizeOptionsCollection(this.map['*']);
        }

        return aggregated;
    };

    DependentField.prototype.buildRequestUrl = function(driverValues) {
        var template = this.urlTemplate;

        if (!template) {
            return null;
        }

        var replacements = {};

        if (this.dependsOn.length) {
            var primaryName = this.dependsOn[0];
            replacements.value = (driverValues[primaryName] || []).join(',');
        }

        $.each(this.dependsOn, function(_, name) {
            replacements[name] = (driverValues[name] || []).join(',');
        });

        return template.replace(/\{([^}]+)\}/g, function(_, key) {
            if (!Object.prototype.hasOwnProperty.call(replacements, key)) {
                return '';
            }

            return encodeURIComponent(replacements[key]);
        });
    };

    DependentField.prototype.fetchOptionsFromUrl = function(driverValues, callback) {
        var self = this;
        var url = this.buildRequestUrl(driverValues);

        if (!url) {
            callback([]);
            return;
        }

        if (this.pendingRequest && typeof this.pendingRequest.abort === 'function') {
            this.pendingRequest.abort();
        }

        this.pendingRequest = $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json'
        })
            .done(function(response) {
                callback(response);
            })
            .fail(function(jqXHR, textStatus) {
                if (textStatus === 'abort') {
                    return;
                }

                console.warn('Backpack dependent fields: unable to load options from URL', url);
                callback([]);
            })
            .always(function() {
                self.pendingRequest = null;
            });
    };

    DependentField.prototype.getSelectedValues = function() {
        return normalizeDriverValue(this.$field.val());
    };

    DependentField.prototype.applyOptions = function(options) {
        var $field = this.$field;
        var multiple = $field.prop('multiple');
        var selectedValues = this.getSelectedValues();
        var labels = collectExistingOptionLabels($field);
        var placeholder = getPlaceholderData($field);
        var optionMap = {};
        var rendered = [];

        if (placeholder) {
            optionMap[placeholder.value] = placeholder;
            rendered.push(placeholder);
        }

        $.each(options, function(_, option) {
            if (!option || typeof option.value === 'undefined') {
                return;
            }

            var value = option.value + '';

            if (optionMap.hasOwnProperty(value)) {
                return;
            }

            optionMap[value] = option;
            rendered.push(option);
        });

        $.each(selectedValues, function(_, value) {
            if (value === '') {
                return;
            }

            if (optionMap.hasOwnProperty(value)) {
                return;
            }

            var label = labels[value] || value;
            var option = {
                value: value,
                label: label
            };

            optionMap[value] = option;
            rendered.push(option);
        });

        var html = rendered.map(function(option) {
            var attributes = '';

            if (option.disabled) {
                attributes += ' disabled';
            }

            if (option.placeholder) {
                attributes += ' data-placeholder="true"';
            }

            return '<option value="' + escapeHtml(option.value) + '"' + attributes + '>' + escapeHtml(option.label) + '</option>';
        }).join('');

        $field.html(html);

        if (multiple) {
            $field.val(selectedValues);
        } else {
            var selected = selectedValues.length ? selectedValues[0] : '';

            if (!optionMap.hasOwnProperty(selected)) {
                selected = placeholder ? placeholder.value : '';
            }

            $field.val(selected);
        }

        if ($field.hasClass('select2-hidden-accessible')) {
            $field.trigger('change.select2');
        } else {
            $field.trigger('change');
        }
    };

    DependentField.prototype.refresh = function() {
        if (!this.dependsOn.length) {
            return;
        }

        if (!this.hasAllDrivers()) {
            return;
        }

        var self = this;
        var driverValues = this.getDriverValues();

        if (this.urlTemplate) {
            this.fetchOptionsFromUrl(driverValues, function(response) {
                var normalized = normalizeOptionsCollection(response);
                self.applyOptions(normalized);
                self.initialPopulation = false;
            });
            return;
        }

        var options = this.getOptionsFromMap(driverValues);
        this.applyOptions(options);
        this.initialPopulation = false;
    };

    window.bpFieldInitDependentOptions = function(element) {
        var $element = $(element);

        if (!$element.length) {
            return;
        }

        if ($element.closest('[data-template-item="true"]').length) {
            return;
        }

        if ($element.data('bp-dependent-initialized')) {
            return;
        }

        $element.data('bp-dependent-initialized', true);
        $element.data('bp-dependent-instance', new DependentField($element));
    };
})(jQuery);

