(function () {
  'use strict';

  var cfg = window.akCategoryBrandLogo || {};
  var logos = Array.isArray(cfg.logos) ? cfg.logos : [];
  var logoMap = {};

  logos.forEach(function (logo) {
    if (logo && logo.slug) {
      logoMap[String(logo.slug)] = logo;
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

  function logoForValue(value) {
    if (!value) {
      return null;
    }
    return logoMap[String(value)] || null;
  }

  function optionMatchesQuery(option, query) {
    if (!query) {
      return true;
    }

    if (normalizeText(option.label).indexOf(query) !== -1) {
      return true;
    }

    return normalizeText(option.value).indexOf(query) !== -1;
  }

  function updateControl(picker, select) {
    var valueEl = picker.querySelector('.ak-brand-logo-picker__value');
    var thumbEl = picker.querySelector('.ak-brand-logo-picker__thumb');
    var option = select.options[select.selectedIndex];
    var label = option ? option.text.replace(/\s+/g, ' ').trim() : '';
    var logo = logoForValue(select.value);

    if (!select.value) {
      valueEl.textContent = cfg.noneLabel || label || '—';
      thumbEl.removeAttribute('src');
      thumbEl.classList.add('is-empty');
      thumbEl.setAttribute('alt', '');
      return;
    }

    valueEl.textContent = label || select.value;
    thumbEl.classList.remove('is-empty');

    if (logo && logo.url) {
      thumbEl.src = logo.url;
      thumbEl.alt = logo.filename || logo.slug;
    } else {
      thumbEl.removeAttribute('src');
    }
  }

  function closePicker(picker) {
    picker.classList.remove('is-open');
    picker.querySelector('.ak-brand-logo-picker__dropdown').hidden = true;
  }

  function openPicker(picker) {
    picker.classList.add('is-open');
    var dropdown = picker.querySelector('.ak-brand-logo-picker__dropdown');
    dropdown.hidden = false;
    var searchInput = picker.querySelector('.ak-brand-logo-picker__search');
    searchInput.value = '';
    renderList(picker, '');
    window.setTimeout(function () {
      searchInput.focus();
    }, 0);
  }

  function renderList(picker, query) {
    var select = picker.querySelector('.ak-brand-logo-picker__native');
    var list = picker.querySelector('.ak-brand-logo-picker__list');
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
      empty.className = 'ak-brand-logo-picker__empty';
      empty.textContent = cfg.noResults || 'نتیجه‌ای یافت نشد';
      list.appendChild(empty);
      return;
    }

    matches.forEach(function (option) {
      var item = document.createElement('li');
      item.className = 'ak-brand-logo-picker__option';
      item.dataset.value = option.value;

      if (String(option.value) === String(currentValue)) {
        item.classList.add('is-selected');
        item.setAttribute('aria-selected', 'true');
      }

      var logo = logoForValue(option.value);
      if (logo && logo.url) {
        var img = document.createElement('img');
        img.src = logo.url;
        img.alt = logo.filename || logo.slug;
        img.loading = 'lazy';
        item.appendChild(img);
      }

      var label = document.createElement('span');
      label.className = 'ak-brand-logo-picker__option-label';
      label.textContent = option.label || option.value || cfg.noneLabel || '—';
      item.appendChild(label);

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

  function enhanceSelect(select) {
    if (!(select instanceof HTMLSelectElement) || select.dataset.akBrandLogoEnhanced === '1') {
      return;
    }

    select.dataset.akBrandLogoEnhanced = '1';

    var picker = document.createElement('div');
    picker.className = 'ak-brand-logo-picker';

    var control = document.createElement('button');
    control.type = 'button';
    control.className = 'ak-brand-logo-picker__control';
    control.innerHTML =
      '<img class="ak-brand-logo-picker__thumb is-empty" alt="" />' +
      '<span class="ak-brand-logo-picker__value"></span>' +
      '<span class="ak-brand-logo-picker__arrow" aria-hidden="true"></span>';

    var dropdown = document.createElement('div');
    dropdown.className = 'ak-brand-logo-picker__dropdown';
    dropdown.hidden = true;

    var searchInput = document.createElement('input');
    searchInput.type = 'search';
    searchInput.className = 'ak-brand-logo-picker__search';
    searchInput.placeholder = cfg.placeholder || 'جستجو یا انتخاب لوگوی برند…';
    searchInput.autocomplete = 'off';

    var list = document.createElement('ul');
    list.className = 'ak-brand-logo-picker__list';
    list.setAttribute('role', 'listbox');

    dropdown.appendChild(searchInput);
    dropdown.appendChild(list);

    select.classList.add('ak-brand-logo-picker__native');
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
    document
      .querySelectorAll('.acf-field[data-key="field_ak_category_brand_logo"] select')
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
