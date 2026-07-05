(function ($) {
  'use strict';

  var PREVIEW_SIZE = 36;

  var defaults = (window.akThemeOptions && window.akThemeOptions.socialSvgDefaults) || {};

  var fieldNetworkMap = {
    field_ak_social_instagram_svg: 'instagram',
    field_ak_social_telegram_svg: 'telegram',
    field_ak_social_youtube_svg: 'youtube',
    field_ak_social_linkedin_svg: 'linkedin',
  };

  function isSocialSvgField($field) {
    var key = $field.data('key');
    return $field.hasClass('ak-social-svg-field') || !!fieldNetworkMap[key];
  }

  function getNetwork($field) {
    return fieldNetworkMap[$field.data('key')] || '';
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
      $toolbar.append($reset);
      $field.find('.acf-input').append($toolbar);

      $reset.on('click', function (event) {
        event.preventDefault();
        $textarea.val(getDefaultSvg(network)).trigger('input');
      });
    }

    $field.find('.ak-social-svg-preview__icon').html(svg);
  }

  function initSocialSvgField($field) {
    if (!isSocialSvgField($field)) {
      return;
    }

    renderPreview($field);
    $field.find('textarea').off('input.akSocialSvg').on('input.akSocialSvg', function () {
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
