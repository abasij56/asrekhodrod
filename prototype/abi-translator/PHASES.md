# ABI Translator — پرامپت فازها (۱ تا ۵)

> هر فاز را در **Agent mode** بزن، بعد تست کن، باگ بگیر، بعد سراغ فاز بعد برو.
> مرجع کامل spec: `prototype/abi-translator/PROMPT.md`
> محل پیاده‌سازی پلاگین: `dev/abi-translator/` (قابل کپی به `wp-content/plugins/abi-translator/`)

**قوانین ثابت در همهٔ فازها:**
- namespace `ABI\Translator` ، text-domain `abi-translator` ، option `abi_translator_settings` ، hook prefix `abi_translator_`
- ترجمه فقط در جدول `{prefix}abi_translations` — **هرگز** `post_meta`
- `lang=fa` هیچ overhead نگیرد
- fail-safe کامل: API down/timeout/خالی → متن فارسی + log ؛ بدون 500/صفحه سفید
- به `asrekhodro-theme/` دست نزن مگر واقعاً لازم شود (در آن صورت اول گزارش بده)

---

## فاز ۱ — اسکلت + ترجمهٔ single post

### پرامپت

```
پلاگین ABI Translator را طبق prototype/abi-translator/PROMPT.md بساز — فقط فاز ۱.

محل پیاده‌سازی:
- همهٔ فایل‌ها مستقیم در root فولدر dev/abi-translator/
- زیرپوشهٔ اضافه نساز ؛ bootstrap: dev/abi-translator/abi-translator.php
- فولدر باید مستقل و قابل کپی به wp-content/plugins/abi-translator/ باشد.

قوانین:
- Compat/AsreKhodro نساز (فاز ۳ نیست).
- hreflang، canonical، LanguageSwitcher، UrlBuilder نساز (فاز ۲ نیست).
- ترجمه هرگز در post_meta ذخیره نشود — فقط جدول {prefix}abi_translations.
- GapGPT provider پیش‌فرض ؛ model، API key، base URL از admin قابل تنظیم.
- سایت فارسی (lang=fa) هیچ overhead ترجمه نگیرد.

فاز ۱ شامل:
- bootstrap + activation hook (ساخت جدول با dbDelta) + uninstall.php
- Settings page: Settings → ABI Translator → AI (Provider, API Key, Base URL, Model, Temperature, Max tokens, Timeout) + testConnection
- LanguageDetector + rewrite/تشخیص پایه برای /en/
- TranslationRepository + TranslationCache + ContentHasher (source_hash = sha256 متن فارسی)
- ProviderInterface + GapGptProvider + ProviderFactory
- فیلتر the_title و the_content فقط برای post_type=post در single و فقط وقتی lang=en
- fail-safe + admin notice اگر API key نباشد

خارج از scope: homepage/بلاک‌ها، excerpt در لیست، post typeهای دیگر، bulk، Compat.

بعد از build:
1. لیست فایل‌های ساخته‌شده
2. مراحل نصب و فعال‌سازی
3. چک‌لیست تست فاز ۱
4. اگر resolve شدن /en/News/{id}/{slug} بدون Compat ممکن نبود، فقط گزارش بده (نساز).
```

### تست و چک‌لیست فاز ۱

- [ ] فعال‌سازی پلاگین → جدول `{prefix}abi_translations` ساخته شد
- [ ] Settings → ABI Translator: فیلدها ذخیره می‌شوند + دکمهٔ Test connection سبز
- [ ] `/` (فارسی) → بدون overhead، هیچ هوک ترجمه‌ای اجرا نمی‌شود
- [ ] `/en/News/{id}/{slug}` بار اول → title + body انگلیسی (کند، API) و ذخیره در DB
- [ ] همان URL بار دوم → سریع، از جدول (نه API)
- [ ] API key خالی/غلط یا API down → متن فارسی + بدون 500 + admin notice
- [ ] ویرایش متن فارسی خبر → بازدید بعدی `/en/` دوباره ترجمه (source_hash عوض شد)

---

## فاز ۲ — SEO + سوییچر زبان

### پرامپت

