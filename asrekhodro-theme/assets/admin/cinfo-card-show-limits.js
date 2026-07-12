(function ($) {
  'use strict';

  var cfg = window.akCinfoCardLimits || {};

  function countChecked(repeaterField, toggleKey) {
    var count = 0;
    repeaterField.find('.acf-row').each(function () {
      var row = $(this);
      if (row.hasClass('acf-clone')) {
        return;
      }
      var toggle = row.find('.acf-field[data-key="' + toggleKey + '"] input[type="checkbox"]');
      if (toggle.length && toggle.prop('checked')) {
        count += 1;
      }
    });
    return count;
  }

  function applyLimits(repeaterField, toggleKey, max) {
    var checked = countChecked(repeaterField, toggleKey);
    repeaterField.find('.acf-row').each(function () {
      var row = $(this);
      if (row.hasClass('acf-clone')) {
        return;
      }
      var field = row.find('.acf-field[data-key="' + toggleKey + '"]');
      var toggle = field.find('input[type="checkbox"]');
      if (!toggle.length) {
        return;
      }
      var disable = !toggle.prop('checked') && checked >= max;
      field.toggleClass('is-card-limit-disabled', disable);
      toggle.prop('disabled', disable);
    });
  }

  function bindRepeater(repeaterKey, toggleKey, max) {
    var repeaterField = $('.acf-field[data-key="' + repeaterKey + '"]');
    if (!repeaterField.length) {
      return;
    }

    applyLimits(repeaterField, toggleKey, max);

    repeaterField.off('.akCardLimits');
    repeaterField.on('change.akCardLimits', '.acf-field[data-key="' + toggleKey + '"] input[type="checkbox"]', function () {
      var toggle = $(this);
      if (toggle.prop('checked') && countChecked(repeaterField, toggleKey) > max) {
        toggle.prop('checked', false);
        window.alert(repeaterKey === cfg.heroFieldKey ? cfg.heroMessage : cfg.factsMessage);
      }
      applyLimits(repeaterField, toggleKey, max);
    });
  }

  function boot() {
    if (cfg.heroFieldKey && cfg.heroToggleKey) {
      bindRepeater(cfg.heroFieldKey, cfg.heroToggleKey, cfg.heroMax || 4);
    }
    if (cfg.factsFieldKey && cfg.factsToggleKey) {
      bindRepeater(cfg.factsFieldKey, cfg.factsToggleKey, cfg.factsMax || 3);
    }
  }

  $(document).ready(boot);

  if (window.acf && typeof window.acf.add_action === 'function') {
    window.acf.add_action('ready', boot);
    window.acf.add_action('append', boot);
  }
})(jQuery);
