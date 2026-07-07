/**
 * Asre Khodro Theme — front-end scripts
 */
(function () {
  "use strict";

  function toPersianDigits(value) {
    return String(value).replace(/\d/g, function (digit) {
      return "۰۱۲۳۴۵۶۷۸۹"[Number(digit)];
    });
  }

  window.akToPersianDigits = toPersianDigits;

  function initMobileNav() {
    var toggle = document.getElementById("nav-toggle");
    var nav = document.getElementById("main-nav");
    if (!toggle || !nav) return;

    toggle.addEventListener("click", function () {
      var isOpen = nav.classList.toggle("is-open");
      toggle.classList.toggle("is-active", isOpen);
      toggle.setAttribute("aria-expanded", String(isOpen));
    });

    nav.querySelectorAll(".main-nav__link, .main-nav__sublink").forEach(function (link) {
      link.addEventListener("click", function () {
        var submenuItem = link.closest(".main-nav__item.has-submenu");
        if (
          submenuItem &&
          link.classList.contains("main-nav__link") &&
          window.matchMedia("(max-width: 767px)").matches
        ) {
          return;
        }

        nav.classList.remove("is-open");
        toggle.classList.remove("is-active");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  function initMainNavSubmenus() {
    var nav = document.getElementById("main-nav");
    if (!nav) return;

    var mq = window.matchMedia("(max-width: 767px)");

    function isMobileNav() {
      return mq.matches;
    }

    function closeAll(exceptItem) {
      nav.querySelectorAll(".main-nav__item.is-submenu-open").forEach(function (item) {
        if (item !== exceptItem) {
          item.classList.remove("is-submenu-open");
          var toggle = item.querySelector(".main-nav__submenu-toggle");
          if (toggle) toggle.setAttribute("aria-expanded", "false");
        }
      });
    }

    function openSubmenu(item) {
      closeAll(item);
      item.classList.add("is-submenu-open");
      var toggle = item.querySelector(".main-nav__submenu-toggle");
      if (toggle) toggle.setAttribute("aria-expanded", "true");
    }

    function toggleSubmenu(item) {
      var willOpen = !item.classList.contains("is-submenu-open");
      if (willOpen) {
        openSubmenu(item);
      } else {
        item.classList.remove("is-submenu-open");
        var toggle = item.querySelector(".main-nav__submenu-toggle");
        if (toggle) toggle.setAttribute("aria-expanded", "false");
      }
    }

    nav.querySelectorAll(".main-nav__item.has-submenu").forEach(function (item) {
      var toggle = item.querySelector(".main-nav__submenu-toggle");
      var head = item.querySelector(".main-nav__item-head");
      var link = head ? head.querySelector(".main-nav__link") : null;

      if (toggle) {
        toggle.addEventListener("click", function (event) {
          if (!isMobileNav()) return;

          event.preventDefault();
          event.stopPropagation();
          toggleSubmenu(item);
        });
      }

      if (link) {
        link.addEventListener("click", function (event) {
          if (!isMobileNav()) return;
          if (!item.classList.contains("has-date-filter")) return;

          if (!item.classList.contains("is-submenu-open")) {
            event.preventDefault();
            event.stopPropagation();
            openSubmenu(item);
          }
        });
      }
    });

    nav.querySelectorAll("[data-ak-news-date-filter]").forEach(function (form) {
      ["click", "touchstart", "mousedown"].forEach(function (eventName) {
        form.addEventListener(
          eventName,
          function (event) {
            event.stopPropagation();
          },
          { passive: true }
        );
      });
    });

    if (typeof mq.addEventListener === "function") {
      mq.addEventListener("change", function () {
        closeAll(null);
      });
    } else if (typeof mq.addListener === "function") {
      mq.addListener(function () {
        closeAll(null);
      });
    }
  }

  function initTicker() {
    var speed = 72;

    document.querySelectorAll(".ticker__track").forEach(function (track) {
      if (track.dataset.tickerReady === "true") return;

      var list = track.querySelector(".ticker__list");
      if (!list) return;

      var marquee = track.querySelector(".ticker__marquee");
      if (!marquee) {
        marquee = document.createElement("div");
        marquee.className = "ticker__marquee";
        track.insertBefore(marquee, list);
        marquee.appendChild(list);
      }

      if (!marquee.querySelector(".ticker__list[aria-hidden='true']")) {
        var clone = list.cloneNode(true);
        clone.setAttribute("aria-hidden", "true");
        marquee.appendChild(clone);
      }

      var syncDuration = function () {
        var width = list.scrollWidth;
        if (width <= 0) return;
        marquee.style.setProperty(
          "--ticker-duration",
          Math.max(width / speed, 12) + "s"
        );
      };

      syncDuration();
      track.dataset.tickerReady = "true";

      if (typeof ResizeObserver !== "undefined") {
        var observer = new ResizeObserver(syncDuration);
        observer.observe(list);
      } else {
        window.addEventListener("resize", syncDuration, { passive: true });
      }
    });
  }

  function initHeaderScroll() {
    var header = document.querySelector(".site-header");
    if (!header) return;
    var onScroll = function () {
      header.style.boxShadow =
        window.scrollY > 10
          ? "0 4px 24px rgba(0,0,0,0.1)"
          : "0 2px 8px rgba(0,0,0,0.06)";
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  function initReveal() {
    if (!("IntersectionObserver" in window)) return;
    var targets = document.querySelectorAll(
      ".card, .magazine-item, .video-card, .review-card, .hero-text-item, .news-list__item"
    );
    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.style.opacity = "1";
            entry.target.style.transform = "translateY(0)";
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.08, rootMargin: "0px 0px -40px 0px" }
    );
    targets.forEach(function (el, i) {
      el.style.opacity = "0";
      el.style.transform = "translateY(20px)";
      el.style.transition =
        "opacity 0.5s ease " + i * 0.02 + "s, transform 0.5s ease " + i * 0.02 + "s";
      observer.observe(el);
    });
  }

  function initNewsletter() {
    document.querySelectorAll("[data-ak-newsletter]").forEach(function (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var input = form.querySelector('input[type="email"]');
        if (!input || !input.value.trim()) return;
        alert("عضویت شما ثبت شد.");
        input.value = "";
      });
    });
  }

  function initAjaxSearch() {
    if (typeof akTheme === "undefined") {
      return;
    }

    document.querySelectorAll("[data-ak-search-wrap]").forEach(function (wrap) {
      var form = wrap.querySelector("[data-ak-search]");
      var input = form ? form.querySelector('input[type="search"]') : null;
      var dropdown = wrap.querySelector("[data-ak-search-dropdown]");
      var trigger = form ? form.querySelector("[data-ak-search-trigger]") : null;

      if (!form || !input || !dropdown) {
        return;
      }

      var timer = null;
      var activeRequest = null;

      function setExpanded(open) {
        input.setAttribute("aria-expanded", open ? "true" : "false");
      }

      function hideDropdown() {
        dropdown.hidden = true;
        dropdown.classList.remove("ak-search-dropdown--loading");
        dropdown.innerHTML = "";
        setExpanded(false);
      }

      function showLoading() {
        dropdown.hidden = false;
        dropdown.classList.add("ak-search-dropdown--loading");
        dropdown.innerHTML = "در حال جستجو…";
        setExpanded(true);
      }

      function buildSearchPageUrl(q) {
        var base = akTheme.homeUrl || "/";
        try {
          var url = new URL(base, window.location.origin);
          url.searchParams.set("s", q);
          return url.pathname + url.search + url.hash;
        } catch (error) {
          var separator = base.indexOf("?") >= 0 ? "&" : "?";
          return base + separator + "s=" + encodeURIComponent(q);
        }
      }

      function renderItems(items, hasMore, query) {
        dropdown.classList.remove("ak-search-dropdown--loading");

        if (!items.length) {
          dropdown.hidden = false;
          dropdown.innerHTML = '<p class="ak-search-dropdown--loading">نتیجه‌ای یافت نشد.</p>';
          setExpanded(true);
          return;
        }

        var itemsHtml = items
          .map(function (item) {
            return (
              '<a class="ak-search-item" role="option" href="' +
              item.url +
              '">' +
              (item.image
                ? '<img src="' + item.image + '" alt="" width="40" height="30" loading="lazy" />'
                : "") +
              "<span>" +
              item.title +
              "</span></a>"
            );
          })
          .join("");

        var showAll = items.length >= 8 && query;
        if (showAll) {
          dropdown.innerHTML =
            '<div class="ak-search-dropdown__results">' +
            itemsHtml +
            "</div>" +
            '<a class="ak-search-more" href="' +
            buildSearchPageUrl(query) +
            '">نمایش همه نتایج</a>';
        } else {
          dropdown.innerHTML = itemsHtml;
        }

        dropdown.hidden = false;
        setExpanded(true);
      }

      function fetchResults(q) {
        if (activeRequest) {
          activeRequest.abort();
        }

        showLoading();

        var controller = new AbortController();
        activeRequest = controller;

        var url =
          akTheme.ajaxUrl +
          "?action=ak_search&nonce=" +
          encodeURIComponent(akTheme.searchNonce) +
          "&q=" +
          encodeURIComponent(q);

        fetch(url, { credentials: "same-origin", signal: controller.signal })
          .then(function (response) {
            return response.json();
          })
          .then(function (data) {
            if (controller.signal.aborted) {
              return;
            }

            if (data && data.success && data.data) {
              renderItems(
                data.data.items || [],
                !!data.data.has_more,
                input.value.trim()
              );
              return;
            }

            hideDropdown();
          })
          .catch(function (error) {
            if (error && error.name === "AbortError") {
              return;
            }

            hideDropdown();
          })
          .finally(function () {
            if (activeRequest === controller) {
              activeRequest = null;
            }
          });
      }

      function queueSearch() {
        var q = input.value.trim();
        clearTimeout(timer);

        if (q.length < 2) {
          if (activeRequest) {
            activeRequest.abort();
            activeRequest = null;
          }
          hideDropdown();
          return;
        }

        timer = setTimeout(function () {
          fetchResults(q);
        }, 400);
      }

      form.addEventListener("submit", function (event) {
        event.preventDefault();
        queueSearch();
      });

      input.addEventListener("input", queueSearch);

      input.addEventListener("keydown", function (event) {
        if (event.key === "Enter") {
          event.preventDefault();
          clearTimeout(timer);
          var q = input.value.trim();
          if (q.length < 2) {
            hideDropdown();
            return;
          }
          fetchResults(q);
        } else if (event.key === "Escape") {
          hideDropdown();
          input.blur();
        }
      });

      if (trigger) {
        trigger.addEventListener("click", function () {
          clearTimeout(timer);
          var q = input.value.trim();
          if (q.length < 2) {
            input.focus();
            return;
          }
          fetchResults(q);
        });
      }

      document.addEventListener("click", function (event) {
        if (!wrap.contains(event.target)) {
          hideDropdown();
        }
      });
    });
  }

  function initMagazinesCarousel() {
    document.querySelectorAll("[data-ak-magazines]").forEach(function (root) {
      var track = root.querySelector(".magazines-carousel__track");
      var slides = root.querySelectorAll(".magazines-carousel__slide");
      var controls = root.querySelector(".magazines-carousel__controls");
      var dotsWrap = root.querySelector("[data-magazines-dots]");
      var prev = root.querySelector("[data-magazines-prev]");
      var next = root.querySelector("[data-magazines-next]");

      if (!track || slides.length === 0) return;

      var index = 0;
      var timer = null;

      function visibleCount() {
        if (window.matchMedia("(max-width: 767px)").matches) return 2;
        if (window.matchMedia("(max-width: 1439px)").matches) return 3;
        return 5;
      }

      function maxIndex() {
        return Math.max(0, slides.length - visibleCount());
      }

      function stepPx() {
        var slide = slides[0];
        if (!slide) return 0;
        var gap = parseFloat(window.getComputedStyle(track).columnGap || window.getComputedStyle(track).gap) || 0;
        return slide.offsetWidth + gap;
      }

      function isCarousel() {
        return slides.length > visibleCount();
      }

      function syncMode() {
        var carousel = isCarousel();
        root.classList.toggle("magazines-carousel--static", !carousel);
        if (controls) {
          controls.hidden = !carousel;
        }
        if (!carousel) {
          track.style.transform = "";
          if (timer) {
            window.clearInterval(timer);
            timer = null;
          }
        }
        return carousel;
      }

      function renderDots() {
        if (!dotsWrap) return;
        dotsWrap.innerHTML = "";
        if (!isCarousel()) return;

        var total = maxIndex() + 1;
        for (var dotIndex = 0; dotIndex < total; dotIndex += 1) {
          (function (targetIndex) {
            var dot = document.createElement("button");
            dot.type = "button";
            dot.className =
              "magazines-carousel__dot" + (targetIndex === index ? " is-active" : "");
            dot.setAttribute("aria-label", "اسلاید " + (targetIndex + 1));
            dot.addEventListener("click", function () {
              goTo(targetIndex);
              restartAuto();
            });
            dotsWrap.appendChild(dot);
          })(dotIndex);
        }
      }

      function goTo(i) {
        if (!syncMode()) return;
        index = Math.max(0, Math.min(i, maxIndex()));
        var offset = index * stepPx();
        track.style.transform = "translateX(" + (-offset) + "px)";
        if (dotsWrap) {
          dotsWrap.querySelectorAll(".magazines-carousel__dot").forEach(function (dot, dotIndex) {
            dot.classList.toggle("is-active", dotIndex === index);
          });
        }
      }

      function initialIndex() {
        return isCarousel() ? maxIndex() : 0;
      }

      function restartAuto() {
        if (timer) {
          window.clearInterval(timer);
          timer = null;
        }
        if (!isCarousel() || maxIndex() === 0) return;
        timer = window.setInterval(function () {
          var max = maxIndex();
          goTo(index <= 0 ? max : index - 1);
        }, 5000);
      }

      syncMode();
      index = initialIndex();
      renderDots();
      goTo(index);

      if (prev) {
        prev.addEventListener("click", function () {
          goTo(index - 1);
          restartAuto();
        });
      }
      if (next) {
        next.addEventListener("click", function () {
          goTo(index + 1);
          restartAuto();
        });
      }

      window.addEventListener("resize", function () {
        syncMode();
        if (!isCarousel()) {
          goTo(0);
        } else {
          index = Math.min(index, maxIndex());
          renderDots();
          goTo(index);
        }
        restartAuto();
      });

      restartAuto();
    });
  }

  function initKioskCarousel() {
    document.querySelectorAll("[data-ak-kiosk]").forEach(function (root) {
      var track = root.querySelector(".kiosk-carousel__track");
      var slides = root.querySelectorAll(".kiosk-carousel__slide");
      var controls = root.querySelector(".kiosk-carousel__controls");
      var dotsWrap = root.querySelector("[data-kiosk-dots]");
      var prev = root.querySelector("[data-kiosk-prev]");
      var next = root.querySelector("[data-kiosk-next]");

      if (!track || slides.length === 0) return;

      var rtlOrder = root.closest(".rail-widget--videos") !== null;
      var index = rtlOrder ? slides.length - 1 : 0;
      var rtl = document.documentElement.getAttribute("dir") === "rtl";
      var timer = null;

      function stepPx() {
        var slide = slides[0];
        return slide ? slide.offsetWidth : 0;
      }

      function goTo(i) {
        if (rtlOrder) {
          index = Math.max(0, Math.min(i, slides.length - 1));
          track.style.transform = "translateX(" + (-index * stepPx()) + "px)";
        } else {
          index = (i + slides.length) % slides.length;
          var offset = index * 100;
          track.style.transform = "translateX(" + (rtl ? offset : -offset) + "%)";
        }
        if (dotsWrap) {
          dotsWrap.querySelectorAll(".kiosk-carousel__dot").forEach(function (dot, dotIndex) {
            dot.classList.toggle("is-active", dotIndex === index);
          });
        }
      }

      function restartAuto() {
        if (timer) {
          window.clearInterval(timer);
          timer = null;
        }
        if (slides.length <= 1) return;
        timer = window.setInterval(function () {
          if (rtlOrder) {
            goTo(index <= 0 ? slides.length - 1 : index - 1);
          } else {
            goTo(index + 1);
          }
        }, 5000);
      }

      if (slides.length > 1 && controls && dotsWrap) {
        controls.hidden = false;
        slides.forEach(function (_, dotIndex) {
          var dot = document.createElement("button");
          dot.type = "button";
          dot.className =
            "kiosk-carousel__dot" + (dotIndex === index ? " is-active" : "");
          dot.setAttribute("aria-label", "اسلاید " + (dotIndex + 1));
          dot.addEventListener("click", function () {
            goTo(dotIndex);
            restartAuto();
          });
          dotsWrap.appendChild(dot);
        });

        goTo(index);

        if (prev) {
          prev.addEventListener("click", function () {
            goTo(index - 1);
            restartAuto();
          });
        }
        if (next) {
          next.addEventListener("click", function () {
            goTo(index + 1);
            restartAuto();
          });
        }

        restartAuto();
      }
    });
  }

  function initSinglePost() {
    var printBtn = document.querySelector("[data-ak-print]");
    if (printBtn) {
      printBtn.addEventListener("click", function () {
        window.print();
      });
    }

    document.querySelectorAll("[data-ak-share-menu]").forEach(function (root) {
      var toggle = root.querySelector("[data-ak-share-toggle]");
      var panel = root.querySelector("[data-ak-share-panel]");
      var copyBtn = root.querySelector("[data-ak-share-copy]");
      var status = root.querySelector("[data-ak-share-status]");
      if (!toggle || !panel) return;

      function closeMenu() {
        root.classList.remove("is-open");
        panel.hidden = true;
        toggle.setAttribute("aria-expanded", "false");
      }

      function openMenu() {
        root.classList.add("is-open");
        panel.hidden = false;
        toggle.setAttribute("aria-expanded", "true");
      }

      toggle.addEventListener("click", function (event) {
        event.stopPropagation();
        if (panel.hidden) {
          openMenu();
        } else {
          closeMenu();
        }
      });

      document.addEventListener("click", function (event) {
        if (!root.contains(event.target)) {
          closeMenu();
        }
      });

      document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
          closeMenu();
        }
      });

      if (copyBtn) {
        copyBtn.addEventListener("click", function () {
          var url = copyBtn.getAttribute("data-share-url") || window.location.href;
          var copiedLabel = "کپی شد!";
          var defaultLabel = "کپی لینک";

          function markCopied() {
            copyBtn.textContent = copiedLabel;
            copyBtn.classList.add("is-copied");
            if (status) {
              status.textContent = copiedLabel;
            }
            window.setTimeout(function () {
              copyBtn.textContent = defaultLabel;
              copyBtn.classList.remove("is-copied");
              if (status) {
                status.textContent = "";
              }
            }, 1800);
          }

          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(markCopied).catch(function () {
              window.prompt("لینک مطلب:", url);
            });
          } else {
            window.prompt("لینک مطلب:", url);
          }
        });
      }
    });

    var moreBtn = document.querySelector("[data-ak-related-more]");
    if (moreBtn) {
      moreBtn.addEventListener("click", function () {
        document.querySelectorAll(".single-related__item--more").forEach(function (item) {
          item.hidden = false;
        });
        moreBtn.hidden = true;
      });
    }
  }

  function initPictureFrameHeaderDock() {
    var section = document.querySelector(".picture-frame");
    if (!section) return;

    var topHeader = document.querySelector(".top-header");
    var siteHeader = document.querySelector(".site-header");
    var headers = [topHeader, siteHeader].filter(Boolean);
    if (headers.length === 0) return;

    var stack = document.querySelector(".picture-frame-header-stack");
    var inner = document.querySelector(".picture-frame-header-stack__inner");
    if (!stack) {
      stack = document.createElement("div");
      stack.className = "picture-frame-header-stack";
      inner = document.createElement("div");
      inner.className = "picture-frame-header-stack__inner";
      headers[0].parentNode.insertBefore(stack, headers[0]);
      stack.appendChild(inner);
      headers.forEach(function (header) {
        inner.appendChild(header);
      });
      document.body.classList.add("has-picture-frame-header-stack");
    }

    if (!inner) {
      inner = stack.querySelector(".picture-frame-header-stack__inner");
      if (!inner) {
        inner = document.createElement("div");
        inner.className = "picture-frame-header-stack__inner";
        while (stack.firstChild) {
          inner.appendChild(stack.firstChild);
        }
        stack.appendChild(inner);
      }
    }

    var headerHeight = inner.offsetHeight;
    var dockTick = false;

    function measureHeaderHeight() {
      inner.style.transform = "";
      headerHeight = inner.offsetHeight;
    }

    function updateHeaderDock() {
      var rect = section.getBoundingClientRect();
      var vh = window.innerHeight;

      if (rect.bottom <= 0 || rect.top >= vh) {
        inner.style.transform = "";
        return;
      }

      if (rect.top > headerHeight) {
        inner.style.transform = "";
        return;
      }

      var offset = Math.min(0, rect.top - headerHeight);
      inner.style.transform = "translate3d(0, " + offset + "px, 0)";
    }

    function scheduleHeaderDock() {
      if (dockTick) return;
      dockTick = true;
      window.requestAnimationFrame(function () {
        dockTick = false;
        updateHeaderDock();
      });
    }

    measureHeaderHeight();
    updateHeaderDock();
    window.addEventListener("scroll", scheduleHeaderDock, { passive: true });
    window.addEventListener(
      "resize",
      function () {
        measureHeaderHeight();
        scheduleHeaderDock();
      },
      { passive: true }
    );
  }

  function initPictureFrameCarousel() {
    var section = document.querySelector(".picture-frame");
    var root = document.querySelector("[data-ak-picture-frame]");
    if (!section || !root) return;

    var backdrops = root.querySelectorAll("[data-pf-backdrop]");
    var slides = root.querySelectorAll("[data-pf-slide]");
    var thumbs = root.querySelectorAll("[data-pf-thumb]");
    var prev = root.querySelector("[data-pf-prev]");
    var next = root.querySelector("[data-pf-next]");
    var bar = root.querySelector("[data-pf-bar]");
    var currentEl = root.querySelector("[data-pf-current]");
    var filmstrip = root.querySelector("[data-pf-thumbs]");

    if (backdrops.length === 0 || slides.length === 0) return;

    var index = Math.max(0, backdrops.length - 1);
    var intervalMs = 7000;
    var timer = null;
    var progressTimer = null;
    var progressStart = 0;
    var isInView = false;
    var wasInView = false;
    var isPaused = false;

    function pad(n) {
      return toPersianDigits(String(n).padStart(2, "0"));
    }

    function clearTimers() {
      if (timer) {
        window.clearInterval(timer);
        timer = null;
      }
      if (progressTimer) {
        window.cancelAnimationFrame(progressTimer);
        progressTimer = null;
      }
    }

    function setProgress(percent) {
      if (bar) {
        bar.style.width = percent + "%";
      }
    }

    function startProgress() {
      if (!bar || backdrops.length <= 1 || !isInView) return;
      progressStart = performance.now();
      setProgress(0);

      function tick(now) {
        var elapsed = now - progressStart;
        var percent = Math.min(100, (elapsed / intervalMs) * 100);
        setProgress(percent);
        if (percent < 100 && isInView) {
          progressTimer = window.requestAnimationFrame(tick);
        }
      }

      progressTimer = window.requestAnimationFrame(tick);
    }

    function restartKenBurns(node) {
      if (!node) return;
      var img = node.querySelector("img");
      if (!img) return;
      img.style.animation = "none";
      void img.offsetWidth;
      img.style.animation = "";
    }

    function scrollFilmstripToEnd() {
      if (!filmstrip) return;
      filmstrip.scrollLeft = filmstrip.scrollWidth;
    }

    function scrollThumbIntoView(thumb) {
      if (!thumb || !filmstrip || !isInView) return;
      var target = thumb.offsetLeft - (filmstrip.clientWidth - thumb.clientWidth) / 2;
      filmstrip.scrollTo({ left: target, behavior: "smooth" });
    }

    function goTo(i, options) {
      var opts = options || {};
      var advance = !!opts.advance;
      var scrollThumb = opts.scrollThumb !== false;

      index = (i + backdrops.length) % backdrops.length;

      backdrops.forEach(function (backdrop, backdropIndex) {
        var active = backdropIndex === index;
        backdrop.classList.toggle("is-active", active);
        if (active) {
          restartKenBurns(backdrop);
        }
      });

      slides.forEach(function (slide, slideIndex) {
        slide.classList.toggle("is-active", slideIndex === index);
      });

      thumbs.forEach(function (thumb, thumbIndex) {
        var active = thumbIndex === index;
        thumb.classList.toggle("is-active", active);
        thumb.setAttribute("aria-selected", active ? "true" : "false");
        if (active && scrollThumb) {
          scrollThumbIntoView(thumb);
        }
      });

      if (currentEl) {
        var activeThumb = thumbs[index];
        var rankEl = activeThumb ? activeThumb.querySelector(".picture-frame__thumb-label") : null;
        currentEl.textContent = rankEl
          ? rankEl.textContent
          : pad(index + 1);
      }

      clearTimers();

      if (!isInView) {
        setProgress(0);
        return;
      }

      startProgress();

      if (backdrops.length > 1 && advance) {
        timer = window.setInterval(function () {
          if (isInView && !isPaused) {
            goTo(index <= 0 ? backdrops.length - 1 : index - 1, { advance: true });
          }
        }, intervalMs);
      }
    }

    function startAutoplay() {
      if (backdrops.length <= 1 || !isInView || isPaused) return;
      goTo(index, { advance: true });
    }

    function stopAutoplay() {
      clearTimers();
      setProgress(0);
    }

    thumbs.forEach(function (thumb) {
      thumb.addEventListener("click", function () {
        var target = parseInt(thumb.getAttribute("data-pf-thumb"), 10);
        if (!isNaN(target)) {
          goTo(target, { advance: true });
        }
      });
    });

    if (prev) {
      prev.addEventListener("click", function () {
        goTo(index - 1, { advance: true });
      });
    }

    if (next) {
      next.addEventListener("click", function () {
        goTo(index + 1, { advance: true });
      });
    }

    root.addEventListener("keydown", function (e) {
      if (backdrops.length <= 1 || !isInView) return;
      if (e.key === "ArrowLeft") {
        goTo(index - 1, { advance: true });
      } else if (e.key === "ArrowRight") {
        goTo(index + 1, { advance: true });
      }
    });

    root.addEventListener("mouseenter", function () {
      isPaused = true;
      clearTimers();
    });

    root.addEventListener("mouseleave", function () {
      isPaused = false;
      startAutoplay();
    });

    root.addEventListener("focusin", function () {
      isPaused = true;
      clearTimers();
    });

    root.addEventListener("focusout", function (e) {
      if (!root.contains(e.relatedTarget)) {
        isPaused = false;
        startAutoplay();
      }
    });

    root.setAttribute("tabindex", "0");

    function shouldActivatePictureFrame(entry) {
      if (!entry.isIntersecting) return false;
      var rect = section.getBoundingClientRect();
      var vh = window.innerHeight;
      if (rect.top > vh * 0.35) return false;
      if (rect.bottom < vh * 0.55) return false;
      return (
        entry.intersectionRatio >= 0.72 ||
        (rect.top <= 32 && rect.bottom >= vh * 0.92)
      );
    }

    function shouldDeactivatePictureFrame(entry) {
      if (!entry.isIntersecting) return true;
      var rect = section.getBoundingClientRect();
      var vh = window.innerHeight;
      if (rect.top > 72) return true;
      if (rect.bottom < vh * 0.45) return true;
      return entry.intersectionRatio < 0.4;
    }

    function setPictureFrameView(active) {
      if (active === wasInView) return;
      wasInView = active;
      isInView = active;
      document.body.classList.toggle("is-picture-frame-view", active);

      if (active) {
        isPaused = false;
        startAutoplay();
        return;
      }

      isPaused = true;
      stopAutoplay();
    }

    if ("IntersectionObserver" in window) {
      var observer = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry) {
            if (shouldActivatePictureFrame(entry)) {
              setPictureFrameView(true);
              return;
            }
            if (shouldDeactivatePictureFrame(entry)) {
              setPictureFrameView(false);
            }
          });
        },
        { threshold: [0, 0.25, 0.5, 0.72, 0.85, 1] }
      );

      observer.observe(section);
    }

    goTo(index, { advance: false, scrollThumb: false });
    scrollFilmstripToEnd();
  }

  function initNewsGalleryLightbox() {
    var lightbox = document.querySelector("[data-news-gallery]");
    if (!lightbox) return;

    var imageEl = lightbox.querySelector(".news-gallery__image");
    var closeBtn = lightbox.querySelector("[data-news-gallery-close]");
    var prevBtn = lightbox.querySelector("[data-news-gallery-prev]");
    var nextBtn = lightbox.querySelector("[data-news-gallery-next]");
    var currentEl = lightbox.querySelector("[data-news-gallery-current]");
    var totalEl = lightbox.querySelector("[data-news-gallery-total]");

    if (!imageEl) return;

    var images = [];
    var index = 0;

    function parseImages(raw) {
      if (!raw) return [];
      try {
        var parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed.filter(Boolean) : [];
      } catch (e) {
        return [];
      }
    }

    function updateNav() {
      var hasMany = images.length > 1;
      if (prevBtn) prevBtn.hidden = !hasMany;
      if (nextBtn) nextBtn.hidden = !hasMany;
      if (totalEl) totalEl.textContent = toPersianDigits(String(images.length));
      if (currentEl) currentEl.textContent = toPersianDigits(String(index + 1));
    }

    function galleryImageUrl(item) {
      if (typeof item === "string") return item;
      return item && item.url ? String(item.url) : "";
    }

    function galleryImageAlt(item) {
      if (!item || typeof item === "string") return "";
      return item.alt ? String(item.alt) : "";
    }

    function showImage(i) {
      if (!images.length) return;
      index = (i + images.length) % images.length;
      imageEl.src = galleryImageUrl(images[index]);
      imageEl.alt = galleryImageAlt(images[index]);
      updateNav();
    }

    function openGallery(groupImages, startIndex) {
      images = groupImages;
      if (!images.length) return;

      index = Math.max(0, Math.min(startIndex || 0, images.length - 1));
      showImage(index);
      lightbox.hidden = false;
      document.body.style.overflow = "hidden";
    }

    function closeGallery() {
      lightbox.hidden = true;
      imageEl.removeAttribute("src");
      images = [];
      index = 0;
      document.body.style.overflow = "";
    }

    document.addEventListener("click", function (event) {
      var trigger = event.target.closest("[data-news-gallery-open]");
      if (!trigger) return;

      event.preventDefault();
      event.stopPropagation();

      var group = trigger.closest("[data-news-gallery-group]");
      if (!group) return;

      var groupImages = parseImages(group.getAttribute("data-gallery-images"));
      var startIndex = parseInt(trigger.getAttribute("data-gallery-index") || "0", 10);

      openGallery(groupImages, isNaN(startIndex) ? 0 : startIndex);
    });

    if (closeBtn) {
      closeBtn.addEventListener("click", closeGallery);
    }

    lightbox.addEventListener("click", function (event) {
      if (event.target === lightbox) {
        closeGallery();
      }
    });

    if (prevBtn) {
      prevBtn.addEventListener("click", function (event) {
        event.stopPropagation();
        showImage(index - 1);
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener("click", function (event) {
        event.stopPropagation();
        showImage(index + 1);
      });
    }

    document.addEventListener("keydown", function (event) {
      if (lightbox.hidden) return;

      if (event.key === "Escape") {
        closeGallery();
      } else if (event.key === "ArrowLeft" && images.length > 1) {
        showImage(index + 1);
      } else if (event.key === "ArrowRight" && images.length > 1) {
        showImage(index - 1);
      }
    });
  }

  function initHeroSlider() {
    var reducedMotion =
      typeof window.matchMedia === "function" &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    document.querySelectorAll("[data-hero-slider]").forEach(function (section) {
      var slidesEl = section.querySelector("[data-hero-slides]");
      var main = section.querySelector("[data-hero-main]");
      if (!slidesEl || !main) {
        return;
      }

      var slides;
      try {
        slides = JSON.parse(slidesEl.textContent || "[]");
      } catch (error) {
        return;
      }

      if (!Array.isArray(slides) || slides.length === 0) {
        return;
      }

      var image = main.querySelector("[data-hero-image]");
      var titleLink = main.querySelector("[data-hero-title-link]");
      var title = main.querySelector("[data-hero-title]");
      var excerpt = main.querySelector("[data-hero-excerpt]");
      var cta = main.querySelector("[data-hero-cta]");
      var imageLink = main.querySelector("[data-hero-image-link]");
      var triggers = section.querySelectorAll("[data-hero-trigger]");
      var progressFill = section.querySelector("[data-hero-progress-fill]");
      var currentIndex = 0;
      var progressPaused = false;
      var intervalMs = parseInt(section.getAttribute("data-hero-interval") || "15000", 10);
      if (!Number.isFinite(intervalMs) || intervalMs < 2000) {
        intervalMs = 15000;
      }

      section.style.setProperty("--hero-progress-duration", intervalMs + "ms");

      function applySlide(slide) {
        if (!slide) {
          return;
        }

        if (image && slide.image) {
          image.src = slide.image;
          image.alt = slide.title || "";
        }
        if (titleLink && slide.link) {
          titleLink.href = slide.link;
        }
        if (title) {
          title.textContent = slide.title || "";
        }
        if (excerpt) {
          excerpt.textContent = slide.excerpt || "";
        }
        if (cta && slide.link) {
          cta.href = slide.link;
        }
        if (imageLink && slide.link) {
          imageLink.href = slide.link;
        }
      }

      function syncProgressBar() {
        if (!progressFill) {
          return;
        }

        progressFill.classList.remove("is-active");
        progressFill.style.animation = "none";
        progressFill.style.animationPlayState = "";
        progressFill.style.width = "0%";

        if (reducedMotion || slides.length <= 1 || progressPaused) {
          return;
        }

        void progressFill.offsetWidth;
        progressFill.classList.add("is-active");
        progressFill.style.animation = "";
        progressFill.style.animationPlayState = progressPaused ? "paused" : "running";

        progressFill.addEventListener(
          "animationend",
          function onProgressEnd() {
            if (progressPaused || document.hidden || reducedMotion) {
              return;
            }

            showSlide(currentIndex + 1);
          },
          { once: true }
        );
      }

      function showSlide(index) {
        if (slides.length <= 1) {
          return;
        }

        currentIndex = ((index % slides.length) + slides.length) % slides.length;
        applySlide(slides[currentIndex]);

        triggers.forEach(function (trigger, triggerIndex) {
          var item = trigger.closest("[data-hero-item]");
          var active = currentIndex === triggerIndex;
          trigger.setAttribute("aria-pressed", active ? "true" : "false");
          if (item) {
            item.classList.toggle("is-active", active);
          }
        });

        syncProgressBar();
      }

      function pauseAuto() {
        progressPaused = true;
        if (progressFill && progressFill.classList.contains("is-active")) {
          progressFill.style.animationPlayState = "paused";
        }
      }

      function resumeAuto() {
        if (reducedMotion || slides.length <= 1) {
          return;
        }

        progressPaused = false;
        if (progressFill && progressFill.classList.contains("is-active")) {
          progressFill.style.animationPlayState = "running";
          return;
        }

        syncProgressBar();
      }

      triggers.forEach(function (trigger, index) {
        trigger.addEventListener("click", function (event) {
          event.preventDefault();
          progressPaused = false;
          showSlide(index);
        });
      });

      section.addEventListener("mouseenter", pauseAuto);
      section.addEventListener("mouseleave", resumeAuto);
      section.addEventListener("focusin", pauseAuto);
      section.addEventListener("focusout", function (event) {
        if (!section.contains(event.relatedTarget)) {
          resumeAuto();
        }
      });

      document.addEventListener("visibilitychange", function () {
        if (document.hidden) {
          pauseAuto();
        } else {
          resumeAuto();
        }
      });

      syncProgressBar();
    });
  }

  function initAdExternalLinks() {
    var selector = [
      ".ad-strip__slot",
      ".ad-banner__slot",
      ".ad-sidebar__slot",
      ".rf-ad-slot:not(.rf-ad-slot--placeholder)",
      ".cb-ad-row__slot",
    ].join(", ");

    document.querySelectorAll(selector).forEach(function (link) {
      if (link.tagName !== "A") {
        return;
      }

      var href = (link.getAttribute("href") || "").trim();
      if (href === "" || href === "#") {
        return;
      }

      link.setAttribute("target", "_blank");
      link.setAttribute("rel", "noopener noreferrer");
    });
  }

  function initArchiveHeroScroll() {
    document.querySelectorAll("[data-archive-hero-scroll]").forEach(function (button) {
      button.addEventListener("click", function (event) {
        event.preventDefault();

        var href = button.getAttribute("href") || "";
        var targetId =
          href.charAt(0) === "#" ? href.slice(1) : "archive-content";
        var target = document.getElementById(targetId);
        if (!target && targetId !== "archive-content") {
          target = document.getElementById("archive-content");
        }
        if (!target) {
          return;
        }

        var header = document.querySelector(".site-header");
        var nav = document.getElementById("main-nav");
        var offset = 16;

        if (header) {
          offset += header.offsetHeight;
        }
        if (nav && getComputedStyle(nav).display !== "none") {
          offset += nav.offsetHeight;
        }

        var top =
          target.getBoundingClientRect().top + window.pageYOffset - offset;

        window.scrollTo({
          top: Math.max(0, top),
          behavior: "smooth",
        });
      });
    });
  }

  function initVideoCinema() {
    document.querySelectorAll("[data-video-cinema-native]").forEach(function (stage) {
      var playBtn = stage.querySelector("[data-video-cinema-play]");
      var video = stage.querySelector("video");
      if (!playBtn || !video) {
        return;
      }

      playBtn.addEventListener("click", function () {
        stage.classList.add("is-playing");
        var playPromise = video.play();
        if (playPromise && typeof playPromise.catch === "function") {
          playPromise.catch(function () {
            stage.classList.remove("is-playing");
          });
        }
      });

      video.addEventListener("play", function () {
        stage.classList.add("is-playing");
      });
    });
  }

  function initArchiveHeroAspect() {
    document.querySelectorAll("[data-archive-hero]").forEach(function (hero) {
      var imageUrl = hero.getAttribute("data-hero-image");
      if (!imageUrl) {
        return;
      }

      var needsFallback = hero.getAttribute("data-hero-aspect-fallback") === "1";
      if (!needsFallback) {
        return;
      }

      var img = new Image();
      img.onload = function () {
        if (img.naturalWidth > 0 && img.naturalHeight > 0) {
          hero.style.setProperty(
            "--archive-hero-aspect",
            img.naturalWidth + " / " + img.naturalHeight
          );
        }
      };
      img.src = imageUrl;
    });
  }

  function initNewsArchiveDateFilter() {
    document.querySelectorAll("[data-ak-news-date-filter]").forEach(function (form) {
      var yearSelect = form.querySelector("[data-news-date-year]");
      var monthSelect = form.querySelector("[data-news-date-month]");
      var daySelect = form.querySelector("[data-news-date-day]");
      if (!yearSelect || !monthSelect || !daySelect) return;

      var monthLengths = {};
      try {
        monthLengths = JSON.parse(form.getAttribute("data-month-lengths") || "{}");
      } catch (error) {
        monthLengths = {};
      }

      function selectedDayValue() {
        return parseInt(daySelect.value, 10) || 0;
      }

      function dayAllLabel() {
        return form.getAttribute("data-ak-date-filter-compact") === "1" ? "روز" : "همه روزها";
      }

      function rebuildDayOptions() {
        var year = parseInt(yearSelect.value, 10) || 0;
        var month = parseInt(monthSelect.value, 10) || 0;
        var previousDay = selectedDayValue();

        daySelect.innerHTML = "";

        if (year <= 0 || month <= 0) {
          daySelect.disabled = true;
          var allDaysOption = document.createElement("option");
          allDaysOption.value = "0";
          allDaysOption.textContent = dayAllLabel();
          allDaysOption.selected = true;
          daySelect.appendChild(allDaysOption);
          return;
        }

        var maxDay = 31;
        var yearKey = String(year);
        if (monthLengths[yearKey] && monthLengths[yearKey][month]) {
          maxDay = monthLengths[yearKey][month];
        }

        daySelect.disabled = false;

        var defaultOption = document.createElement("option");
        defaultOption.value = "0";
        defaultOption.textContent = dayAllLabel();
        defaultOption.selected = previousDay <= 0;
        daySelect.appendChild(defaultOption);

        for (var day = 1; day <= maxDay; day += 1) {
          var option = document.createElement("option");
          option.value = String(day);
          option.textContent = String(day);
          if (day === previousDay) {
            option.selected = true;
            defaultOption.selected = false;
          }
          daySelect.appendChild(option);
        }
      }

      yearSelect.addEventListener("change", rebuildDayOptions);
      monthSelect.addEventListener("change", rebuildDayOptions);
      rebuildDayOptions();
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initMobileNav();
    initMainNavSubmenus();
    initTicker();
    initHeaderScroll();
    initReveal();
    initNewsletter();
    initAjaxSearch();
    initSinglePost();
    initMagazinesCarousel();
    initKioskCarousel();
    initPictureFrameHeaderDock();
    initPictureFrameCarousel();
    initNewsGalleryLightbox();
    initHeroSlider();
    initArchiveHeroAspect();
    initArchiveHeroScroll();
    initVideoCinema();
    initNewsArchiveDateFilter();
    initAdExternalLinks();
  });
})();
