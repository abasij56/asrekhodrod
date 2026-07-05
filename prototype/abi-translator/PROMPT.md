# پروژه: ABI Translator (پلاگین چندزبانگی AI + SEO)

> **فایل پرامپت Agent** — این spec را در Cursor Agent mode بده تا پلاگین ساخته شود.

## هدف

**ABI Translator** یک پلاگین مستقل وردپرس است که با API هوش مصنوعی محتوای سایت را به زبان‌های دیگر ترجمه می‌کند — شبیه GTranslate ولی با **SEO واقعی** (URL جدا مثل `/en/`).

**اولین مصرف‌کننده:** سایت خبری فارسی «عصر خودرو».  
**آینده:** هسته (Core) برای فروش در ژاکت/مخزن وردپرس؛ یکپارچگی هر سایت در **Compat**.

### قوانین اصلی

1. تمام منطق ترجمه، routing، cache، SEO و تنظیمات AI در **پلاگین** است.
2. تم فقط hook/UI حداقلی می‌گیرد.
3. API key و model **هرگز** در تم نباشد.
4. **هرگز** ترجمه را در `post_meta` ذخیره نکن — جدول اختصاصی.
5. سایت فارسی (پیش‌فرض) **هیچ هزینه اضافه‌ای** نپردازد؛ ترجمه فقط وقتی `lang != fa`.

---

## نام‌گذاری (ثابت از فاز ۱)

| مورد | مقدار |
|------|--------|
| نام نمایشی | **ABI Translator** |
| پوشه پلاگین | `wp-content/plugins/abi-translator/` |
| فایل bootstrap | `abi-translator.php` |
| Text domain | `abi-translator` |
| Namespace PHP | `ABI\Translator` |
| جدول cache | `{prefix}abi_translations` |
| option تنظیمات | `abi_translator_settings` |
| پیشوند hook/filter | `abi_translator_` |

---

## بستر فعلی عصر خودرو (قبل از کدنویسی بخوان)

### تم (در همین repo)

- مسیر: `asrekhodro-theme/`
- Timber/Twig + Layout Engine
- ظاهر پیش‌فرض: `appearances/classic/`

### پست‌تایپ‌ها (`asrekhodro-theme/inc/PostTypes.php`)

| post_type | permalink / archive |
|-----------|---------------------|
| `post` (خبر) | `/News/{content_id}/{slug}` در `inc/NewsPermalinks.php` |
| `ak_video` | `/video/...` |
| `ak_magazine` | `/Home/Kiosk/...` |
| `ak_review` | `/review/...` |
| `carsinfo` | `/carsinfo/...` |
| `ad_slot` | غیرعمومی |

### خبر legacy

- هر پست meta دارد: `_asrekhodro_content_id`
- rewrite: `News/([0-9]+)/([^/]+)/?` → query var `ak_news_content_id`

### صفحه اول

- از `LayoutEngine` + بلاک‌ها (`asrekhodro-theme/inc/blocks/`)
- بلاک‌های مهم: `ak-ticker`, `ak-hero`, `ak-featured-grid`, `ak-news-list`, `ak-videos`, `ak-magazines`, `ak-reviews`
- هر بلاک دو نوع متن دارد:
  1. **UI بلاک** — عنوان‌ها و labelها (manifest / Twig)
  2. **محتوای query** — title, excerpt, body پست‌ها

---

## معماری پلاگین

```
abi-translator/
├── abi-translator.php
├── src/
│   ├── Core/                         # عمومی — قابل فروش بعداً
│   │   ├── Plugin.php
│   │   ├── Language/
│   │   │   ├── LanguageDetector.php
│   │   │   ├── LanguageRouter.php
│   │   │   └── UrlBuilder.php
│   │   ├── Translation/
│   │   │   ├── TranslationService.php
│   │   │   ├── TranslationRepository.php
│   │   │   ├── TranslationCache.php
│   │   │   └── ContentHasher.php
│   │   ├── AI/
│   │   │   ├── ProviderInterface.php
│   │   │   ├── GapGptProvider.php
│   │   │   ├── OpenAiProvider.php
│   │   │   └── ProviderFactory.php
│   │   ├── Filters/
│   │   │   ├── PostFilters.php       # the_title, the_content, excerpt
│   │   │   └── TermFilters.php
│   │   ├── SEO/
│   │   │   ├── Hreflang.php
│   │   │   └── Canonical.php
│   │   ├── Admin/
│   │   │   └── SettingsPage.php
│   │   └── Frontend/
│   │       ├── LanguageSwitcher.php
│   │       └── MenuIntegration.php
│   └── Compat/
│       └── AsreKhodro/
│           ├── Bootstrap.php
│           ├── NewsPermalinkBridge.php
│           ├── BlockLabelsBridge.php
│           └── TimberBridge.php
├── assets/
│   ├── css/language-switcher.css
│   └── js/language-switcher.js
└── uninstall.php
```

