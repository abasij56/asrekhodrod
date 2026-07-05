/**
 * Match تازه‌ها & فراتر از خبر column height to خبر اول (no extra row gap).
 */
(function () {
  "use strict";

  var MQ_STACK = "(max-width: 1100px)";

  function debounce(fn, ms) {
    var timer;
    return function () {
      clearTimeout(timer);
      timer = setTimeout(fn, ms);
    };
  }

  function balanceTopColumns() {
    var top = document.querySelector(".rfarda-top");
    if (!top) {
      return;
    }

    var lead = top.querySelector(".rfarda-top__col--lead");
    var latest = top.querySelector(".rfarda-top__col--latest");
    var beyond = top.querySelector(".rfarda-top__col--beyond");

    if (!lead || !latest || !beyond) {
      return;
    }

    latest.style.maxHeight = "";
    beyond.style.maxHeight = "";

    if (window.matchMedia(MQ_STACK).matches) {
      return;
    }

    var height = lead.getBoundingClientRect().height;
    if (height <= 0) {
      return;
    }

    latest.style.maxHeight = Math.ceil(height) + "px";
    beyond.style.maxHeight = Math.ceil(height) + "px";
  }

  function scheduleBalance() {
    window.requestAnimationFrame(balanceTopColumns);
  }

  function watchImages(root) {
    root.querySelectorAll("img").forEach(function (img) {
      if (img.complete) {
        return;
      }
      img.addEventListener("load", scheduleBalance);
      img.addEventListener("error", scheduleBalance);
    });
  }

  function init() {
    var top = document.querySelector(".rfarda-top");
    if (!top) {
      return;
    }

    balanceTopColumns();
    watchImages(top);

    var lead = top.querySelector(".rfarda-top__col--lead");
    if (lead && window.ResizeObserver) {
      new ResizeObserver(scheduleBalance).observe(lead);
    }

    window.addEventListener("resize", debounce(scheduleBalance, 120));

    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(scheduleBalance);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  window.addEventListener("load", scheduleBalance);
})();
