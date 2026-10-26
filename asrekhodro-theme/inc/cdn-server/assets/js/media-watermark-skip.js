(function (window, document, $) {
  'use strict';

  var cfg = window.akMediaWatermarkSkip;
  if (!$ || !cfg || !cfg.param) {
    return;
  }

  var PARAM = cfg.param;
  var INPUT_SELECTOR = '.ak-skip-watermark-input';
  var WRAP_CLASS = 'ak-skip-watermark-wrap';

  // Single source of truth. Always starts unchecked on page load.
  var skip = false;

  function escapeHtml(text) {
    return String(text || '').replace(/[&<>"']/g, function (ch) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
  }

  function checkboxHtml() {
    return (
      '<p class="' + WRAP_CLASS + '">' +
      '<label class="ak-skip-watermark-label">' +
      '<input type="checkbox" class="ak-skip-watermark-input" value="1" autocomplete="off"' +
      (skip ? ' checked="checked"' : '') +
      ' /> ' +
      escapeHtml(cfg.label) +
      '</label>' +
      (cfg.help ? '<span class="ak-skip-watermark-help description">' + escapeHtml(cfg.help) + '</span>' : '') +
      '</p>'
    );
  }

  /**
   * plupload reads up.settings.multipart_params when each file is sent,
   * so writing it in BeforeUpload reflects the checkbox at that exact moment.
   */
  function bindUploader(up) {
    if (!up || typeof up.bind !== 'function' || up.akWatermarkSkipBound) {
      return;
    }
    up.akWatermarkSkipBound = true;

    up.bind('BeforeUpload', function (uploader) {
      uploader.settings = uploader.settings || {};
      uploader.settings.multipart_params = uploader.settings.multipart_params || {};
      uploader.settings.multipart_params[PARAM] = skip ? '1' : '0';
    });
  }

  // Media modal / grid uploaders: wp.Uploader calls this.init() at the end of its constructor.
  if (window.wp && wp.Uploader && wp.Uploader.prototype && !wp.Uploader.prototype.akWatermarkSkipWrapped) {
    var originalInit = wp.Uploader.prototype.init;
    wp.Uploader.prototype.akWatermarkSkipWrapped = true;
    wp.Uploader.prototype.init = function () {
      bindUploader(this.uploader);
      if (typeof originalInit === 'function') {
        return originalInit.apply(this, arguments);
      }
    };
  }

  function bindKnownUploaders() {
    // media-new.php (plupload-handlers.js).
    bindUploader(window.uploader);

    // Uploaders created before this script ran (defensive).
    if (window.wp && wp.media && wp.media.frame && wp.media.frame.uploader && wp.media.frame.uploader.uploader) {
      bindUploader(wp.media.frame.uploader.uploader.uploader);
    }
  }

  function injectIntoModals() {
    $('.uploader-inline-content').each(function () {
      var $content = $(this);
      if ($content.children('.' + WRAP_CLASS).length) {
        return;
      }
      var $anchor = $content.children('.upload-ui').first();
      if ($anchor.length) {
        $anchor.before(checkboxHtml());
      } else {
        $content.prepend(checkboxHtml());
      }
    });
  }

  $(document).on('change', INPUT_SELECTOR, function () {
    skip = !!this.checked;
    $(INPUT_SELECTOR).not(this).prop('checked', skip);
  });

  $(function () {
    // Ignore any checked state restored by the browser.
    skip = false;
    $(INPUT_SELECTOR).prop('checked', false);

    bindKnownUploaders();
    injectIntoModals();

    if (window.MutationObserver && document.body) {
      var queued = false;
      new MutationObserver(function () {
        if (queued) {
          return;
        }
        queued = true;
        window.requestAnimationFrame(function () {
          queued = false;
          injectIntoModals();
          bindKnownUploaders();
        });
      }).observe(document.body, { childList: true, subtree: true });
    }
  });
})(window, document, window.jQuery);
