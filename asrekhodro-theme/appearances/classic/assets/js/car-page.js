(function () {
  'use strict';

  function initGallery() {
    var lightbox = document.querySelector('[data-car-lightbox]');
    if (!lightbox) {
      lightbox = document.createElement('div');
      lightbox.className = 'car-lightbox';
      lightbox.setAttribute('data-car-lightbox', '');
      lightbox.setAttribute('dir', 'rtl');
      lightbox.hidden = true;
      document.body.appendChild(lightbox);
    }

    if (!lightbox.querySelector('.car-lightbox__figure')) {
      lightbox.innerHTML =
        '<button type="button" class="car-lightbox__nav car-lightbox__nav--prev" data-car-lightbox-prev aria-label="تصویر قبلی"><svg class="car-lightbox__nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>' +
        '<button type="button" class="car-lightbox__nav car-lightbox__nav--next" data-car-lightbox-next aria-label="تصویر بعدی"><svg class="car-lightbox__nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>' +
        '<button type="button" class="car-lightbox__close" data-car-lightbox-close aria-label="بستن">&times;</button>' +
        '<figure class="car-lightbox__figure">' +
        '<button type="button" class="car-lightbox__hit car-lightbox__hit--prev" data-car-lightbox-prev aria-label="تصویر قبلی" tabindex="-1"></button>' +
        '<img class="car-lightbox__image" src="" alt="" />' +
        '<button type="button" class="car-lightbox__hit car-lightbox__hit--next" data-car-lightbox-next aria-label="تصویر بعدی" tabindex="-1"></button>' +
        '</figure>';
    } else if (!lightbox.querySelector('.car-lightbox__nav--prev')) {
      lightbox.insertAdjacentHTML(
        'afterbegin',
        '<button type="button" class="car-lightbox__nav car-lightbox__nav--prev" data-car-lightbox-prev aria-label="تصویر قبلی"><svg class="car-lightbox__nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>' +
          '<button type="button" class="car-lightbox__nav car-lightbox__nav--next" data-car-lightbox-next aria-label="تصویر بعدی"><svg class="car-lightbox__nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
      );
    }

    var figure = lightbox.querySelector('.car-lightbox__figure');
    var image = lightbox.querySelector('.car-lightbox__image');
    var closeBtn = lightbox.querySelector('[data-car-lightbox-close]');
    var prevControls = lightbox.querySelectorAll('[data-car-lightbox-prev]');
    var nextControls = lightbox.querySelectorAll('[data-car-lightbox-next]');
    var state = {
      triggers: [],
      index: 0,
    };

    function updateNavControls() {
      var hasMany = state.triggers.length > 1;
      prevControls.forEach(function (control) {
        control.hidden = !hasMany;
      });
      nextControls.forEach(function (control) {
        control.hidden = !hasMany;
      });
      lightbox.classList.toggle('car-lightbox--has-nav', hasMany);
    }

    function showAt(index) {
      if (!state.triggers.length || !image) {
        return;
      }

      if (index < 0) {
        index = state.triggers.length - 1;
      } else if (index >= state.triggers.length) {
        index = 0;
      }

      state.index = index;
      var trigger = state.triggers[state.index];
      image.src = trigger.getAttribute('data-full-src') || '';
      image.alt = trigger.getAttribute('data-full-alt') || '';
      updateNavControls();
    }

    function openFromTrigger(trigger) {
      var gallery = trigger.closest('[data-car-gallery]');
      state.triggers = gallery
        ? Array.prototype.slice.call(gallery.querySelectorAll('[data-car-gallery-open]'))
        : [trigger];

      var index = state.triggers.indexOf(trigger);
      state.index = index >= 0 ? index : 0;

      showAt(state.index);
      lightbox.hidden = false;
      document.body.style.overflow = 'hidden';
      if (closeBtn) {
        closeBtn.focus();
      }
    }

    function close() {
      lightbox.hidden = true;
      if (image) {
        image.src = '';
        image.alt = '';
      }
      state.triggers = [];
      state.index = 0;
      updateNavControls();
      document.body.style.overflow = '';
    }

    function step(delta) {
      if (lightbox.hidden || state.triggers.length < 2) {
        return;
      }
      showAt(state.index + delta);
    }

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-car-gallery-open]');
      if (trigger) {
        event.preventDefault();
        openFromTrigger(trigger);
        return;
      }

      if (event.target.closest('[data-car-lightbox-prev]')) {
        event.preventDefault();
        step(-1);
        return;
      }

      if (event.target.closest('[data-car-lightbox-next]')) {
        event.preventDefault();
        step(1);
        return;
      }

      if (event.target === lightbox || event.target.closest('[data-car-lightbox-close]')) {
        close();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (lightbox.hidden) {
        return;
      }

      if (event.key === 'Escape') {
        close();
        return;
      }

      if (event.key === 'ArrowLeft') {
        event.preventDefault();
        step(1);
        return;
      }

      if (event.key === 'ArrowRight') {
        event.preventDefault();
        step(-1);
      }
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', close);
    }

    if (figure && !figure.querySelector('.car-lightbox__hit--prev')) {
      figure.insertAdjacentHTML(
        'afterbegin',
        '<button type="button" class="car-lightbox__hit car-lightbox__hit--prev" data-car-lightbox-prev aria-label="تصویر قبلی" tabindex="-1"></button>'
      );
      figure.insertAdjacentHTML(
        'beforeend',
        '<button type="button" class="car-lightbox__hit car-lightbox__hit--next" data-car-lightbox-next aria-label="تصویر بعدی" tabindex="-1"></button>'
      );
      prevControls = lightbox.querySelectorAll('[data-car-lightbox-prev]');
      nextControls = lightbox.querySelectorAll('[data-car-lightbox-next]');
    }

    updateNavControls();
  }

  function initPriceCharts() {
    var charts = document.querySelectorAll('[data-car-price-chart]');
    charts.forEach(function (chart) {
      var canvas = chart.querySelector('.car-price-chart__canvas');
      var rows = chart.querySelectorAll('tbody tr[data-price]');
      if (!canvas || !rows.length) {
        return;
      }

      var points = [];
      rows.forEach(function (row) {
        var price = parseFloat(row.getAttribute('data-price'), 10);
        var date = row.getAttribute('data-date') || '';
        if (!isNaN(price)) {
          points.push({ price: price, date: date });
        }
      });

      if (points.length < 2) {
        return;
      }

      var ctx = canvas.getContext('2d');
      var dpr = window.devicePixelRatio || 1;
      var width = chart.clientWidth || 600;
      var height = 260;

      canvas.width = width * dpr;
      canvas.height = height * dpr;
      canvas.style.width = width + 'px';
      canvas.style.height = height + 'px';
      ctx.scale(dpr, dpr);

      var padding = { top: 20, right: 20, bottom: 40, left: 60 };
      var plotW = width - padding.left - padding.right;
      var plotH = height - padding.top - padding.bottom;

      var prices = points.map(function (p) {
        return p.price;
      });
      var minP = Math.min.apply(null, prices);
      var maxP = Math.max.apply(null, prices);
      var range = maxP - minP || 1;

      ctx.clearRect(0, 0, width, height);
      ctx.strokeStyle = '#e0e0e0';
      ctx.lineWidth = 1;

      for (var g = 0; g <= 4; g++) {
        var y = padding.top + (plotH * g) / 4;
        ctx.beginPath();
        ctx.moveTo(padding.left, y);
        ctx.lineTo(width - padding.right, y);
        ctx.stroke();
      }

      ctx.strokeStyle = '#e10600';
      ctx.lineWidth = 2;
      ctx.beginPath();

      points.forEach(function (point, index) {
        var x = padding.left + (plotW * index) / (points.length - 1);
        var y = padding.top + plotH - ((point.price - minP) / range) * plotH;
        if (index === 0) {
          ctx.moveTo(x, y);
        } else {
          ctx.lineTo(x, y);
        }
      });
      ctx.stroke();

      ctx.fillStyle = '#e10600';
      points.forEach(function (point, index) {
        var x = padding.left + (plotW * index) / (points.length - 1);
        var y = padding.top + plotH - ((point.price - minP) / range) * plotH;
        ctx.beginPath();
        ctx.arc(x, y, 4, 0, Math.PI * 2);
        ctx.fill();
      });

      ctx.fillStyle = '#333';
      ctx.font = '12px sans-serif';
      ctx.textAlign = 'center';
      points.forEach(function (point, index) {
        if (index % Math.ceil(points.length / 6) !== 0 && index !== points.length - 1) {
          return;
        }
        var x = padding.left + (plotW * index) / (points.length - 1);
        ctx.fillText(point.date, x, height - 12);
      });
    });
  }

  function collectTocItems(page) {
    var items = [];
    var seen = {};

    page.querySelectorAll('section[id] > h2.ci-section__title').forEach(function (heading) {
      var section = heading.closest('section');
      if (!section || !section.id) {
        return;
      }

      var label = (heading.textContent || '').replace(/\s+/g, ' ').trim();
      if (label === '' || seen[section.id]) {
        return;
      }

      seen[section.id] = true;
      items.push({
        anchor: section.id,
        label: label,
      });
    });

    return items;
  }

  function renderTocLists(items) {
    document.querySelectorAll('[data-ci-toc-list]').forEach(function (list) {
      list.innerHTML = '';

      items.forEach(function (item) {
        var li = document.createElement('li');
        var link = document.createElement('a');
        link.href = '#' + item.anchor;
        link.textContent = item.label;
        li.appendChild(link);
        list.appendChild(li);
      });
    });
  }

  function initToc() {
    var page = document.querySelector('.carsinfo-page[data-ci-toc-enabled]');
    if (!page) {
      return;
    }

    var items = collectTocItems(page);
    if (!items.length) {
      return;
    }

    renderTocLists(items);

    document.querySelectorAll('[data-ci-toc-sidebar], [data-ci-toc-mobile]').forEach(function (node) {
      node.hidden = false;
    });

    var mobileTocs = document.querySelectorAll('[data-ci-toc-mobile]');
    mobileTocs.forEach(function (toc) {
      var toggle = toc.querySelector('[data-ci-toc-toggle]');
      var panel = toc.querySelector('[data-ci-toc-panel]');
      if (!toggle || !panel) {
        return;
      }

      function setOpen(open) {
        toc.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        panel.hidden = !open;
      }

      toggle.addEventListener('click', function (event) {
        event.stopPropagation();
        setOpen(panel.hidden);
      });

      panel.querySelectorAll('a[href^="#"]').forEach(function (link) {
        link.addEventListener('click', function () {
          setOpen(false);
        });
      });

      document.addEventListener('click', function (event) {
        if (!toc.contains(event.target)) {
          setOpen(false);
        }
      });
    });
  }

  function init() {
    initGallery();
    initPriceCharts();
    initToc();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
