(function ($) {
  'use strict';

  var PREVIEW_SIZE = 36;

  var defaults = (window.akThemeOptions && window.akThemeOptions.socialSvgDefaults) || {};

  function isSocialSvgField($field) {
    return $field.hasClass('ak-social-svg-field');
  }

  function guessNetworkFromTitle($field) {
    var $row = $field.closest('.acf-row, .acf-block-body');
    var title = $.trim($row.find('[data-name="social_title"] input').val() || '').toLowerCase();

    if (title.indexOf('اینستا') !== -1 || title.indexOf('instagram') !== -1) {
      return 'instagram';
    }
    if (title.indexOf('تلگر') !== -1 || title.indexOf('telegram') !== -1) {
      return 'telegram';
    }
    if (title.indexOf('یوتیوب') !== -1 || title.indexOf('youtube') !== -1) {
      return 'youtube';
    }
    if (title.indexOf('لینکدین') !== -1 || title.indexOf('linkedin') !== -1) {
      return 'linkedin';
    }

    return '';
  }

  function getNetwork($field) {
    return guessNetworkFromTitle($field);
  }

  function getDefaultSvg(network) {
    return defaults[network] || '';
  }

  function normalizePreviewSvg(rawSvg) {
    var trimmed = $.trim(rawSvg || '');
    if (!trimmed) {
      return '';
    }

    var template = document.createElement('template');
    template.innerHTML = trimmed;
    var svg = template.content.querySelector('svg');
    if (!svg) {
      return '';
    }

    if (!svg.getAttribute('viewBox')) {
      svg.setAttribute('viewBox', '0 0 24 24');
    }

    svg.setAttribute('width', String(PREVIEW_SIZE));
    svg.setAttribute('height', String(PREVIEW_SIZE));
    svg.setAttribute('focusable', 'false');
    svg.setAttribute('aria-hidden', 'true');
    svg.style.width = PREVIEW_SIZE + 'px';
    svg.style.height = PREVIEW_SIZE + 'px';
    svg.style.display = 'block';
    svg.style.maxWidth = PREVIEW_SIZE + 'px';
    svg.style.maxHeight = PREVIEW_SIZE + 'px';

    return svg.outerHTML;
  }

  function renderPreview($field) {
    var $textarea = $field.find('textarea');
    var network = getNetwork($field);
    var value = $.trim($textarea.val());
    var svg = normalizePreviewSvg(value || getDefaultSvg(network));

    var $preview = $field.find('.ak-social-svg-preview');
    if (!$preview.length) {
      $preview = $(
        '<div class="ak-social-svg-preview" aria-hidden="true">' +
          '<span class="ak-social-svg-preview__icon"></span>' +
        '</div>'
      );
      $field.find('.acf-input').append($preview);

      var $toolbar = $('<div class="ak-social-svg-toolbar"></div>');
      var $reset = $(
        '<button type="button" class="button button-secondary ak-social-svg-reset">بازنشانی به پیش‌فرض</button>'
      );
      var $clear = $(
        '<button type="button" class="button button-secondary ak-social-svg-clear">پاک کردن آیکن</button>'
      );
      $toolbar.append($reset, $clear);
      $field.find('.acf-input').append($toolbar);

      $reset.on('click', function (event) {
        event.preventDefault();
        var preset = getDefaultSvg(getNetwork($field));
        $textarea.val(preset).trigger('input');
      });

      $clear.on('click', function (event) {
        event.preventDefault();
        $textarea.val('').trigger('input');
      });
    }

    $field.find('.ak-social-svg-preview__icon').html(svg);
    $field.find('.ak-social-svg-reset').toggle(!!getNetwork($field));
  }

  function initSocialSvgField($field) {
    if (!isSocialSvgField($field)) {
      return;
    }

    renderPreview($field);
    $field.find('textarea').off('input.akSocialSvg').on('input.akSocialSvg', function () {
      renderPreview($field);
    });

    var $row = $field.closest('.acf-row, .acf-block-body');
    $row.find('[data-name="social_title"] input')
      .off('input.akSocialSvgTitle')
      .on('input.akSocialSvgTitle', function () {
        renderPreview($field);
      });
  }

  function initAllSocialSvgFields($scope) {
    ($scope || $(document))
      .find('.acf-field')
      .each(function () {
        initSocialSvgField($(this));
      });
  }

  /**
   * Theme options uses left tabs. ACF validates the whole form, so required
   * fields on inactive tabs block save. Limit validation to the active tab.
   */
  function isThemeOptionsPage() {
    return document.body && document.body.classList.contains('toplevel_page_asrekhodro-settings');
  }

  function getActiveTabKey($form) {
    var $scope = $form && $form.length ? $form : $(document);
    var $active = $scope.find('.acf-tab-group .acf-tab-button.-active, .acf-tab-group a.acf-tab-button.active').first();
    if (!$active.length) {
      $active = $('.acf-tab-group .acf-tab-button.-active').first();
    }
    return $active.length ? String($active.data('key') || '') : '';
  }

  function fieldBelongsToActiveTab($field, activeTabKey) {
    if (!$field || !$field.length) {
      return true;
    }

    // Hidden by conditional logic → never fail validation.
    if ($field.hasClass('acf-hidden') || $field.is('[hidden]') || !$field.is(':visible')) {
      return false;
    }

    if (!activeTabKey) {
      return true;
    }

    // Walk previous siblings until the nearest tab field.
    var $prevTab = $field.prevAll('.acf-field-tab').first();
    if (!$prevTab.length) {
      return true;
    }

    return String($prevTab.data('key') || '') === activeTabKey;
  }

  function filterErrorsToActiveTab(json, $form) {
    if (!isThemeOptionsPage() || !json || json.valid == 1 || !json.errors || !json.errors.length) {
      return json;
    }

    var activeTabKey = getActiveTabKey($form);
    var remaining = [];

    json.errors.forEach(function (err) {
      if (!err || !err.input) {
        remaining.push(err);
        return;
      }

      var $input = $form.find('[name="' + err.input + '"]').first();
      if (!$input.length) {
        // Keep unknown errors (safer) unless clearly not visible.
        remaining.push(err);
        return;
      }

      var $field = $input.closest('.acf-field');
      if (fieldBelongsToActiveTab($field, activeTabKey)) {
        remaining.push(err);
      }
    });

    if (!remaining.length) {
      json.valid = 1;
      delete json.errors;
    } else {
      json.errors = remaining;
    }

    return json;
  }

  function syncActiveTabInput($form) {
    if (!isThemeOptionsPage()) {
      return;
    }

    var key = getActiveTabKey($form);
    var $input = $form.find('input[name="ak_active_options_tab"]');
    if (!$input.length) {
      $input = $('<input type="hidden" name="ak_active_options_tab" />');
      $form.append($input);
    }
    $input.val(key);
  }

  if (window.acf) {
    acf.addAction('ready', function () {
      initAllSocialSvgFields();
    });
    acf.addAction('append', function ($el) {
      initAllSocialSvgFields($el);
    });

    acf.addAction('validation_begin', function ($form) {
      syncActiveTabInput($form);
    });

    acf.addFilter('validation_complete', function (json, $form) {
      return filterErrorsToActiveTab(json, $form);
    });

    acf.addFilter('prepare_for_ajax', function (data) {
      if (!isThemeOptionsPage()) {
        return data;
      }
      data.ak_active_options_tab = getActiveTabKey($(document));
      return data;
    });
  } else {
    $(function () {
      initAllSocialSvgFields();
    });
  }
})(jQuery);
