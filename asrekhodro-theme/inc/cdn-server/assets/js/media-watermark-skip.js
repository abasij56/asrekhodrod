(function (window, $) {
  'use strict';

  if (!$ || !window.akMediaWatermarkSkip) {
    return;
  }

  var cfg = window.akMediaWatermarkSkip;
  var PARAM = cfg.param || 'ak_skip_watermark';
  var checkboxHtml =
    '<p class="ak-skip-watermark-wrap">' +
    '<label class="ak-skip-watermark-label">' +
    '<input type="checkbox" class="ak-skip-watermark-input" value="1" /> ' +
    (cfg.label || '') +
    '</label>' +
    (cfg.help
      ? '<span class="ak-skip-watermark-help description">' + cfg.help + '</span>'
      : '') +
    '</p>';

  function isChecked() {
    return $('.ak-skip-watermark-input:checked').length > 0;
  }

  function skipValue() {
    return isChecked() ? '1' : '0';
  }

  function applyToMultipart(params) {
    if (!params || typeof params !== 'object') {
      return;
    }
    params[PARAM] = skipValue();
  }

  /**
   * Plupload copies multipart_params at init; updating defaults later is ignored.
   * Set the flag on every file, right before the request is sent.
   */
  function bindBeforeUpload(pluploader) {
    if (!pluploader || typeof pluploader.bind !== 'function' || pluploader.__akWmSkipBound) {
      return;
    }
    pluploader.__akWmSkipBound = true;
    pluploader.bind('BeforeUpload', function (up) {
      up.settings = up.settings || {};
      up.settings.multipart_params = up.settings.multipart_params || {};
      applyToMultipart(up.settings.multipart_params);
    });
  }

  function syncAllUploaders() {
    var val = skipValue();

    if (window._wpPluploadSettings && _wpPluploadSettings.defaults) {
      _wpPluploadSettings.defaults.multipart_params =
        _wpPluploadSettings.defaults.multipart_params || {};
      _wpPluploadSettings.defaults.multipart_params[PARAM] = val;
    }

    if (window.wp && wp.Uploader && wp.Uploader.defaults) {
      wp.Uploader.defaults.multipart_params = wp.Uploader.defaults.multipart_params || {};
      wp.Uploader.defaults.multipart_params[PARAM] = val;
    }

    // media-new.php / upload.php legacy global plupload instance
    if (window.uploader) {
      if (window.uploader.settings) {
        window.uploader.settings.multipart_params = window.uploader.settings.multipart_params || {};
        window.uploader.settings.multipart_params[PARAM] = val;
      }
      bindBeforeUpload(window.uploader);
    }

    if (window.wp && wp.media && wp.media.frame && wp.media.frame.uploader && wp.media.frame.uploader.uploader) {
      var frameUp = wp.media.frame.uploader.uploader;
      if (frameUp.settings) {
        frameUp.settings.multipart_params = frameUp.settings.multipart_params || {};
        frameUp.settings.multipart_params[PARAM] = val;
      }
      bindBeforeUpload(frameUp);
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
  }

  function injectAll() {
    injectInto($('#plupload-upload-ui'));
    injectInto($('.uploader-inline'));
    injectInto($('.media-frame .uploader-inline'));
    injectInto($('#html-upload-ui'));
    syncAllUploaders();
  }

  $(document).on('change', '.ak-skip-watermark-input', function () {
    var checked = !!this.checked;
    $('.ak-skip-watermark-input').prop('checked', checked);
    syncAllUploaders();
  });

  $(function () {
    var $htmlForm = $('#file-form, #html-upload-ui').closest('form');
    if ($htmlForm.length && !$htmlForm.find('input.ak-skip-watermark-hidden').length) {
      $htmlForm.prepend(
        '<input type="hidden" class="ak-skip-watermark-hidden" name="' + PARAM + '" value="0" />'
      );
    }

    // PHP may already have rendered a checkbox; keep its class wiring.
    injectAll();
    bindBeforeUpload(window.uploader);
    syncAllUploaders();

    // media-new.php creates the global plupload instance a moment later.
    var tries = 0;
    var timer = window.setInterval(function () {
      bindBeforeUpload(window.uploader);
      syncAllUploaders();
      tries += 1;
      if ((window.uploader && window.uploader.__akWmSkipBound) || tries > 40) {
        window.clearInterval(timer);
      }
    }, 250);
  });

  if (window.wp && wp.Uploader && wp.Uploader.prototype) {
    var originalInit = wp.Uploader.prototype.init;
    if (typeof originalInit === 'function' && !wp.Uploader.prototype.__akWmSkipPatched) {
      wp.Uploader.prototype.__akWmSkipPatched = true;
      wp.Uploader.prototype.init = function () {
        originalInit.apply(this, arguments);
        if (this.uploader) {
          bindBeforeUpload(this.uploader);
          if (this.uploader.settings) {
            this.uploader.settings.multipart_params = this.uploader.settings.multipart_params || {};
            applyToMultipart(this.uploader.settings.multipart_params);
          }
        }
      };
    }
  }

  if (window.wp && wp.media && wp.media.view && wp.media.view.UploaderInline) {
    var proto = wp.media.view.UploaderInline.prototype;
    var originalReady = proto.ready;
    proto.ready = function () {
      if (typeof originalReady === 'function') {
        originalReady.apply(this, arguments);
      }
      injectInto(this.$el);
      if (this.controller && this.controller.uploader && this.controller.uploader.uploader) {
        bindBeforeUpload(this.controller.uploader.uploader);
      }
      syncAllUploaders();
    };
  }

  $(document).on('click', '.insert-media, .add_media, #menu-item-upload, .media-menu-item', function () {
    window.setTimeout(function () {
      injectAll();
      bindBeforeUpload(window.uploader);
      if (window.wp && wp.media && wp.media.frame && wp.media.frame.uploader && wp.media.frame.uploader.uploader) {
        bindBeforeUpload(wp.media.frame.uploader.uploader);
      }
      syncAllUploaders();
    }, 300);
  });
})(window, window.jQuery);