```
پلاگین ABI Translator (فاز ۱ کامل و تست‌شده) را طبق prototype/abi-translator/PROMPT.md ادامه بده — فقط فاز ۲.
روی همان ساختار Core موجود بساز، چیزی از فاز ۱ را نشکن.

خارج از scope (نساز): Compat/بلاک‌ها/Timber (فاز ۳)، post typeهای دیگر (فاز ۴)، purge/dashboard/metabox (فاز ۵).

فاز ۲ شامل:
1. تنظیمات زبان در Settings → ABI Translator:
   - فیلد Default language (select) — زبان اصلی که ترجمه نمی‌شود (پیش‌فرض fa)
   - فیلد Target languages (multi-select/چک‌باکس) — زبان‌های مقصد (پیش‌فرض en)
   - مقادیر در abi_translator_settings ذخیره و در sanitize معتبرسازی شوند
   - default_lang نباید داخل target languages باشد ؛ حداقل یک زبان مقصد
   - LanguageDetector و UrlBuilder از همین مقادیر بخوانند (نه ثابت hardcoded)
2. UrlBuilder (Core): تبدیل هر URL داخلی به نسخهٔ زبان فعال و برعکس.
   - fa بدون prefix ؛ en با prefix /en/ (prefix از تنظیمات، نه ثابت)
   - متد build(lang, url) و ساخت لینک زبان دیگر برای URL فعلی
3. SEO/Hreflang.php: در <head> فرانت:
   - <link rel="alternate" hreflang="fa" ...>
   - <link rel="alternate" hreflang="en" ...>
   - <link rel="alternate" hreflang="x-default" ...> (پیش‌فرض default_lang)
4. SEO/Canonical.php:
   - canonical مجزا برای هر زبان
   - سرکوب redirect_canonical برای /en/* تا به نسخهٔ فارسی ریدایرکت نشود
5. Frontend/LanguageSwitcher.php:
   - shortcode [abi_language_switcher] (FA | EN یا پرچم)
   - do_action('abi_translator_language_switcher') برای درج در تم
   - assets/css/language-switcher.css (enqueue فقط در فرانت)
   - کلیک → همان URL با prefix زبان دیگر (از UrlBuilder)
6. فیلتر excerpt (get_the_excerpt/the_excerpt) برای post در archive/list وقتی lang=en:
   - فقط excerpt ؛ body در لیست هرگز ترجمه نشود ؛ field=excerpt در جدول

Hookهای عمومی لازم:
- apply_filters('abi_translator_language_url', $url, $lang, $object)
- apply_filters('abi_translator_active_languages', ['fa','en'])

بعد از build:
1. لیست فایل‌های جدید/تغییرکرده
2. اگر درج سوییچر نیاز به یک خط در تم دارد، فقط snippet را گزارش بده (به تم دست نزن مگر تأیید کنم)
3. چک‌لیست تست فاز ۲
```

### تست و چک‌لیست فاز ۲

- [ ] در Settings: انتخاب Default language و Target languages ذخیره می‌شود و در فرانت اثر می‌کند
- [ ] در source هر صفحه: hreflang برای `fa` و `en` (+ `x-default`)
- [ ] canonical هر زبان درست ؛ `/en/*` بدون ریدایرکت ناخواسته به فارسی
- [ ] سوییچر: `/News/123/slug` ↔ `/en/News/123/slug` درست جابه‌جا می‌شود
- [ ] shortcode `[abi_language_switcher]` و `do_action` هر دو کار می‌کنند
- [ ] excerpt انگلیسی در آرشیو نمایش داده می‌شود ؛ بار دوم از DB
- [ ] body در لیست/آرشیو **هرگز** ترجمه نمی‌شود
- [ ] `/` (fa) هنوز بدون overhead

---

## فاز ۳ — صفحه اول + بلاک‌ها (Compat/AsreKhodro)

### پرامپت

```
ABI Translator (فاز ۱ و ۲ تست‌شده) را ادامه بده — فقط فاز ۳: Compat/AsreKhodro برای صفحه اول و بلاک‌ها.

محل: dev/abi-translator/src/Compat/AsreKhodro/ — Core را تغییر نده مگر لازم باشد (اگر لازم شد اول گزارش بده).
تم مرجع (فقط خواندن برای شناخت): asrekhodro-theme/ — به تم دست نزن مگر تأیید کنم.

خارج از scope: post typeهای غیر post (فاز ۴)، dashboard/purge/metabox (فاز ۵).

فاز ۳ شامل:
1. Compat/AsreKhodro/Bootstrap.php: فقط وقتی تم عصر خودرو فعال است لود شود ؛ همهٔ bridgeها را wire کند.
2. BlockLabelsBridge.php:
   - ترجمهٔ UI label بلاک‌های صفحه اول (عنوان بلاک، «مشاهده بیشتر»، «آرشیو اخبار ←»، ...)
   - object_type=block_label در جدول (object_id=0، key پایدار برای هر label)
   - منبع: config.php بلاک‌ها (default_title)، placement.title لِیاوت، رشته‌های Twig پرکاربرد
3. batch title برای بلاک‌های لیستی (ak-news-list, ak-featured-grid, ak-videos, ...):
   - متد getBatch در TranslationRepository (اگر نیست در Core اضافه کن و گزارش بده) تا 20–40 title با یک query خوانده شود، نه 40 query
   - missها یک‌جا ترجمه و ذخیره شوند
   - در صفحه اول فقط title (+ excerpt اگر تم نشان می‌دهد) ؛ body هرگز
4. TimberBridge.php:
   - add_filter('timber/context', ...) برای تزریق زبان فعال/سوییچر/کمک‌کننده‌ها
   - اطمینان از عبور {{ post.title }} بلاک‌ها از مسیر فیلتر ترجمه

بعد از build:
1. لیست فایل‌های Compat + هر تغییر Core (با دلیل)
2. چک‌لیست تست فاز ۳
```

### تست و چک‌لیست فاز ۳

