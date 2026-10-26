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
    '<input type="checkbox" class="ak-skip-watermark-input" value="1" autocomplete="off" /> ' +
    (cfg.label || '') +
    '</label>' +
    (cfg.help
      ? '<span class="ak-skip-watermark-help description">' + cfg.help + '</span>'
      : '') +
    '</p>';

  function wantsSkip() {
    return $('.ak-skip-watermark-input').filter(':checked').length > 0;
  }

  /**
   * Only send the flag when skipping. Absence of the flag = watermark ON.
   * Always refresh on BeforeUpload so plupload does not keep a stale copy.
   */
  function writeParams(params) {
    if (!params || typeof params !== 'object') {
      return;
    }
    if (wantsSkip()) {
      params[PARAM] = '1';
    } else {
      delete params[PARAM];
    }
  }

  function bindBeforeUpload(pluploader) {
    if (!pluploader || typeof pluploader.bind !== 'function') {
      return;
    }
    if (pluploader.__akWmSkipBound) {
      return;
    }
    pluploader.__akWmSkipBound = true;
    pluploader.bind('BeforeUpload', function (up) {
      up.settings = up.settings || {};
      up.settings.multipart_params = up.settings.multipart_params || {};
      writeParams(up.settings.multipart_params);
    });
  }

  function syncAllUploaders() {
    if (window._wpPluploadSettings && _wpPluploadSettings.defaults) {
      _wpPluploadSettings.defaults.multipart_params =
        _wpPluploadSettings.defaults.multipart_params || {};
      writeParams(_wpPluploadSettings.defaults.multipart_params);
    }

    if (window.wp && wp.Uploader && wp.Uploader.defaults) {
      wp.Uploader.defaults.multipart_params = wp.Uploader.defaults.multipart_params || {};
      writeParams(wp.Uploader.defaults.multipart_params);
    }

    if (window.uploader) {
      if (window.uploader.settings) {
        window.uploader.settings.multipart_params = window.uploader.settings.multipart_params || {};
        writeParams(window.uploader.settings.multipart_params);
      }
      bindBeforeUpload(window.uploader);
    }

    if (window.wp && wp.media && wp.media.frame && wp.media.frame.uploader && wp.media.frame.uploader.uploader) {
      var frameUp = wp.media.frame.uploader.uploader;
      if (frameUp.settings) {
        frameUp.settings.multipart_params = frameUp.settings.multipart_params || {};
        writeParams(frameUp.settings.multipart_params);
      }
      bindBeforeUpload(frameUp);
    }

    var $hidden = $('input[type="hidden"].ak-skip-watermark-hidden');
    if ($hidden.length) {
      $hidden.val(wantsSkip() ? '1' : '0');
    }
  }

  function injectInto($root) {
    if (!$root || !$root.length || $root.find('.ak-skip-watermark-wrap').length) {
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
    // Never leave a checked box from browser form restore.
    if (!$('.ak-skip-watermark-input').filter(':checked').length) {
      $('.ak-skip-watermark-input').prop('checked', false);
    }
    syncAllUploaders();
  }

  $(document).on('change', '.ak-skip-watermark-input', function () {
    var checked = !!this.checked;
    $('.ak-skip-watermark-input').prop('checked', checked);
    syncAllUploaders();
  });

  $(function () {
    // Strip name from any PHP-rendered checkbox so it cannot leak into POST by itself.
    $('.ak-skip-watermark-input').removeAttr('name').attr('autocomplete', 'off');

    var $htmlForm = $('#file-form, #html-upload-ui').closest('form');
    if ($htmlForm.length && !$htmlForm.find('input.ak-skip-watermark-hidden').length) {
      $htmlForm.prepend(
        '<input type="hidden" class="ak-skip-watermark-hidden" name="' + PARAM + '" value="0" autocomplete="off" />'
      );
    }

    injectAll();
    bindBeforeUpload(window.uploader);
    syncAllUploaders();

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

  if (window.wp && wp.Uploader && wp.Uploader.prototype && !wp.Uploader.prototype.__akWmSkipPatched) {
    var originalInit = wp.Uploader.prototype.init;
    if (typeof originalInit === 'function') {
      wp.Uploader.prototype.__akWmSkipPatched = true;
      wp.Uploader.prototype.init = function () {
        originalInit.apply(this, arguments);
        if (this.uploader) {
          bindBeforeUpload(this.uploader);
          if (this.uploader.settings) {
            this.uploader.settings.multipart_params = this.uploader.settings.multipart_params || {};
            writeParams(this.uploader.settings.multipart_params);
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
      this.$('.ak-skip-watermark-input').removeAttr('name').attr('autocomplete', 'off');
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
