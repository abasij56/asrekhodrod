(function (window, $) {
  'use strict';

  if (!$ || !window.akMediaWatermarkSkip) {
    return;
  }

  var cfg = window.akMediaWatermarkSkip;
  var PARAM = cfg.param || 'ak_skip_watermark';
  var checkboxHtml =
    '<p class="ak-skip-watermark-wrap">' +
    '<label for="ak-skip-watermark" class="ak-skip-watermark-label">' +
    '<input type="checkbox" id="ak-skip-watermark" class="ak-skip-watermark-input" name="' +
    PARAM +
    '" value="1" /> ' +
    (cfg.label || '') +
    '</label>' +
    (cfg.help
      ? '<span class="ak-skip-watermark-help description">' + cfg.help + '</span>'
      : '') +
    '</p>';

  function isChecked() {
    return $('.ak-skip-watermark-input:checked').length > 0;
  }

  function syncParams(skip) {
    var val = skip ? '1' : '0';

    if (window._wpPluploadSettings && _wpPluploadSettings.defaults) {
      _wpPluploadSettings.defaults.multipart_params =
        _wpPluploadSettings.defaults.multipart_params || {};
      _wpPluploadSettings.defaults.multipart_params[PARAM] = val;
    }

    if (window.wp && wp.Uploader && wp.Uploader.defaults) {
      wp.Uploader.defaults.multipart_params = wp.Uploader.defaults.multipart_params || {};
      wp.Uploader.defaults.multipart_params[PARAM] = val;
    }

    if (window.wp && wp.media && wp.media.frame && wp.media.frame.uploader && wp.media.frame.uploader.uploader) {
      var up = wp.media.frame.uploader.uploader;
      if (up.settings) {
        up.settings.multipart_params = up.settings.multipart_params || {};
        up.settings.multipart_params[PARAM] = val;
      }
    }

    $('input[type="hidden"][name="' + PARAM + '"].ak-skip-watermark-hidden').val(val);
  }

  function injectInto($root) {
    if (!$root || !$root.length) {
      return;
    }

    if ($root.find('.ak-skip-watermark-wrap').length) {
      return;
    }

    var $anchor = $root.find('.upload-ui, .uploader-inline-content, .media-uploader-status').first();
    if ($anchor.length) {
      $anchor.before(checkboxHtml);
    } else {
      $root.prepend(checkboxHtml);
    }

    syncParams(isChecked());
  }

  function injectAll() {
    injectInto($('#plupload-upload-ui'));
    injectInto($('.uploader-inline'));
    injectInto($('.media-frame .uploader-inline'));
    injectInto($('#html-upload-ui'));
  }

  $(document).on('change', '.ak-skip-watermark-input', function () {
    var checked = this.checked;
    $('.ak-skip-watermark-input').prop('checked', checked);
    syncParams(checked);
  });

  $(function () {
    // Ensure HTML browser-uploader posts the flag even when unchecked is not sent.
    var $htmlForm = $('#html-upload-ui').closest('form');
    if ($htmlForm.length && !$htmlForm.find('input.ak-skip-watermark-hidden').length) {
      $htmlForm.prepend(
        '<input type="hidden" class="ak-skip-watermark-hidden" name="' + PARAM + '" value="0" />'
      );
    }

    injectAll();
    syncParams(false);
  });

  if (window.wp && wp.media && wp.media.view && wp.media.view.UploaderInline) {
    var proto = wp.media.view.UploaderInline.prototype;
    var originalReady = proto.ready;
    proto.ready = function () {
      if (typeof originalReady === 'function') {
        originalReady.apply(this, arguments);
      }
      injectInto(this.$el);
      syncParams(isChecked());
    };
  }

  $(document).on('click', '.insert-media, .add_media, #menu-item-upload', function () {
    window.setTimeout(injectAll, 300);
  });
})(window, window.jQuery);
