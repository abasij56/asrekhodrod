(function () {
  'use strict';

  var cfg = window.akCategoryParentSelect || {};
  var terms = (cfg.termTree && cfg.termTree.terms) || {};

  function normalizeText(value) {
    return String(value || '')
      .replace(/\u00a0/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .toLowerCase();
  }

  function getTermId(value) {
    var id = parseInt(String(value), 10);
    return id > 0 ? id : 0;
  }

  function getOptions(select) {
    return Array.prototype.map.call(select.options, function (option) {
      var raw = option.text;

      return {
        value: option.value,
        label: raw.replace(/^[\u00a0\s]+/, '').replace(/\s+/g, ' ').trim(),
        displayLabel: raw.replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim(),
      };
    });
  }

  function optionMatchesQuery(option, query) {
    if (!query) {
      return true;
    }

    if (normalizeText(option.label).indexOf(query) !== -1) {
      return true;
    }

    var termId = getTermId(option.value);
    if (!termId) {
      return false;
    }

    var current = terms[String(termId)];
    while (current && current.parent) {
      var parent = terms[String(current.parent)];
      if (parent && normalizeText(parent.name).indexOf(query) !== -1) {
        return true;
      }
      current = parent;
    }

    return false;
  }

  function selectedLabel(select) {
    var option = select.options[select.selectedIndex];
    return option ? option.text.replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim() : '';
  }

  function closePicker(picker) {
    picker.classList.remove('is-open');
    picker.querySelector('.ak-parent-picker__dropdown').hidden = true;
  }

  function openPicker(picker) {
    picker.classList.add('is-open');
    var dropdown = picker.querySelector('.ak-parent-picker__dropdown');
    dropdown.hidden = false;
    var searchInput = picker.querySelector('.ak-parent-picker__search');
    searchInput.value = '';
    renderList(picker, '');
    window.setTimeout(function () {
      searchInput.focus();
    }, 0);
  }

  function renderList(picker, query) {
    var select = picker.querySelector('.ak-parent-picker__native');
    var list = picker.querySelector('.ak-parent-picker__list');
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
      empty.className = 'ak-parent-picker__empty';
      empty.textContent = cfg.noResults || 'نتیجه‌ای یافت نشد';
      list.appendChild(empty);
      return;
    }

    matches.forEach(function (option) {
      var item = document.createElement('li');
      item.className = 'ak-parent-picker__option';
      item.textContent = option.displayLabel;
      item.dataset.value = option.value;

      if (String(option.value) === String(currentValue)) {
        item.classList.add('is-selected');
        item.setAttribute('aria-selected', 'true');
      }

      item.addEventListener('mousedown', function (event) {
        event.preventDefault();
      });

      item.addEventListener('click', function () {
        select.value = option.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        picker.querySelector('.ak-parent-picker__value').textContent = option.displayLabel;
        closePicker(picker);
      });

      list.appendChild(item);
    });
  }

  function enhanceSelect(select) {
    if (!(select instanceof HTMLSelectElement) || select.dataset.akParentEnhanced === '1') {
      return;
    }

    select.dataset.akParentEnhanced = '1';

    var picker = document.createElement('div');
    picker.className = 'ak-parent-picker';

    var control = document.createElement('button');
    control.type = 'button';
    control.className = 'ak-parent-picker__control';
    control.innerHTML =
      '<span class="ak-parent-picker__value"></span><span class="ak-parent-picker__arrow" aria-hidden="true"></span>';

    var dropdown = document.createElement('div');
    dropdown.className = 'ak-parent-picker__dropdown';
    dropdown.hidden = true;

    var searchInput = document.createElement('input');
    searchInput.type = 'search';
    searchInput.className = 'ak-parent-picker__search';
    searchInput.placeholder = cfg.placeholder || 'جستجو یا انتخاب کتگوری والد…';
    searchInput.autocomplete = 'off';

    var list = document.createElement('ul');
    list.className = 'ak-parent-picker__list';
    list.setAttribute('role', 'listbox');

    dropdown.appendChild(searchInput);
    dropdown.appendChild(list);

    select.classList.add('ak-parent-picker__native');
    select.parentNode.insertBefore(picker, select);
    picker.appendChild(control);
    picker.appendChild(dropdown);
    picker.appendChild(select);

    picker.querySelector('.ak-parent-picker__value').textContent = selectedLabel(select);

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
      picker.querySelector('.ak-parent-picker__value').textContent = selectedLabel(select);
    });
  }

  function boot() {
    document
      .querySelectorAll('select#parent, select.ak-category-parent-select[name="parent"]')
      .forEach(enhanceSelect);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.addEventListener('load', boot);
})();
