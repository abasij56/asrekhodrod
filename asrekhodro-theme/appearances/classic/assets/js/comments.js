/**
 * Infinite scroll for themed comment lists (4 comments per batch).
 */
(function () {
  "use strict";

  var config = window.akComments || {};
  var lists = document.querySelectorAll("[data-ak-comments-list]");

  initSubmissionNotice();

  if (lists.length && config.ajaxUrl) {
    lists.forEach(function (list) {
      initCommentList(list);
    });
  }

  function initSubmissionNotice() {
    var notice = document.querySelector("[data-ak-comments-notice]");
    if (!notice) {
      return;
    }

    var referrer = document.referrer || "";
    var commentsPostPath = config.commentsPostPath || "/wp-comments-post.php";
    var cameFromSubmit = referrer.indexOf(commentsPostPath) !== -1;
    var hash = window.location.hash || "";
    var commentMatch = hash.match(/^#comment-(\d+)$/);
    var targetSelector =
      notice.getAttribute("data-ak-comments-notice-target") ||
      (commentMatch ? hash : "");

    if (!notice.hidden && notice.querySelector(".ak-comments__notice-text")) {
      scrollToTarget(targetSelector);
      return;
    }

    if (!cameFromSubmit || !commentMatch) {
      return;
    }

    var commentEl = document.querySelector(hash);
    if (!commentEl) {
      return;
    }

    var isModeration = Boolean(commentEl.querySelector(".ak-comment-card__moderation"));
    var type = isModeration ? "moderation" : "success";
    var message = isModeration
      ? config.moderationMessage || "دیدگاه شما ثبت شد و پس از بررسی توسط تحریریه منتشر می‌شود."
      : config.successMessage || "نظر شما با موفقیت ثبت شد.";

    showNotice(notice, type, message);
    scrollToTarget(hash);
  }

  function showNotice(notice, type, message) {
    notice.hidden = false;
    notice.setAttribute("data-ak-comments-notice-type", type);

    var text = notice.querySelector(".ak-comments__notice-text");
    if (text) {
      text.textContent = message;
      return;
    }

    notice.innerHTML = '<p class="ak-comments__notice-text"></p>';
    notice.querySelector(".ak-comments__notice-text").textContent = message;
  }

  function scrollToTarget(selector) {
    if (!selector) {
      return;
    }

    var target = document.querySelector(selector);
    if (!target) {
      return;
    }

    window.requestAnimationFrame(function () {
      target.scrollIntoView({ behavior: "smooth", block: "center" });
    });
  }

  function initCommentList(list) {
    var wrap = list.closest(".ak-comments__list-wrap");
    if (!wrap) {
      return;
    }

    var sentinel = wrap.querySelector("[data-ak-comments-sentinel]");
    var status = wrap.querySelector("[data-ak-comments-status]");
    var postId = parseInt(list.getAttribute("data-post-id") || "0", 10);
    var offset = parseInt(list.getAttribute("data-offset") || "0", 10);
    var total = parseInt(list.getAttribute("data-total") || "0", 10);
    var perPage = parseInt(list.getAttribute("data-per-page") || config.perPage || "7", 10);
    var loading = false;
    var hasMore = offset < total;

    if (!postId || !hasMore || !sentinel) {
      if (sentinel) {
        sentinel.hidden = true;
      }
      return;
    }

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            loadMore();
          }
        });
      },
      {
        root: null,
        rootMargin: "120px 0px",
        threshold: 0,
      }
    );

    observer.observe(sentinel);

    function setStatus(message) {
      if (!status) {
        return;
      }

      if (!message) {
        status.hidden = true;
        status.textContent = "";
        return;
      }

      status.hidden = false;
      status.textContent = message;
    }

    function finish(hasMoreLeft) {
      hasMore = hasMoreLeft;
      loading = false;

      if (!hasMore) {
        observer.disconnect();
        sentinel.hidden = true;
        setStatus("");
        return;
      }

      setStatus("");
    }

    function loadMore() {
      if (loading || !hasMore) {
        return;
      }

      loading = true;
      setStatus(config.loadingText || "در حال بارگذاری نظرات…");

      var url =
        config.ajaxUrl +
        "?action=" +
        encodeURIComponent(config.action || "ak_load_comments") +
        "&nonce=" +
        encodeURIComponent(config.nonce || "") +
        "&post_id=" +
        encodeURIComponent(String(postId)) +
        "&offset=" +
        encodeURIComponent(String(offset));

      fetch(url, {
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
        },
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (payload) {
          if (!payload || !payload.success || !payload.data) {
            throw new Error("load_failed");
          }

          var data = payload.data;
          if (data.html) {
            list.insertAdjacentHTML("beforeend", data.html);
          }

          offset = typeof data.offset === "number" ? data.offset : offset + perPage;
          list.setAttribute("data-offset", String(offset));

          finish(Boolean(data.hasMore));
        })
        .catch(function () {
          loading = false;
          setStatus(config.errorText || "بارگذاری نظرات ناموفق بود.");
        });
    }
  }
})();
