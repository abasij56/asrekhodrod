/**
 * عصر خودرو — Homepage Prototype
 * Vanilla JS: navigation, ticker, scroll effects, newsletter
 */

(function () {
  "use strict";

  /* ── Persian date in header ── */
  function setPersianDate() {
    var el = document.getElementById("current-date");
    if (!el) return;

    try {
      var formatted = new Intl.DateTimeFormat("fa-IR", {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
      }).format(new Date());
      el.textContent = formatted;
    } catch (e) {
      el.textContent = new Date().toLocaleDateString("fa-IR");
    }
  }

  /* ── Mobile navigation ── */
  function initMobileNav() {
    var toggle = document.getElementById("nav-toggle");
    var nav = document.getElementById("main-nav");
    if (!toggle || !nav) return;

    toggle.addEventListener("click", function () {
      var isOpen = nav.classList.toggle("is-open");
      toggle.classList.toggle("is-active", isOpen);
      toggle.setAttribute("aria-expanded", String(isOpen));
    });

    nav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        nav.classList.remove("is-open");
        toggle.classList.remove("is-active");
        toggle.setAttribute("aria-expanded", "false");
      });
    });

    document.addEventListener("click", function (e) {
      if (!nav.contains(e.target) && !toggle.contains(e.target)) {
        nav.classList.remove("is-open");
        toggle.classList.remove("is-active");
        toggle.setAttribute("aria-expanded", "false");
      }
    });
  }

  /* ── Duplicate ticker items for seamless loop ── */
  function initTicker() {
    var track = document.querySelector(".ticker__track");
    var list = document.querySelector(".ticker__list");
    if (!track || !list) return;

    var clone = list.cloneNode(true);
    clone.setAttribute("aria-hidden", "true");
    track.appendChild(clone);
  }

  /* ── Sticky header shadow on scroll ── */
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

  /* ── Intersection observer for fade-in cards ── */
  function initReveal() {
    if (!("IntersectionObserver" in window)) return;

    var targets = document.querySelectorAll(
      ".card, .magazine-item, .video-card, .market-card, .review-card, .hero-text-item, .news-list__item"
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
        "opacity 0.5s ease " + i * 0.03 + "s, transform 0.5s ease " + i * 0.03 + "s";
      observer.observe(el);
    });
  }

  /* ── Newsletter form (prototype) ── */
  function initNewsletter() {
    var form = document.getElementById("newsletter-form");
    if (!form) return;

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var input = form.querySelector('input[type="email"]');
      if (!input || !input.value.trim()) {
        input.focus();
        return;
      }
      alert("عضویت شما با موفقیت ثبت شد. (نمونه نمایشی)");
      input.value = "";
    });
  }

  /* ── Search (prototype) ── */
  function initSearch() {
    var forms = document.querySelectorAll(".top-header__search");
    forms.forEach(function (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var input = form.querySelector("input");
        if (input && input.value.trim()) {
          alert('جستجو برای: "' + input.value.trim() + '" (نمونه نمایشی)');
        }
      });
    });
  }

  /* ── Video card click (prototype) ── */
  function initVideos() {
    document.querySelectorAll(".video-card").forEach(function (card) {
      card.addEventListener("click", function () {
        var title = card.querySelector("h3");
        if (title) {
          alert("پخش ویدئو: " + title.textContent + " (نمونه نمایشی)");
        }
      });
      card.setAttribute("role", "button");
      card.setAttribute("tabindex", "0");
      card.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          card.click();
        }
      });
    });
  }

  /* ── Classic news list (~40 items) ── */
  var NEWS_IMAGES = [
    "https://images.unsplash.com/photo-1617788138017-80ad40651399?w=326&h=218&fit=crop&q=80",
    "https://images.pexels.com/photos/170811/pexels-photo-170811.jpeg?auto=compress&cs=tinysrgb&w=326&h=218&fit=crop",
    "https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?w=326&h=218&fit=crop&q=80",
    "https://images.unsplash.com/photo-1583121274602-3e2820c59d9f?w=326&h=218&fit=crop&q=80",
    "https://images.pexels.com/photos/1149137/pexels-photo-1149137.jpeg?auto=compress&cs=tinysrgb&w=326&h=218&fit=crop",
    "https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=326&h=218&fit=crop&q=80",
    "https://images.pexels.com/photos/359392/pexels-photo-359392.jpeg?auto=compress&cs=tinysrgb&w=326&h=218&fit=crop",
    "https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=326&h=218&fit=crop&q=80",
  ];

  var NEWS_ITEMS = [
    { title: "ترمز تویوتا در برقی‌ها", excerpt: "تویوتا موتورز در بحبوحه بازار جهانی راکد خودروهای برقی، قصد دارد طراحی نسل بعدی خودروهای برقی را بازنگری کند." },
    { title: "قیمت‌گذاری جدید خودروهای داخلی در تیرماه", excerpt: "افزایش جزئی قیمت برخی محصولات ایران‌خودو و سایپا با توجه به نرخ ارز و هزینه‌های تولید اعلام شد." },
    { title: "رونمایی شاسی‌بلند برقی چینی در نمایشگاه شانگهای", excerpt: "خودروساز چینی با برد ۷۰۰ کیلومتری و شتاب ۰ تا ۱۰۰ در ۳.۸ ثانیه، رقابت تازه‌ای در بازار EV آغاز کرد." },
    { title: "آغاز پیش‌فروش پژو ۲۰۷ و تارا", excerpt: "ایران‌خودو زمان‌بندی تحویل و شرایط فروش جدید را برای دو محصول پرفروش خود اعلام کرد." },
    { title: "تحلیل بازار خودروهای کارکرده در خرداد ۱۴۰۵", excerpt: "کارشناسان عصر خودرو روند قیمت خودروهای دست‌دوم را در کلان‌شهرها بررسی کردند." },
    { title: "فولکس‌واگن ID.7 به خط تولید رسید", excerpt: "سدان برقی جدید فولکس با پلتفرم MEB و برد بیش از ۶۰۰ کیلومتر وارد بازار اروپا شد." },
    { title: "تست ایمنی پنج ستاره برای کراس‌اوور جدید", excerpt: "نتایج تست Euro NCAP نشان می‌دهد خودروی ساخت چین در تمامی معیارهای ایمنی عملکرد قابل قبولی داشته است." },
    { title: "سایپا طرح فروش شاهین و ساینا را اعلام کرد", excerpt: "متقاضیان می‌توانند از طریق سامانه یکپارچه فروش خودرو ثبت‌نام کنند." },
    { title: "ورود رسمی برند لامبورگینی به بازار خودروهای برقی", excerpt: "لامبورگینی از برنامه تولید اولین خودروی تمام‌برقی خود در سال ۲۰۲۸ خبر داد." },
    { title: "تغییر سیاست واردات خودروهای هیبریدی", excerpt: "دولت شرایط جدیدی برای واردات خودروهای هیبریدی و پلاگین هیبرید اعلام کرد." },
    { title: "افزایش تولید خودرو در خطوط ایران‌خودو", excerpt: "گزارش‌ها حاکی از افزایش ۱۲ درصدی تولید نسبت به ماه مشابه سال قبل است." },
    { title: "معرفی نسل جدید BMW سری ۵", excerpt: "ب‌ام‌و با طراحی تازه، سیستم‌های کمک‌راننده پیشرفته و موتورهای هیبریدی سری ۵ را معرفی کرد." },
    { title: "قیمت روز خودروهای وارداتی در بازار تهران", excerpt: "جدول قیمت خودروهای وارداتی و مونتاژی در تاریخ ۱۰ خرداد ۱۴۰۵ منتشر شد." },
    { title: "کاهش تقاضا برای خودروهای بنزینی در اروپا", excerpt: "آمار فروش اتحادیه اروپا نشان می‌دهد سهم خودروهای برقی برای نخستین بار از ۲۰ درصد عبور کرد." },
    { title: "رونمایی پیکاپ برقی آمریکایی", excerpt: "خودروساز آمریکایی پیکاپ تمام‌برقی با ظرفیت یدک‌کشی ۴.۵ تن معرفی کرد." },
    { title: "جزئیات بیمه بدنه خودروهای صفر کیلومتر", excerpt: "بیمه مرکزی شرایط تخفیف بیمه بدنه برای خودروهای تازه تحویل را اعلام کرد." },
    { title: "تست رانندگی هیوندای آیونیک ۶", excerpt: "سدان برقی هیوندای با طراحی آیرودینامیک و برد ۶۱۰ کیلومتری مورد ارزیابی قرار گرفت." },
    { title: "آغاز عملیات اجرایی طرح نوسازی ناوگان حمل‌ونقل", excerpt: "وزارت راه از حمایت مالی برای جایگزینی خودروهای فرسوده با خودروهای کم‌مصرف خبر داد." },
    { title: "معرفی تیگو ۹ پرو توسط چری", excerpt: "شاسی‌بلند سه ردیف صندلی چری با موتور ۲ لیتری توربو وارد فاز پیش‌فروش شد." },
    { title: "برنامه توسعه شبکه شارژ خودروهای برقی", excerpt: "شرکت توزیع برق از نصب ۵۰۰ ایستگاه شارژ سریع در بزرگراه‌های کشور خبر داد." },
    { title: "مقایسه دنا پلاس و تارا اتوماتیک", excerpt: "دو سدان پرفروش بازار ایران از نظر مصرف سوخت، امکانات و ارزش خرید مقایسه شدند." },
    { title: "رکورد فروش BYD در بازار جهانی", excerpt: "خودروساز چینی در ماه گذشته بیش از ۳۰۰ هزار خودرو برقی به فروش رساند." },
    { title: "افزایش نرخ لیزینگ خودرو برای متقاضیان", excerpt: "شرکت‌های لیزینگ شرایط جدید اقساط و پیش‌پرداخت را اعلام کردند." },
    { title: "رونمایی کانسپت مرسدس کلاس E برقی", excerpt: "مرسدس-Benz از طراحی آینده سدان لوکس خود در نمایشگاه پاریس رونمایی کرد." },
    { title: "گزارش کیفیت خودروهای تولید داخل", excerpt: "مرکز تحقیقات صنعت خودرو گزارش سالانه کیفیت محصولات داخلی را منتشر کرد." },
    { title: "توقف تولید یکی از مدل‌های قدیمی پژو", excerpt: "ایران‌خودو از توقف تولید پژو ۴۰۵ به دلیل استانداردهای جدید آلایندگی خبر داد." },
    { title: "ورود دانگ‌فنگ به بازار خودروهای برقی ایران", excerpt: "نمایندگی رسمی برند چینی از آغاز فروش دو مدل برقی در تهران خبر داد." },
    { title: "آمار تصادفات جاده‌ای و نقش سیستم‌های ایمنی", excerpt: "پلیس راهور از کاهش ۸ درصدی تلفات تصادفات در بزرگراه‌ها گزارش داد." },
    { title: "معرفی موتور سه سیلندر توربو جدید", excerpt: "خودروساز ژاپنی موتور کم‌حجم با مصرف سوخت ۴.۵ لیتر در ۱۰۰ کیلومتر معرفی کرد." },
    { title: "قیمت قطعات یدکی خودروهای داخلی", excerpt: "اتحادیه فروشندگان قطعات از افزایش ۱۵ درصدی قیمت برخی قطعات پرتیراژ خبر داد." },
    { title: "تست آفرود لندکروزر سری ۳۰۰ جدید", excerpt: "شاسی‌بلند افسانه‌ای تویوتا در شرایط کویر و کوهستان مورد ارزیابی قرار گرفت." },
    { title: "آغاز صادرات خودرو به کشورهای همسایه", excerpt: "وزارت صنعت از افزایش صادرات خودرو و قطعه به عراق و آذربایجان خبر داد." },
    { title: "رونمایی نسخه پرفورمنس گلف GTI", excerpt: "فولکس‌واگن نسخه تقویت‌شده گلف GTI با ۲۶۵ اسب بخار معرفی کرد." },
    { title: "برنامه جایگزینی موتورسیکلت‌های کاربری", excerpt: "شهرداری تهران از تسهیلات خرید خودروهای برقی سبک برای پیک‌ها خبر داد." },
    { title: "تحلیل بازار خودروهای لوکس در ایران", excerpt: "فروش خودروهای لوکس وارداتی با وجود محدودیت‌ها روند صعودی ملایمی داشته است." },
    { title: "معرفی کامیونت برقی برای حمل شهری", excerpt: "استارتاپ اروپایی کامیونت تمام‌برقی با برد ۲۰۰ کیلومتر برای توزیع شهری معرفی کرد." },
    { title: "جزئیات طرح تعویض پلاک خودروهای شخصی", excerpt: "پلیس راهور شرایط جدید تعویض پلاک و انتقال سند را اعلام کرد." },
    { title: "رونمایی کوپه برقی پورشه", excerpt: "پورشه از نسل جدید کوپه تمام‌برقی خود با شتاب زیر ۴ ثانیه خبر داد." },
    { title: "گزارش مصرف واقعی خودروهای هیبریدی", excerpt: "نتایج تست مصرف سوخت واقعی نشان می‌دهد برخی مدل‌ها از اعداد رسمی فاصله دارند." },
    { title: "افتتاح بزرگ‌ترین نمایشگاه خودرو غرب کشور", excerpt: "مجتمع جدید با حضور ۱۵ برند داخلی و خارجی در کرمانشاه افتتاح شد." },
    { title: "آینده تولید خودرو در منطقه ویژه اقتصادی", excerpt: "سرمایه‌گذاران خارجی از برنامه ساخت کارخانه مونتاژ خودرو در منطقه آزاد خبر دادند." },
  ];

  function initNewsList() {
    var list = document.getElementById("news-list");
    if (!list) return;

    var fragment = document.createDocumentFragment();

    NEWS_ITEMS.forEach(function (item, index) {
      var li = document.createElement("li");
      li.className = "news-list__item";

      var imgSrc = NEWS_IMAGES[index % NEWS_IMAGES.length];
      var slug = "news-" + (index + 1);

      li.innerHTML =
        '<article>' +
        '<a href="/News/' + slug + '" class="news-list__link">' +
        '<div class="news-list__media">' +
        '<img src="' + imgSrc + '" width="163" height="109" alt="' + item.title + '" loading="lazy" />' +
        "</div>" +
        '<div class="news-list__body">' +
        '<h2 class="news-list__title">' + item.title + "</h2>" +
        '<p class="news-list__excerpt"><strong>عصر خودرو-</strong> ' + item.excerpt + "</p>" +
        "</div>" +
        "</a>" +
        "</article>";

      fragment.appendChild(li);
    });

    list.appendChild(fragment);
  }

  /* ── Init ── */
  document.addEventListener("DOMContentLoaded", function () {
    setPersianDate();
    initMobileNav();
    initTicker();
    initHeaderScroll();
    initNewsList();
    initReveal();
    initNewsletter();
    initSearch();
    initVideos();
  });
})();