### تفکیک Core vs Compat

| Core (عمومی) | Compat/AsreKhodro (فقط این سایت) |
|--------------|----------------------------------|
| Provider AI | `/News/{id}/{slug}` + rewrite |
| جدول cache | بلاک‌های `ak-*` |
| `/en/` routing پایه | Timber context |
| فیلتر title/content | permalinkهای legacy |
| hreflang, canonical | UI labelهای manifest |
| سوییچر زبان | post typeهای سفارشی |

---

## جدول ترجمه (نه post_meta)

```sql
CREATE TABLE {prefix}abi_translations (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  object_type     VARCHAR(32)  NOT NULL,   -- post, term, option, block_label, ...
  object_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,
  field           VARCHAR(64)  NOT NULL,   -- title, excerpt, content, label, ...
  lang            VARCHAR(10)  NOT NULL,   -- en, ar, ...
  source_hash     CHAR(64)     NOT NULL,   -- sha256 متن فارسی
  translated_text LONGTEXT     NOT NULL,
  provider        VARCHAR(32)  NOT NULL DEFAULT '',
  model           VARCHAR(64)  NOT NULL DEFAULT '',
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_object (object_type, object_id, field, lang),
  KEY idx_lang (lang),
  KEY idx_hash (source_hash)
);
```

### source_hash

وقتی پست فارسی ویرایش شد، hash عوض می‌شود → ترجمه قدیمی invalid → re-translate on-demand.

---

## Provider AI (قابل عوض شدن)

### Interface

```php
interface ProviderInterface {
    public function translate(string $text, string $from, string $to, array $context = []): string;
    public function translateBatch(array $items, string $from, string $to): array;
    public function testConnection(): bool;
}
```

### تنظیمات ادمین

**Settings → ABI Translator → AI**

| فیلد | توضیح |
|------|--------|
| Provider | GapGPT / OpenAI / DeepL / Custom |
| API Key | password field |
| Base URL | اختیاری — برای GapGPT یا proxy |
| Model | مثلاً `gpt-4o-mini`, `gpt-4.1` |
| Temperature | 0.2–0.4 برای خبر |
| Max tokens | per request |
| Timeout | ثانیه |

GapGPT provider پیش‌فرض؛ مدل بعداً از admin عوض شود بدون تغییر Core.

---

## Routing و SEO

### زبان پیش‌فرض

- `fa` — بدون prefix: `/News/123/slug`

### زبان دوم (MVP: انگلیسی)

- با prefix: `/en/News/123/slug`
- `/en/video/...`, `/en/review/...` و غیره

### rewrite (Core)

```
^en/News/([0-9]+)/([^/]+)/?$  → lang=en + ak_news_content_id
^en/?$                        → front_page + lang=en
^en/(.*)$                     → lang=en + path=$1
```

### Compat/AsreKhodro

- bridge روی `NewsPermalinks` و `post_type_link`
- `UrlBuilder` لینک‌های `/en/...` بسازد

### SEO

- `<link rel="alternate" hreflang="fa" href="...">`
- `<link rel="alternate" hreflang="en" href="...">`
- `<link rel="alternate" hreflang="x-default" href="...">` (معمولاً fa)
- canonical برای هر زبان جدا
- `<html lang="en" dir="ltr">` برای EN (RTL/LTR switch)

---

## On-demand + Cache

### جریان

```
کاربر → /en/News/123/slug
  → LanguageDetector: lang=en
  → WP همان post_id فارسی را resolve می‌کند
  → PostFilters:
      title  → Repository.get → اگر miss → AI → save → return
      excerpt → همان
      content → فقط در single؛ body کامل
  → HTML ترجمه‌شده
```

### قوانین performance

| قانون | دلیل |
|-------|------|
| `lang=fa` → هیچ hook ترجمه | سرعت سایت فارسی |
| title/excerpt در لیست | body ترجمه نشود |
| body فقط در single | صفحه اول سبک بماند |
| `getBatch()` برای ۲۰–۴۰ title | یک query نه ۴۰ query |
| page cache برای `/en/*` | بازدید دوم سریع |
| Transient fallback | اختیاری برای HTML fragment |

### صفحه اول `/en/`

- UI label بلاک‌ها (Compat): «آخرین اخبار خودرو» → «Latest Car News»
- پست‌ها: فقط title (+ excerpt اگر لازم)
- body **هرگز** در homepage

---

## سوییچر زبان

### نمایش

- در منو (header) — پرچم یا slug: `FA | EN`
- shortcode: `[abi_language_switcher]`
- widget/block اختیاری

### رفتار

- کلیک → همان URL با prefix زبان دیگر
- `/News/123/slug` ↔ `/en/News/123/slug`
- cookie/session اختیاری: `abi_lang` (redirect بعدی)

### تم