- [ ] `/en/` صفحه اول: labelهای بلاک انگلیسی + title پست‌ها انگلیسی
- [ ] بار دوم `/en/` → از DB و سریع (بدون رگبار query ؛ batch کار می‌کند)
- [ ] بلاک ویدیو/فیچرد: titleها ترجمه، body نه
- [ ] `/` (fa) بدون overhead و بدون تغییر ظاهری
- [ ] labelها یک‌بار ترجمه و cache می‌شوند (تغییر ترتیب بلاک‌ها ترجمهٔ جدید نمی‌خواهد)
- [ ] API down در homepage → همه‌چیز فارسی، بدون خطا

---

## فاز ۴ — بقیه post typeها

### پرامپت

```
ABI Translator (فاز ۱–۳ تست‌شده) را ادامه بده — فقط فاز ۴: پشتیبانی از post typeهای دیگر.

هدف: تعمیم ترجمهٔ single و لیست به:
- ak_video, ak_review, ak_magazine, carsinfo
- در صورت نیاز taxonomy/category (نام و توضیح term ؛ object_type=term)

قوانین:
- منطق عمومی در Core ؛ نگاشت‌های permalink/routing خاص هر post type در Compat/AsreKhodro
- routing: /en/video/...، /en/review/... و غیره با همان روش strip پیشوند زبان کار کنند
- title همیشه، excerpt در لیست، body فقط در single همان post type
- lang=fa بدون overhead، fail-safe، ترجمه فقط در جدول

فاز ۴ شامل:
1. تعمیم PostFilters (یا فیلتر مناسب) به post typeهای هدف با گارد single + main query
2. TermFilters (Core): ترجمهٔ نام/توضیح term وقتی lang=en (اگر لازم شد)
3. Compat: bridge permalink برای هر post type تا /en/{base}/... درست resolve شود
4. اطمینان از hash/purge: ویرایش هر آبجکت → hash عوض → re-translate

بعد از build:
1. لیست فایل‌ها/تغییرات
2. چک‌لیست تست فاز ۴
```

### تست و چک‌لیست فاز ۴

- [ ] `/en/video/{...}` single: title + body انگلیسی ؛ بار دوم از DB
- [ ] `/en/review/{...}` single: title + body انگلیسی ؛ بار دوم از DB
- [ ] `ak_magazine` و `carsinfo` single: ترجمه درست
- [ ] آرشیو هر post type: title/excerpt انگلیسی
- [ ] category/term (اگر فعال شد): نام انگلیسی
- [ ] `/` (fa) بدون overhead
- [ ] هر post type: body در لیست ترجمه نمی‌شود، فقط در single

---

## فاز ۵ — ادمین و نگهداری

### پرامپت

```
ABI Translator (فاز ۱–۴ تست‌شده) را ادامه بده — فقط فاز ۵: ادمین و نگهداری.

فاز ۵ شامل:
1. Purge خودکار cache هنگام ویرایش پست/آبجکت:
   - هوک save_post (و ویرایش term اگر فاز ۴ فعال کرد)
   - حذف ردیف‌های ترجمهٔ آن object (TranslationRepository::delete_for_object) یا اتکا به تغییر source_hash — تصمیم بهینه بگیر و توضیح بده
2. Dashboard در Settings → ABI Translator:
   - تعداد کل ترجمه‌ها به تفکیک lang و object_type
   - هزینهٔ تخمینی (بر اساس request/token اگر ذخیره شده ؛ وگرنه تخمین ساده)
3. Re-translate دستی:
   - metabox در ویرایش پست: «ترجمهٔ مجدد این پست به EN» (حذف cache + ترجمهٔ فوری یا در بازدید بعد)
   - capability: manage_options برای settings، edit_post برای metabox
4. امنیت/نگهداری: sanitize خروجی، rate limit اختیاری per IP، لاگ بدون API key

بعد از build:
1. لیست فایل‌ها/تغییرات
2. چک‌لیست تست فاز ۵
```

### تست و چک‌لیست فاز ۵

- [ ] ویرایش پست فارسی → بازدید `/en/` همان پست → ترجمهٔ تازه (cache قدیمی نمانده)
- [ ] Dashboard: تعداد ترجمه‌ها به تفکیک lang/object_type درست
- [ ] Dashboard: هزینهٔ تخمینی نمایش داده می‌شود
- [ ] metabox «ترجمهٔ مجدد» کار می‌کند و cache را پاک می‌کند
- [ ] rate limit (اگر فعال شد) درخواست‌های پشت‌سرهم را محدود می‌کند
- [ ] در هیچ سناریو 500/صفحه سفید رخ نمی‌دهد
- [ ] در logها API key دیده نمی‌شود

---

## توصیه‌های روند کار (فاز → تست → فاز بعد)

- بین هر فاز یک بار **Settings → Permalinks → Save** بزن (routing/rewrite عوض می‌شود).
- بعد از تأیید هر فاز یک **commit جدا** بزن (مثلاً `abi-translator: phase 2`) تا بازگشت آسان باشد.
- روی staging **`WP_DEBUG = true`** باشد تا logهای fail-safe دیده شوند.
- تنها جایی که ممکن است Core در فاز بعدی لمس شود، افزودن `getBatch` در فاز ۳ است — به گزارش Agent دقت کن.
