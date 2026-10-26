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
    return $('.ak-skip-watermark-input:checked').length > 0;
  }

  /**
   * Only when checked: send ak_skip_watermark=1.
   * When unchecked: remove the key entirely so PHP watermarks as normal.
   */
  function applySkipFlag(params) {
    if (!params || typeof params !== 'object') {
      return;
    }
    if (wantsSkip()) {
      params[PARAM] = '1';
    } else if (Object.prototype.hasOwnProperty.call(params, PARAM)) {
      delete params[PARAM];
    }
  }

  function bindBeforeUpload(pluploader) {
    if (!pluploader || typeof pluploader.bind !== 'function' || pluploader.__akWmSkipBound) {
      return;
    }
    pluploader.__akWmSkipBound = true;
    pluploader.bind('BeforeUpload', function (up) {
      up.settings = up.settings || {};
      up.settings.multipart_params = up.settings.multipart_params || {};
      applySkipFlag(up.settings.multipart_params);
    });
  }

  function refreshUploaderFlags() {
    if (window._wpPluploadSettings && _wpPluploadSettings.defaults) {
      _wpPluploadSettings.defaults.multipart_params =
        _wpPluploadSettings.defaults.multipart_params || {};
      applySkipFlag(_wpPluploadSettings.defaults.multipart_params);
    }

    if (window.wp && wp.Uploader && wp.Uploader.defaults) {
      wp.Uploader.defaults.multipart_params = wp.Uploader.defaults.multipart_params || {};
      applySkipFlag(wp.Uploader.defaults.multipart_params);
    }

    if (window.uploader) {
      if (window.uploader.settings) {
        window.uploader.settings.multipart_params = window.uploader.settings.multipart_params || {};
        applySkipFlag(window.uploader.settings.multipart_params);
      }
      bindBeforeUpload(window.uploader);
    }

    if (window.wp && wp.media && wp.media.frame && wp.media.frame.uploader && wp.media.frame.uploader.uploader) {
      var up = wp.media.frame.uploader.uploader;
      if (up.settings) {
        up.settings.multipart_params = up.settings.multipart_params || {};
        applySkipFlag(up.settings.multipart_params);
      }
      bindBeforeUpload(up);
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

  function boot() {
    injectInto($('#plupload-upload-ui'));
    injectInto($('.uploader-inline'));
    injectInto($('.media-frame .uploader-inline'));
    injectInto($('#html-upload-ui'));

    // Never inherit a checked state from browser form restore.
    $('.ak-skip-watermark-input').prop('checked', false).removeAttr('name').attr('autocomplete', 'off');

    refreshUploaderFlags();
    bindBeforeUpload(window.uploader);
  }

  $(document).on('change', '.ak-skip-watermark-input', function () {
    var checked = !!this.checked;
    $('.ak-skip-watermark-input').prop('checked', checked);
    refreshUploaderFlags();
  });

  $(boot);

  var tries = 0;
  var timer = window.setInterval(function () {
    bindBeforeUpload(window.uploader);
    refreshUploaderFlags();
    tries += 1;
    if ((window.uploader && window.uploader.__akWmSkipBound) || tries > 40) {
      window.clearInterval(timer);
    }
  }, 250);

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
            applySkipFlag(this.uploader.settings.multipart_params);
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
      this.$('.ak-skip-watermark-input').prop('checked', false).removeAttr('name');
      if (this.controller && this.controller.uploader && this.controller.uploader.uploader) {
        bindBeforeUpload(this.controller.uploader.uploader);
      }
      refreshUploaderFlags();
    };
  }
})(window, window.jQuery);