- تم فقط `do_action('abi_translator_language_switcher')` یا shortcode در header.twig
- استایل پایه در پلاگین؛ override در تم با CSS

---

## Fail-safe

- API down → متن فارسی اصلی
- timeout → متن فارسی + log
- ترجمه خالی → fallback
- admin notice اگر API key نباشد
- **هرگز** صفحه سفید یا 500 نده

---

## Hookهای عمومی (برای تم و Compat)

```php
// آیا این پست ترجمه شود؟
apply_filters('abi_translator_should_translate_post', true, $post, $lang);

// قبل/بعد ترجمه
apply_filters('abi_translator_before_translate', $text, $context);
apply_filters('abi_translator_after_translate', $translated, $text, $context);

// زبان فعال
apply_filters('abi_translator_active_languages', ['fa', 'en']);

// URL زبان
apply_filters('abi_translator_language_url', $url, $lang, $object);

// Timber (Compat)
add_filter('timber/context', ...);
```

---

## فازبندی MVP

### فاز ۱ — اسکلت

- [ ] bootstrap پلاگین + activation (جدول DB)
- [ ] Settings (Provider, API Key, Model)
- [ ] LanguageDetector + `/en/` rewrite
- [ ] TranslationRepository
- [ ] GapGptProvider
- [ ] فیلتر `the_title` + `the_content` برای `post` single
- [ ] Fail-safe
- [ ] تست: `/en/News/{id}/{slug}`

### فاز ۲ — SEO + سوییچر

- [ ] hreflang + canonical
- [ ] LanguageSwitcher در منو
- [ ] UrlBuilder برای لینک‌های داخلی
- [ ] excerpt در archive

### فاز ۳ — صفحه اول + بلاک‌ها

- [ ] Compat/AsreKhodro/BlockLabelsBridge
- [ ] batch title برای `ak-news-list`, `ak-featured-grid`
- [ ] TimberBridge برای context

### فاز ۴ — بقیه post typeها

- [ ] ak_video, ak_review, ak_magazine, carsinfo
- [ ] taxonomy/category (در صورت نیاز)

### فاز ۵ — ادمین و نگهداری

- [ ] purge cache وقتی پست edit شد
- [ ] dashboard: تعداد ترجمه، هزینه تخمینی
- [ ] re-translate دستی از metabox

---

## تغییرات حداقلی در تم

تم **نباید** منطق ترجمه داشته باشد. فقط:

1. **header.twig** — جای سوییچر:

   ```twig
   {{ function('do_shortcode', '[abi_language_switcher]') }}
   ```

2. **base.twig** — `lang` و `dir` روی `<html>` (یا filter پلاگین)

3. **اختیاری:** CSS سوییچر در تم

---

## Prompt ترجمه (پیشنهادی)

```
You are a professional translator for an automotive news website.
Translate from Persian (fa) to English (en).
Rules:
- Keep HTML tags unchanged
- Keep numbers, URLs, brand names (Iran Khodro, SAIPA, Chery, etc.)
- News style: clear, neutral, journalistic
- Do not add or remove sentences
Output only the translation, no explanation.
```

برای body HTML: chunk به paragraph/block؛ batch برای titleها.

---

## امنیت

- API key فقط در `wp_options` (encrypted اگر ممکن)
- sanitize output
- rate limit per IP (اختیاری)
- capability برای settings: `manage_options`
- log بدون ذخیره API key

---

## تست

| سناریو | انتظار |
|--------|--------|
| `/` (fa) | بدون ترجمه، سرعت عادی |
| `/en/News/123` اولین بار | کند (API) |
| `/en/News/123` دومین بار | سریع (DB) |
| API خطا | متن فارسی |
| ویرایش پست | re-translate on next visit |
| hreflang | fa + en + x-default |

---

## خارج از scope فاز ۱

- ترجمه خودکار ۵۰هزار پست bulk
- post duplicate (WPML-style)
- ترجمه comment
- ترجمه media/alt (بعداً)
- ترجمه ACF field-by-field (بعداً در Compat)

---

## دستور Agent برای Cursor

```
پلاگین ABI Translator را طبق prototype/abi-translator/PROMPT.md بساز.
شروع از فاز ۱.
مسیر تم: asrekhodro-theme/
Compat/AsreKhodro را جدا نگه دار.
هرگز post_meta برای ترجمه استفاده نکن.
GapGPT را provider پیش‌فرض بگذار با model قابل تنظیم در admin.
```

---

## چک‌لیست تأیید

- [x] پلاگین مستقل
- [x] ترجمه AI با provider/model قابل تغییر
- [x] Core مستقل + Compat/AsreKhodro
- [x] on-demand + ذخیره در جدول + cache
- [x] سوییچر زبان (پرچم/slug) در منو
- [x] slug/prefix زبان برای SEO (`/en/`)
- [x] سایت فارسی بدون overhead
- [x] fail-safe
