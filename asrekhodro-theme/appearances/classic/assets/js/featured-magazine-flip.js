/**
 * Featured magazine — StPageFlip (official API pattern).
 * @see https://nodlik.github.io/StPageFlip/
 */
(function () {
  "use strict";

  var PERSIAN_DIGITS = "۰۱۲۳۴۵۶۷۸۹";

  function toPersianNumber(value) {
    if (typeof window.akToPersianDigits === "function") {
      return window.akToPersianDigits(value);
    }

    return String(value).replace(/\d/g, function (digit) {
      return PERSIAN_DIGITS[Number(digit)];
    });
  }

  function initMagazine(root) {
    if (typeof St === "undefined" || !St.PageFlip) {
      return;
    }

    var book = root.querySelector(".mag-flip__book");
    var viewport = root.querySelector(".mag-flip__viewport");
    if (!book || !viewport) {
      return;
    }

    var pageNodes = book.querySelectorAll(".mag-flip__page");
    if (pageNodes.length < 2) {
      return;
    }

    var pageCount = pageNodes.length;
    var pager = root.querySelector(".mag-flip__pager");
    var pagerCurrent = root.querySelector(".mag-flip__pager-current");
    var pagerTotal = root.querySelector(".mag-flip__pager-total");
    var cornerNext = root.querySelector(".mag-flip__corner--next");
    var cornerPrev = root.querySelector(".mag-flip__corner--prev");
    var pageFlip = null;
    var bootScheduled = false;
    var bootAttempts = 0;

    function updatePager(pageIndex) {
      if (pagerCurrent) {
        pagerCurrent.textContent = toPersianNumber(pageIndex + 1);
      }
      if (pagerTotal) {
        pagerTotal.textContent = toPersianNumber(pageCount);
      }
      if (pager) {
        pager.classList.toggle("is-on-cover", pageIndex === 0);
      }
      if (cornerPrev) {
        cornerPrev.classList.toggle("is-hidden", pageIndex <= 0);
      }
      if (cornerNext) {
        cornerNext.classList.toggle("is-hidden", pageIndex >= pageCount - 1);
      }
    }

    function getFlipMetrics() {
      var totalW = root.clientWidth;
      var totalH = root.clientHeight;
      var spread = totalW >= 480 && window.matchMedia("(min-width: 1024px)").matches;

      return {
        pageWidth: spread ? Math.max(Math.floor(totalW / 2), 200) : totalW,
        pageHeight: Math.max(totalH, 280),
        spread: spread,
      };
    }

    function syncHeight() {
      var grid = root.closest(".news-grid--featured");
      if (!grid) {
        return;
      }

      if (window.matchMedia("(max-width: 1023px)").matches) {
        root.style.height = "";
        root.style.minHeight = "";
        if (pageFlip) {
          pageFlip.update();
        }
        return;
      }

      var card = grid.querySelector(".card");
      if (!card) {
        return;
      }

      var height = card.offsetHeight;
      if (height > 0) {
        root.style.height = height + "px";
        root.style.minHeight = height + "px";
        if (pageFlip) {
          pageFlip.update();
        }
      }
    }

    function bootFlip() {
      if (pageFlip) {
        return;
      }

      bootAttempts += 1;
      syncHeight();

      var metrics = getFlipMetrics();

      if ((metrics.pageWidth < 200 || metrics.pageHeight < 280) && bootAttempts < 120) {
        window.requestAnimationFrame(bootFlip);
        return;
      }

      pageFlip = new St.PageFlip(book, {
        width: metrics.pageWidth,
        height: metrics.pageHeight,
        size: "stretch",
        minWidth: 200,
        maxWidth: 1200,
        minHeight: 280,
        maxHeight: 2400,
        showCover: true,
        maxShadowOpacity: 0.5,
        mobileScrollSupport: true,
        useMouseEvents: true,
        disableFlipByClick: false,
        clickEventForward: true,
        drawShadow: true,
        flippingTime: 800,
        usePortrait: !metrics.spread,
        autoSize: false,
        startPage: 0,
      });

      pageFlip.on("flip", function (event) {
        updatePager(event.data);
      });

      pageFlip.on("init", function () {
        updatePager(pageFlip.getCurrentPageIndex());
        syncHeight();
      });

      pageFlip.on("changeOrientation", function () {
        syncHeight();
        pageFlip.update();
      });

      pageFlip.on("changeState", function (event) {
        var flipping = event.data === "flipping";
        viewport.classList.toggle("is-flipping", flipping);
        root.classList.toggle("mag-flip--active", flipping);
      });

      pageFlip.loadFromHTML(Array.prototype.slice.call(pageNodes));
    }

    function maybeBootFlip() {
      if (pageFlip || bootScheduled) {
        return;
      }
      syncHeight();
      if (root.clientWidth < 200 || root.clientHeight < 280) {
        return;
      }
      bootScheduled = true;
      bootFlip();
    }

    function watchHeight() {
      syncHeight();
      if (pageFlip) {
        pageFlip.update();
      } else {
        maybeBootFlip();
      }
    }

    if (cornerNext) {
      cornerNext.addEventListener("click", function (event) {
        event.preventDefault();
        if (pageFlip) {
          pageFlip.flipNext("bottom");
        }
      });
    }

    if (cornerPrev) {
      cornerPrev.addEventListener("click", function (event) {
        event.preventDefault();
        if (pageFlip) {
          pageFlip.flipPrev("bottom");
        }
      });
    }

    if (pagerTotal) {
      pagerTotal.textContent = toPersianNumber(pageCount);
    }

    watchHeight();
    maybeBootFlip();

    window.addEventListener("resize", watchHeight);

    var grid = root.closest(".news-grid--featured");
    if (grid && window.ResizeObserver) {
      var resizeObserver = new ResizeObserver(watchHeight);
      resizeObserver.observe(root);
      grid.querySelectorAll(".card").forEach(function (card) {
        resizeObserver.observe(card);
      });
      grid.querySelectorAll("img").forEach(function (img) {
        if (!img.complete) {
          img.addEventListener("load", watchHeight);
        }
      });
    }
  }

  function init() {
    document.querySelectorAll("[data-mag-flip]").forEach(initMagazine);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
