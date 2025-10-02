(function () {
  function getJQ(el){ return (window.jQuery && !el.jquery) ? jQuery(el) : el; }

  function hasSelect2Instance(el){
    return !!(window.jQuery && jQuery(el).data && jQuery(el).data('select2'));
  }

  function isSelect2(el){
    if (!el) return false;
    if (hasSelect2Instance(el)) return true;
    if (el.classList && (el.classList.contains('select2-hidden-accessible') || Array.prototype.some.call(el.classList, function(cls){ return cls.indexOf('select2') !== -1; }))) return true;
    var initFn = el.getAttribute && el.getAttribute('data-init-function');
    if (initFn && /^bpFieldInitSelect2/i.test(initFn)) return true;
    return false;
  }

  function valueHasContent(val){
    if (Array.isArray(val)) return val.length > 0;
    return val !== null && val !== undefined && String(val) !== '';
  }

  function isFormControl(el){
    if (!el || !el.matches) return false;
    return el.matches('select,input,textarea');
  }

  function getValue(el){
    if(!el) return null;
    if(el.type === 'checkbox') return el.checked ? (el.value || '1') : '';
    if(el.type === 'radio'){
      var form = el.form || document;
      var group = form.querySelectorAll('input[type=radio][name="'+el.name+'"]');
      for(var i=0;i<group.length;i++){ if(group[i].checked) return group[i].value; }
      return '';
    }
    if(el.tagName === 'SELECT' && el.multiple){
      return Array.prototype.slice.call(el.options).filter(o=>o.selected).map(o=>o.value);
    }
    return el.value;
  }

  function normalizeOptions(raw, cfg){
    cfg = cfg || {};
    var labelProp = cfg.labelProp || null;
    // Поддерживаем разные форматы:
    // 1) объект: { "v":"Label", ... }
    // 2) массив пар: [["v","Label"], ...]
    // 3) массив объектов: [{id:"v", text:"Label"}, ...] или с кастомным label
    // 4) пагинация Laravel с ключом data
    if(!raw) return {};
    if (typeof raw === 'object' && !Array.isArray(raw) && Array.isArray(raw.data)){
      raw = raw.data;
    }
    if (Array.isArray(raw)){
      var asPairs = {};
      raw.forEach(function(it){
        if(Array.isArray(it) && it.length >= 2){
          asPairs[String(it[0])] = String(it[1]);
          return;
        }
        if (it && typeof it === 'object') {
          var key = it.hasOwnProperty('id') ? it.id : (it.hasOwnProperty('value') ? it.value : null);
          var label = null;
          if (labelProp && it[labelProp] != null) label = it[labelProp];
          else if (it.text != null) label = it.text;
          else if (it.label != null) label = it.label;
          else if (it.name != null) label = it.name;
          else if (it.value != null) label = it.value;
          else if (it.title != null) label = it.title;
          if (key != null && label != null){
            asPairs[String(key)] = String(label);
          }
        }
      });
      return asPairs;
    }
    if (typeof raw === 'object'){
      var objPairs = {};
      Object.keys(raw).forEach(function(k){ objPairs[String(k)] = String(raw[k]); });
      return objPairs;
    }
    return {};
  }

  function clearOptions(selectEl){
    while(selectEl.options.length){ selectEl.remove(0); }
  }

  function setOptions(selectEl, optionsObj, cfg){
    cfg = cfg || {};
    var allowNull = cfg.allowNull || false;
    var placeholder = cfg.placeholder || '';
    var autoselect = cfg.autoselect || false;

    var current = getValue(selectEl);
    var wasArray = Array.isArray(current);
    var currentArr = wasArray ? current.slice() : [current];

    var $sel = window.jQuery ? jQuery(selectEl) : null;
    var hadSelect2 = hasSelect2Instance(selectEl);
    var wantsSelect2 = isSelect2(selectEl);
    if (hadSelect2 && $sel && $sel.select2){
      try { $sel.select2('destroy'); } catch(_e) { /* ignore */ }
    }

    clearOptions(selectEl);

    var frag = document.createDocumentFragment();

    if (allowNull){
      var optEmpty = document.createElement('option');
      optEmpty.value = '';
      optEmpty.textContent = placeholder || '—';
      frag.appendChild(optEmpty);
    }

    Object.keys(optionsObj || {}).forEach(function(val){
      var opt = document.createElement('option');
      opt.value = val;
      opt.textContent = optionsObj[val];
      frag.appendChild(opt);
    });

    selectEl.appendChild(frag);

    var choose = null;
    if (wasArray){
      currentArr.forEach(function(v){
        var targetOpt = Array.prototype.find.call(selectEl.options, function(x){ return x.value === String(v); });
        if (targetOpt) targetOpt.selected = true;
      });
    } else {
      var existing = Array.prototype.find.call(selectEl.options, function(x){ return x.value === String(current); });
      if (existing) choose = current;
    }

    if (!choose && autoselect){
      var available = allowNull ? Array.prototype.slice.call(selectEl.options, 1) : Array.prototype.slice.call(selectEl.options);
      if (available.length === 1){ choose = available[0].value; }
    }

    if (choose !== null && choose !== undefined){
      selectEl.value = choose;
    } else if(!allowNull && !wasArray){
      selectEl.selectedIndex = -1;
    }

    if (wantsSelect2 && $sel){
      var initFn = selectEl.getAttribute('data-init-function');
      if (initFn && typeof window[initFn] === 'function'){
        window[initFn]($sel);
      } else if ($sel.select2){
        $sel.select2({ theme: 'bootstrap' });
      }
      $sel.trigger('change');
    } else {
      var evt = document.createEvent('HTMLEvents');
      evt.initEvent('change', true, false);
      selectEl.dispatchEvent(evt);
    }
  }

  function resolveDriver(targetEl, dependsOn, scope){
    scope = scope || 'auto';

    var selectorParts = [];
    var depNames = [dependsOn];
    if (dependsOn && dependsOn.slice(-2) === '[]'){
      depNames.push(dependsOn.slice(0, -2));
    }

    depNames.forEach(function(dep){
      if (!dep) return;
      selectorParts.push('[name="'+dep+'"]');
      selectorParts.push('[name="'+dep+'[]"]');
      selectorParts.push('[data-repeatable-input-name="'+dep+'"]');
      selectorParts.push('[data-repeatable-input-name="'+dep+'[]"]');
      selectorParts.push('[data-dep-source="'+dep+'"]');
      selectorParts.push('[data-field-name="'+dep+'"]');
    });

    var selector = selectorParts.join(',');

    function locate(root){
      if (!root || !root.querySelector) return null;
      var found = root.querySelector(selector);
      if (!found) return null;
      if (isFormControl(found)) return found;
      if (found.querySelector){
        var inner = found.querySelector('select,input,textarea');
        if (inner) return inner;
      }
      return found;
    }

    var customScope = null;
    if (scope && scope !== 'auto' && scope !== 'row' && scope !== 'global'){
      try {
        customScope = targetEl.closest(scope);
      } catch(_err){
        customScope = null;
      }
      if (customScope){
        var inCustom = locate(customScope);
        if (inCustom) return inCustom;
      }
    }

    if (scope !== 'global'){
      var row = targetEl.closest('.repeatable-element');
      if (row){
        var inRow = locate(row);
        if (inRow) return inRow;
      }
      if (scope === 'row') return null;
    }

    var form = targetEl.closest('form');
    var roots = [];
    if (form) roots.push(form);
    roots.push(document);

    for (var i = 0; i < roots.length; i++){
      var within = locate(roots[i]);
      if (within) return within;
    }

    return null;
  }

  function fetchOptionsByUrl(url, value, opts, done){
    opts = opts || {};
    // value может быть массивом
    var v = Array.isArray(value) ? value.join(',') : (value == null ? '' : String(value));
    var endpoint = url.replace('{value}', encodeURIComponent(v));
    if (window.jQuery){
      jQuery.ajax({ url: endpoint, method: 'GET', dataType: 'json' })
        .done(function(resp){ done(null, normalizeOptions(resp, opts)); })
        .fail(function(xhr){ done(xhr || true, {}); });
    } else {
      fetch(endpoint, { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(resp){ done(null, normalizeOptions(resp, opts)); })
        .catch(function(e){ done(e || true, {}); });
    }
  }

  function makeUpdater(targetEl){
    var mapJson = targetEl.getAttribute('data-dep-map');
    var url = targetEl.getAttribute('data-dep-url');
    var allowNull = targetEl.getAttribute('data-allow-null') === '1' || targetEl.getAttribute('data-allow-null') === 'true';
    var placeholder = targetEl.getAttribute('data-placeholder') || '';
    var autoselect = targetEl.getAttribute('data-dep-autoselect') === '1' || targetEl.getAttribute('data-dep-autoselect') === 'true';

    var labelProp = targetEl.getAttribute('data-label-prop') || null;

    var mapObj = null;
    if (mapJson){
      try { mapObj = JSON.parse(mapJson); } catch(e){ mapObj = null; }
    }

    var requestToken = 0;

    return function updateFrom(driverEl){
      var val = getValue(driverEl);
      // 1) статическая карта
      if (mapObj){
        var options = mapObj && ( (Array.isArray(val) ? null : (mapObj[val])) || mapObj['*'] ) || {};
        options = normalizeOptions(options, { labelProp: labelProp });
        setOptions(targetEl, options, { allowNull: allowNull, placeholder: placeholder, autoselect: autoselect });
        return;
      }
      // 2) по URL
      if (url){
        if (!valueHasContent(val)){
          setOptions(targetEl, {}, { allowNull: allowNull, placeholder: placeholder, autoselect: autoselect });
          return;
        }
        var token = ++requestToken;
        fetchOptionsByUrl(url, val, { labelProp: labelProp }, function(_err, options){
          if (token !== requestToken) return;
          setOptions(targetEl, options, { allowNull: allowNull, placeholder: placeholder, autoselect: autoselect });
        });
        return;
      }
      // 3) нет источника — просто очистим (но оставим placeholder при allowNull)
      setOptions(targetEl, {}, { allowNull: allowNull, placeholder: placeholder });
    };
  }

  function bindOne(targetEl){
    if (targetEl.__bp_dep_init) return;
    var dependsOn = targetEl.getAttribute('data-depends-on');
    if (!dependsOn) return;

    var scope = targetEl.getAttribute('data-dep-scope') || 'auto';
    var driver = resolveDriver(targetEl, dependsOn, scope);
    if (!driver) return; // драйвер ещё не доступен — можно попробовать позже (conditional может скрывать)

    var updater = makeUpdater(targetEl);

    // первичная установка
    updater(driver);

    // подписки
    if (window.jQuery && isSelect2(driver)){
      var $driver = jQuery(driver);
      $driver.on('change.bpDependentOptions', function(){ updater(driver); });
      $driver.on('select2:select.bpDependentOptions select2:unselect.bpDependentOptions select2:clear.bpDependentOptions', function(){ updater(driver); });
    } else {
      driver.addEventListener('change', function(){ updater(driver); });
      driver.addEventListener('input', function(){ updater(driver); });
    }

    targetEl.__bp_dep_init = true;
  }

  function bindAll(ctx){
    var root = ctx || document;
    var nodes = root.querySelectorAll('[data-init-function="bpFieldInitDependentOptions"],[data-bp-dependent-options="1"]');
    Array.prototype.forEach.call(nodes, function(node){
      // если data-init-function стоит на wrapper, попробуем найти input/select
      if (node.matches('select,input,textarea')) bindOne(node);
      else {
        var inner = node.querySelector('select,input,textarea');
        if (inner) bindOne(inner);
      }
    });
  }

  // DOM ready
  document.addEventListener('DOMContentLoaded', function(){ bindAll(); });

  // MutationObserver — на случай динамических вставок вне initializeFieldsWithJavascript
  var mo = new MutationObserver(function(muts){
    muts.forEach(function(m){
      Array.prototype.forEach.call(m.addedNodes || [], function(n){
        if(!n || n.nodeType !== 1) return;
        if (n.querySelector && (n.hasAttribute('data-init-function') || n.querySelector('[data-init-function="bpFieldInitDependentOptions"]'))){
          bindAll(n);
        }
      });
    });
  });
  mo.observe(document.documentElement || document.body, { childList:true, subtree:true });

  // Хук под Backpack: вызывать из initializeFieldsWithJavascript
  window.bpFieldInitDependentOptions = function(element){
    var el = element && element[0] ? element[0] : element;
    bindAll(el || document);
  };

})();
