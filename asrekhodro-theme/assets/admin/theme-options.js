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

  if (window.acf) {
    acf.addAction('ready', function () {
      initAllSocialSvgFields();
    });
    acf.addAction('append', function ($el) {
      initAllSocialSvgFields($el);
    });
  } else {
    $(function () {
      initAllSocialSvgFields();
    });
  }
})(jQuery);
