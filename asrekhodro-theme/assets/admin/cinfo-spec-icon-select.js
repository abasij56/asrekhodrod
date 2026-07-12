(function () {
  'use strict';

  var cfg = window.akCarSpecIcons || {};
  var icons = Array.isArray(cfg.icons) ? cfg.icons : [];
  var iconMap = {};

  icons.forEach(function (icon) {
    if (icon && icon.id) {
      iconMap[String(icon.id)] = icon;
    }
  });

  function normalizeText(value) {
    return String(value || '')
      .replace(/\u00a0/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .toLowerCase();
  }

  function getOptions(select) {
    return Array.prototype.map.call(select.options, function (option) {
      return {
        value: option.value,
        label: option.text.replace(/\s+/g, ' ').trim(),
      };
    });
  }

  function iconForValue(value) {
    if (!value) {
      return null;
    }
    return iconMap[String(value)] || null;
  }

  function optionMatchesQuery(option, query) {
    if (!query) {
      return true;
    }

    var icon = iconForValue(option.value);
    var haystack = [option.label, option.value];

    if (icon) {
      haystack.push(icon.title, icon.name, icon.set, icon.setLabel);
    }

    return haystack.some(function (part) {
      return normalizeText(part).indexOf(query) !== -1;
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
    var option = select.options[select.selectedIndex];
    var label = option ? option.text.replace(/\s+/g, ' ').trim() : '';
    var icon = iconForValue(select.value);

    if (!select.value) {
      valueEl.textContent = cfg.noneLabel || label || '—';
      renderThumb(thumbEl, null);
      return;
    }

    valueEl.textContent = icon && icon.title ? icon.title + ' — ' + icon.name : label || select.value;
    renderThumb(thumbEl, icon);
  }

  function closePicker(picker) {
    picker.classList.remove('is-open');
    picker.querySelector('.ak-cinfo-icon-picker__dropdown').hidden = true;
  }

  function openPicker(picker) {
    picker.classList.add('is-open');
    var dropdown = picker.querySelector('.ak-cinfo-icon-picker__dropdown');
    dropdown.hidden = false;
    var searchInput = picker.querySelector('.ak-cinfo-icon-picker__search');
    searchInput.value = '';
    renderList(picker, '');
    window.setTimeout(function () {
      searchInput.focus();
    }, 0);
  }

  function renderList(picker, query) {
    var select = picker.querySelector('.ak-cinfo-icon-picker__native');
    var list = picker.querySelector('.ak-cinfo-icon-picker__list');
    var normalizedQuery = normalizeText(query);
    var options = getOptions(select);
    var currentValue = select.value;
    var matches = options.filter(function (option) {
      if (optionMatchesQuery(option, normalizedQuery)) {
        return true;
      }
      return normalizedQuery && String(option.value) === String(currentValue);
    });

    list.innerHTML = '';

    if (!matches.length) {
      var empty = document.createElement('li');
      empty.className = 'ak-cinfo-icon-picker__empty';
      empty.textContent = cfg.noResults || 'نتیجه‌ای یافت نشد';
      list.appendChild(empty);
      return;
    }

    matches.forEach(function (option) {
      var item = document.createElement('li');
      item.className = 'ak-cinfo-icon-picker__option';
      item.dataset.value = option.value;

      if (String(option.value) === String(currentValue)) {
        item.classList.add('is-selected');
        item.setAttribute('aria-selected', 'true');
      }

      var thumb = document.createElement('span');
      thumb.className = 'ak-cinfo-icon-picker__option-thumb';
      renderThumb(thumb, iconForValue(option.value));
      item.appendChild(thumb);

      var meta = document.createElement('span');
      meta.className = 'ak-cinfo-icon-picker__option-meta';

      var icon = iconForValue(option.value);
      var title = document.createElement('span');
      title.className = 'ak-cinfo-icon-picker__option-title';
      title.textContent = icon && icon.title ? icon.title : option.label || option.value || cfg.noneLabel || '—';

      var name = document.createElement('span');
      name.className = 'ak-cinfo-icon-picker__option-name';
      name.textContent = icon ? icon.name + ' (' + (icon.setLabel || icon.set) + ')' : option.value;

      meta.appendChild(title);
      meta.appendChild(name);
      item.appendChild(meta);

      item.addEventListener('mousedown', function (event) {
        event.preventDefault();
      });

      item.addEventListener('click', function () {
        select.value = option.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        updateControl(picker, select);
        closePicker(picker);
      });

      list.appendChild(item);
    });
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

  function enhanceSelect(select) {
    if (!(select instanceof HTMLSelectElement) || select.dataset.akCinfoIconEnhanced === '1') {
      return;
    }

    select.dataset.akCinfoIconEnhanced = '1';

    var picker = document.createElement('div');
    picker.className = 'ak-cinfo-icon-picker';

    var control = document.createElement('button');
    control.type = 'button';
    control.className = 'ak-cinfo-icon-picker__control';
    control.innerHTML =
      '<span class="ak-cinfo-icon-picker__thumb is-empty" aria-hidden="true"></span>' +
      '<span class="ak-cinfo-icon-picker__value"></span>' +
      '<span class="ak-cinfo-icon-picker__arrow" aria-hidden="true"></span>';

    var dropdown = document.createElement('div');
    dropdown.className = 'ak-cinfo-icon-picker__dropdown';
    dropdown.hidden = true;

    var searchInput = document.createElement('input');
    searchInput.type = 'search';
    searchInput.className = 'ak-cinfo-icon-picker__search';
    searchInput.placeholder = cfg.placeholder || 'جستجو یا انتخاب آیکون…';
    searchInput.autocomplete = 'off';

    var list = document.createElement('ul');
    list.className = 'ak-cinfo-icon-picker__list';
    list.setAttribute('role', 'listbox');

    dropdown.appendChild(searchInput);
    dropdown.appendChild(list);

    select.classList.add('ak-cinfo-icon-picker__native');
    select.parentNode.insertBefore(picker, select);
    picker.appendChild(control);
    picker.appendChild(dropdown);
    picker.appendChild(select);

    updateControl(picker, select);

    control.addEventListener('click', function () {
      if (picker.classList.contains('is-open')) {
        closePicker(picker);
        return;
      }
      openPicker(picker);
    });

    searchInput.addEventListener('input', function () {
      renderList(picker, searchInput.value);
    });

    searchInput.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closePicker(picker);
        control.focus();
      }
    });

    document.addEventListener('click', function (event) {
      if (!picker.contains(event.target)) {
        closePicker(picker);
      }
    });

    select.addEventListener('change', function () {
      updateControl(picker, select);
    });
  }

  function boot() {
    injectSprite();
    document
      .querySelectorAll('.acf-field[data-key="field_cinfo_facts_item_icon"] select')
      .forEach(enhanceSelect);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.addEventListener('load', boot);

  if (window.acf && typeof window.acf.add_action === 'function') {
    window.acf.add_action('append', boot);
    window.acf.add_action('ready', boot);
  }
})();
