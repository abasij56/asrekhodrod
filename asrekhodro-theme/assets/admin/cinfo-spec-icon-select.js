(function () {
  'use strict';

  var cfg = window.akCarSpecIcons || {};
  var iconMap = {};
  var searchTimer = null;
  var delegatedBound = false;
  var acfHooksBound = false;
  var observeBound = false;
  var enhanceTimer = null;
  var FIELD_KEY = 'field_cinfo_facts_item_icon';
  var FIELD_SELECTOR = '.acf-field[data-key="' + FIELD_KEY + '"] select';

  function rememberIcons(items) {
    if (!Array.isArray(items)) {
      return;
    }

    items.forEach(function (icon) {
      if ( icon && icon.id ) {
        iconMap[String(icon.id)] = icon;
      }
    });
  }

  rememberIcons(cfg.initialIcons);

  function normalizeText(value) {
    return String(value || '')
      .replace(/\u00a0/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .toLowerCase();
  }

  function iconForValue(value) {
    if (!value) {
      return null;
    }
    return iconMap[String(value)] || null;
  }

  function getPickerState(picker) {
    if (!picker._akIconState) {
      picker._akIconState = {
        items: Array.isArray(cfg.initialIcons) ? cfg.initialIcons.slice() : [],
        query: '',
        offset: Number(cfg.initialLimit || 10),
        hasMore: Boolean(cfg.initialHasMore),
        loading: false,
        total: Number(cfg.initialTotal || 0),
        loadedOnce: false,
      };
    }
    return picker._akIconState;
  }

  function resetPickerState(picker) {
    picker._akIconState = {
      items: Array.isArray(cfg.initialIcons) ? cfg.initialIcons.slice() : [],
      query: '',
      offset: Number(cfg.initialLimit || 10),
      hasMore: Boolean(cfg.initialHasMore),
      loading: false,
      total: Number(cfg.initialTotal || 0),
      loadedOnce: true,
    };
  }

  function ensureSelectOption(select, value, icon) {
    if (!value) {
      return;
    }

    var existing = Array.prototype.find.call(select.options, function (option) {
      return String(option.value) === String(value);
    });

    if (existing) {
      return;
    }

    var option = document.createElement('option');
    option.value = value;
    option.text = icon && icon.title ? icon.title + ' — ' + icon.name : value;
    select.appendChild(option);
  }

  function buildQueryUrl(params) {
    var url = new URL(cfg.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php', window.location.origin);
    url.searchParams.set('action', cfg.action || 'ak_car_spec_icons_query');
    url.searchParams.set('nonce', cfg.nonce || '');

    Object.keys(params).forEach(function (key) {
      if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
        url.searchParams.set(key, String(params[key]));
      }
    });

    return url.toString();
  }

  function fetchIcons(picker, options) {
    var state = getPickerState(picker);
    var select = picker.querySelector('.ak-cinfo-icon-picker__native');

    if (state.loading) {
      return Promise.resolve();
    }

    var reset = Boolean(options && options.reset);
    var query = options && typeof options.query === 'string' ? options.query : state.query;
    var offset = reset ? 0 : state.offset;
    var limit = Number(cfg.pageSize || 20);
    var include = select && select.value ? select.value : '';

    state.loading = true;
    renderListStatus(picker);

    return fetch(
      buildQueryUrl({
        q: query,
        offset: offset,
        limit: limit,
        include: reset && include ? include : '',
      })
    )
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data) {
          throw new Error('invalid response');
        }

        var data = payload.data;
        var items = Array.isArray(data.items) ? data.items : [];

        rememberIcons(items);

        if (reset) {
          state.items = items.slice();
        } else {
          items.forEach(function (item) {
            if (!state.items.some(function (existing) {
              return String(existing.id) === String(item.id);
            })) {
              state.items.push(item);
            }
          });
        }

        state.query = query;
        state.hasMore = Boolean(data.has_more);
        state.total = Number(data.total || state.total || 0);
        state.offset = Number(data.next_offset !== undefined ? data.next_offset : offset + items.length);
        state.loadedOnce = true;
      })
      .catch(function () {
        if (!state.loadedOnce) {
          state.items = Array.isArray(cfg.initialIcons) ? cfg.initialIcons.slice() : [];
        }
      })
      .finally(function () {
        state.loading = false;
        renderList(picker, { append: false });
      });
  }

  function renderThumb(container, icon) {
    container.innerHTML = '';

    if (!icon) {
      container.classList.add('is-empty');
      return;
    }

    container.classList.remove('is-empty');

    if (icon.sprite && icon.symbol) {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('aria-hidden', 'true');
      var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
      use.setAttribute('href', '#' + icon.symbol);
      svg.appendChild(use);
      container.appendChild(svg);
      return;
    }

    if (icon.url) {
      var img = document.createElement('img');
      img.src = icon.url;
      img.alt = icon.name || icon.id;
      img.loading = 'lazy';
      container.appendChild(img);
    }
  }

  function updateControl(picker, select) {
    var valueEl = picker.querySelector('.ak-cinfo-icon-picker__value');
    var thumbEl = picker.querySelector('.ak-cinfo-icon-picker__thumb');
    if (!valueEl || !thumbEl) {
      return;
    }

    var icon = iconForValue(select.value);

    if (!select.value) {
      valueEl.textContent = cfg.noneLabel || '—';
      renderThumb(thumbEl, null);
      return;
    }

    if (!icon) {
      valueEl.textContent = select.value;
      renderThumb(thumbEl, null);
      return;
    }

    ensureSelectOption(select, select.value, icon);
    valueEl.textContent = icon.title ? icon.title + ' — ' + icon.name : select.value;
    renderThumb(thumbEl, icon);
  }

  function closePicker(picker) {
    picker.classList.remove('is-open');
    var dropdown = picker.querySelector('.ak-cinfo-icon-picker__dropdown');
    if (dropdown) {
      dropdown.hidden = true;
    }
  }

  function closeAllPickers(except) {
    document.querySelectorAll('.ak-cinfo-icon-picker.is-open').forEach(function (picker) {
      if (picker !== except) {
        closePicker(picker);
      }
    });
  }

  function openPicker(picker) {
    closeAllPickers(picker);
    picker.classList.add('is-open');
    var dropdown = picker.querySelector('.ak-cinfo-icon-picker__dropdown');
    dropdown.hidden = false;
    var searchInput = picker.querySelector('.ak-cinfo-icon-picker__search');
    searchInput.value = '';
    resetPickerState(picker);
    renderList(picker, { append: false });
    window.setTimeout(function () {
      searchInput.focus();
    }, 0);
  }

  function togglePicker(picker) {
    if (picker.classList.contains('is-open')) {
      closePicker(picker);
      return;
    }
    openPicker(picker);
  }

  function renderListStatus(picker) {
    var list = picker.querySelector('.ak-cinfo-icon-picker__list');
    var state = getPickerState(picker);
    var status = list.querySelector('.ak-cinfo-icon-picker__status');

    if (!state.loading) {
      if (status) {
        status.remove();
      }
      return;
    }

    if (!status) {
      status = document.createElement('li');
      status.className = 'ak-cinfo-icon-picker__status';
      list.appendChild(status);
    }

    status.textContent = cfg.loadingLabel || 'در حال بارگذاری…';
  }

  function selectIcon(picker, select, value, icon) {
    if (value) {
      ensureSelectOption(select, value, icon);
      select.value = value;
    } else {
      select.value = '';
    }

    select.dispatchEvent(new Event('change', { bubbles: true }));
    updateControl(picker, select);
    closePicker(picker);
  }

  function createOptionItem(picker, select, icon, currentValue) {
    var item = document.createElement('li');
    item.className = 'ak-cinfo-icon-picker__option';
    item.dataset.value = icon.id;

    if (String(icon.id) === String(currentValue)) {
      item.classList.add('is-selected');
      item.setAttribute('aria-selected', 'true');
    }

    var thumb = document.createElement('span');
    thumb.className = 'ak-cinfo-icon-picker__option-thumb';
    renderThumb(thumb, icon);
    item.appendChild(thumb);

    var meta = document.createElement('span');
    meta.className = 'ak-cinfo-icon-picker__option-meta';

    var title = document.createElement('span');
    title.className = 'ak-cinfo-icon-picker__option-title';
    title.textContent = icon.title || icon.name || icon.id;

    var name = document.createElement('span');
    name.className = 'ak-cinfo-icon-picker__option-name';
    name.textContent = icon.name + ' (' + (icon.setLabel || icon.set) + ')';

    meta.appendChild(title);
    meta.appendChild(name);
    item.appendChild(meta);

    return item;
  }

  function renderList(picker, options) {
    var select = picker.querySelector('.ak-cinfo-icon-picker__native');
    var list = picker.querySelector('.ak-cinfo-icon-picker__list');
    var state = getPickerState(picker);
    var currentValue = select.value;
    var append = Boolean(options && options.append);
    var items = state.items.slice();

    if (!append) {
      list.innerHTML = '';
    } else {
      list.querySelectorAll('.ak-cinfo-icon-picker__option, .ak-cinfo-icon-picker__empty').forEach(function (node) {
        if (!node.classList.contains('ak-cinfo-icon-picker__status')) {
          node.remove();
        }
      });
    }

    if (state.loading && !items.length) {
      renderListStatus(picker);
      return;
    }

    if (!items.length) {
      var empty = document.createElement('li');
      empty.className = 'ak-cinfo-icon-picker__empty';
      empty.textContent = cfg.noResults || 'نتیجه‌ای یافت نشد';
      list.appendChild(empty);
      return;
    }

    var noneItem = document.createElement('li');
    noneItem.className = 'ak-cinfo-icon-picker__option';
    noneItem.dataset.value = '';
    if (!currentValue) {
      noneItem.classList.add('is-selected');
    }
    noneItem.innerHTML =
      '<span class="ak-cinfo-icon-picker__option-thumb is-empty" aria-hidden="true"></span>' +
      '<span class="ak-cinfo-icon-picker__option-meta">' +
      '<span class="ak-cinfo-icon-picker__option-title">' + (cfg.noneLabel || '— بدون آیکون —') + '</span>' +
      '</span>';
    list.appendChild(noneItem);

    items.forEach(function (icon) {
      list.appendChild(createOptionItem(picker, select, icon, currentValue));
    });

    if (state.hasMore) {
      var more = document.createElement('li');
      more.className = 'ak-cinfo-icon-picker__more';
      more.textContent = state.loading
        ? (cfg.loadingLabel || 'در حال بارگذاری…')
        : (cfg.scrollHint || 'برای مشاهده بیشتر اسکرول کنید…');
      list.appendChild(more);
    }

    renderListStatus(picker);
  }

  function loadMore(picker) {
    var state = getPickerState(picker);
    if (state.loading || !state.hasMore) {
      return;
    }

    fetchIcons(picker, { reset: false, query: state.query });
  }

  function injectSprite() {
    if (document.getElementById('ak-cinfo-icon-sprite')) {
      return;
    }

    if (!cfg.spriteUrl) {
      return;
    }

    fetch(cfg.spriteUrl)
      .then(function (response) {
        return response.text();
      })
      .then(function (markup) {
        var tpl = document.createElement('template');
        tpl.innerHTML = markup.trim();
        var svg = tpl.content.firstElementChild;
        if (!svg || svg.tagName.toLowerCase() !== 'svg') {
          return;
        }
        svg.id = 'ak-cinfo-icon-sprite';
        svg.setAttribute('aria-hidden', 'true');
        svg.style.display = 'none';
        document.body.appendChild(svg);
      })
      .catch(function () {
        /* sprite optional */
      });
  }

  function primeSelectedIcon(picker, select) {
    if (!select.value || iconForValue(select.value)) {
      updateControl(picker, select);
      return;
    }

    fetch(
      buildQueryUrl({
        q: '',
        offset: 0,
        limit: 1,
        include: select.value,
      })
    )
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (payload && payload.success && payload.data && Array.isArray(payload.data.items)) {
          rememberIcons(payload.data.items);
        }
      })
      .finally(function () {
        updateControl(picker, select);
      });
  }

  function buildPickerMarkup() {
    return (
      '<button type="button" class="ak-cinfo-icon-picker__control">' +
      '<span class="ak-cinfo-icon-picker__thumb is-empty" aria-hidden="true"></span>' +
      '<span class="ak-cinfo-icon-picker__value"></span>' +
      '<span class="ak-cinfo-icon-picker__arrow" aria-hidden="true"></span>' +
      '</button>' +
      '<div class="ak-cinfo-icon-picker__dropdown" hidden>' +
      '<input type="search" class="ak-cinfo-icon-picker__search" autocomplete="off" placeholder="' +
      (cfg.placeholder || 'جستجو یا انتخاب آیکون…') +
      '" />' +
      '<ul class="ak-cinfo-icon-picker__list" role="listbox"></ul>' +
      '</div>'
    );
  }

  function enhanceSelect(select) {
    if (!(select instanceof HTMLSelectElement)) {
      return;
    }

    var field = select.closest('.acf-field[data-key="' + FIELD_KEY + '"]');
    if (!field) {
      return;
    }

    var picker = select.closest('.ak-cinfo-icon-picker');

    if (!picker) {
      var orphan = field.querySelector('.ak-cinfo-icon-picker');
      if (orphan) {
        orphan.remove();
      }

      picker = document.createElement('div');
      picker.className = 'ak-cinfo-icon-picker';
      picker.innerHTML = buildPickerMarkup();

      select.classList.add('ak-cinfo-icon-picker__native');
      var input = field.querySelector('.acf-input');
      if (input) {
        input.appendChild(picker);
      } else {
        select.parentNode.insertBefore(picker, select);
      }
      picker.appendChild(select);
    }

    select.classList.add('ak-cinfo-icon-picker__native');
    primeSelectedIcon(picker, select);
  }

  function enhanceWithin(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll(FIELD_SELECTOR).forEach(enhanceSelect);
  }

  function scheduleEnhance(root) {
    window.clearTimeout(enhanceTimer);
    enhanceTimer = window.setTimeout(function () {
      enhanceWithin(root || document);
    }, 30);
  }

  function bindDelegatedEvents() {
    if (delegatedBound) {
      return;
    }

    delegatedBound = true;

    document.addEventListener(
      'click',
      function (event) {
        var control = event.target.closest('.ak-cinfo-icon-picker__control');
        if (control) {
          event.preventDefault();
          event.stopPropagation();
          var picker = control.closest('.ak-cinfo-icon-picker');
          if (picker) {
            togglePicker(picker);
          }
          return;
        }

        var option = event.target.closest('.ak-cinfo-icon-picker__option');
        if (option) {
          event.preventDefault();
          event.stopPropagation();
          var pickerOption = option.closest('.ak-cinfo-icon-picker');
          var selectOption = pickerOption && pickerOption.querySelector('.ak-cinfo-icon-picker__native');
          if (!pickerOption || !selectOption) {
            return;
          }

          var value = option.dataset.value || '';
          var icon = value ? iconForValue(value) : null;
          selectIcon(pickerOption, selectOption, value, icon);
          return;
        }

        document.querySelectorAll('.ak-cinfo-icon-picker.is-open').forEach(function (picker) {
          if (!picker.contains(event.target)) {
            closePicker(picker);
          }
        });
      },
      true
    );

    document.addEventListener(
      'input',
      function (event) {
        if (!event.target.matches('.ak-cinfo-icon-picker__search')) {
          return;
        }

        var picker = event.target.closest('.ak-cinfo-icon-picker');
        if (!picker) {
          return;
        }

        window.clearTimeout(searchTimer);
        var query = normalizeText(event.target.value);
        searchTimer = window.setTimeout(function () {
          fetchIcons(picker, { reset: true, query: query });
        }, 280);
      },
      true
    );

    document.addEventListener(
      'keydown',
      function (event) {
        if (event.key !== 'Escape') {
          return;
        }

        var picker = event.target.closest('.ak-cinfo-icon-picker.is-open');
        if (!picker) {
          return;
        }

        closePicker(picker);
        var control = picker.querySelector('.ak-cinfo-icon-picker__control');
        if (control) {
          control.focus();
        }
      },
      true
    );

    document.addEventListener(
      'scroll',
      function (event) {
        var list = event.target.closest && event.target.closest('.ak-cinfo-icon-picker__list');
        if (!list || event.target !== list) {
          return;
        }

        var picker = list.closest('.ak-cinfo-icon-picker');
        if (!picker) {
          return;
        }

        if (list.scrollTop + list.clientHeight >= list.scrollHeight - 48) {
          loadMore(picker);
        }
      },
      true
    );
  }

  function bindDomObserver() {
    if (observeBound || !window.MutationObserver) {
      return;
    }

    observeBound = true;

    var observer = new MutationObserver(function () {
      scheduleEnhance(document);
    });

    var start = function () {
      if (!document.body) {
        return;
      }
      observer.observe(document.body, { childList: true, subtree: true });
    };

    if (document.body) {
      start();
    } else {
      document.addEventListener('DOMContentLoaded', start);
    }
  }

  function fieldMatches(field) {
    if (!field || typeof field.get !== 'function') {
      return false;
    }

    return field.get('key') === FIELD_KEY || field.get('name') === 'item_icon';
  }

  function repeaterMatches(field) {
    if (!field || typeof field.get !== 'function') {
      return false;
    }

    return field.get('key') === 'field_cinfo_facts_items' || field.get('name') === 'fact_items';
  }

  function registerAcfHooks() {
    if (!window.acf || acfHooksBound) {
      return;
    }

    acfHooksBound = true;

    var onField = function (field) {
      if (!fieldMatches(field) && !repeaterMatches(field)) {
        return;
      }

      var el = field.$el && field.$el[0] ? field.$el[0] : null;
      scheduleEnhance(el || document);
    };

    var onAppend = function ($el) {
      var root = $el && $el[0] ? $el[0] : $el;
      scheduleEnhance(root || document);
    };

    var add = function (name, callback) {
      if (typeof acf.addAction === 'function') {
        acf.addAction(name, callback);
      }
      if (typeof acf.add_action === 'function') {
        acf.add_action(name, callback);
      }
    };

    add('ready', function () {
      scheduleEnhance(document);
    });
    add('load', function () {
      scheduleEnhance(document);
    });
    add('append', onAppend);
    add('ready_field', onField);
    add('append_field', onField);
    add('show_field', onField);
  }

  function boot() {
    injectSprite();
    bindDelegatedEvents();
    bindDomObserver();
    registerAcfHooks();
    scheduleEnhance(document);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.addEventListener('load', function () {
    scheduleEnhance(document);
  });
})();
