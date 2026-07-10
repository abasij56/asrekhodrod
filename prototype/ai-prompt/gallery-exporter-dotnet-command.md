# Prompt — افزودن export mode جدید «gallery» به .NET اکسپورتر

> این فایل یک پرامپت آماده برای اجرا در حالت **Agent** است. کل بخش «PROMPT» را کپی و به ایجنت بده.
> بخش «Context» فقط برای درک انسانیِ مسئله است و نیازی به کپی کردن آن نیست.

---

## Context (چرا این کار لازم است)

در سایت قدیم (`asrekhodro.com`) هر خبر علاوه بر یک **تصویر اصلی/شاخص**، یک **گالری تصویر** هم دارد.
این گالری در دیتابیس قدیم به‌صورت ردیف‌های جداگانه در جدول `AsreKhodroFront.dbo.ContentFiles`
ذخیره شده و **جدا از متن بدنه** (`SingleContent.Body`) است.

اکسپورتر فعلی فقط **یک** تصویر (تصویر اصلی) را به‌عنوان `imageUrl` صادر می‌کند و بقیه‌ی تصاویر گالری
هرگز export نمی‌شوند؛ در نتیجه در سایت جدید (`asrekhodro.net`) بدنه‌ی خبر گالری ندارد.

هدف: افزودن یک حالت اکسپورت جدید که برای همه‌ی ~۲۰۰٬۰۰۰ خبر منتشرشده، تصاویر گالری را استخراج و در
JSON خروجی (chunked + resume) بنویسد. سمت وردپرس/PHP در یک تسک جداگانه انجام می‌شود و **خارج از محدوده‌ی این تسک** است.

مرجع‌های کد فعلی که باید از الگوی آن‌ها پیروی شود:
- CLI parsing: `src/SqlServerExporter/Cli/ExportOptions.cs`
- Entry / routing: `src/SqlServerExporter/Program.cs` → `src/SqlServerExporter/Export/WordPressExporter.cs`
- SQL queries: `src/SqlServerExporter/Sql/WordPressExportQueries.cs` و `src/SqlServerExporter/Sql/SqlFragments.cs`
- الگوی اکسپورت chunked + resume: `src/SqlServerExporter/Services/BatchedCollectionExporter.cs`
- الگوی windowed + safe fetch: `src/SqlServerExporter/Services/PostExporter.cs`
- نوشتن chunk / progress: `ChunkFileService` و `ExportProgressService`

---

## PROMPT (این بخش را به ایجنت بده)

### Task
در پروژه‌ی `sql-server-exporter-dotnet/src/SqlServerExporter` یک **export mode** جدید به نام
**gallery** اضافه کن که برای همه‌ی خبرهای منتشرشده در دیتابیس **Front**، تصاویرِ گالریِ هر خبر را از
جدول `AsreKhodroFront.dbo.ContentFiles` استخراج کند و به‌صورت خروجی JSON با قابلیت **chunking** و
**resume** بنویسد. خروجی فقط باید شامل «آی‌دی خبر» و «لیست مرتب‌شده‌ی URL تصاویر گالری» باشد.

این کار **نباید** هیچ‌کدام از اکسپورت‌های فعلی (`posts`, `categories`, `tags`, `comments`, ...) یا
مسیر full export را تغییر دهد یا خراب کند.

### منبع داده و اسکیمای مرتبط (فقط دیتابیس Front)
- جدول تصاویر: `AsreKhodroFront.dbo.ContentFiles`
  ستون‌های مهم: `RowId`, `ContentId`, `FileId`, `FileTypeId`, `ImageDimensionId`,
  `PeriorityInContent`, `URL`, `IsMain`.
- جدول محتوا: `AsreKhodroFront.dbo.SingleContent` با کلید `ContentId` و ستون `MainImageId`
  (که به `ContentFiles.RowId`ِ تصویر اصلی اشاره می‌کند).

### قوانین انتخاب تصاویر گالری (قطعی)
برای هر خبر، ردیف‌های `ContentFiles` را طوری انتخاب کن که:
1. `cf.ContentId = sc.ContentId` (JOIN با `SingleContent`).
2. `cf.FileTypeId = 1` → فایل، تصویر است.
3. `cf.ImageDimensionId = 3` → نسخه‌ی «تصویر بزرگ / Large».
4. `cf.URL` غیرخالی باشد (`URL IS NOT NULL AND LTRIM(RTRIM(URL)) <> ''`).
5. تصویرِ **اصلی** حذف شود (چون قبلاً به‌عنوان featured image ایمپورت شده و با آن کاری نداریم).
   ⚠️ **نکته‌ی مهمِ درستی:** `SingleContent.MainImageId` به یک `RowId` مشخص اشاره می‌کند که ممکن است
   نسخه‌ی سایز دیگری (مثلاً thumbnail) از همان تصویر باشد. پس حذف را بر اساس **`FileId`ِ تصویر اصلی**
   انجام بده تا نسخه‌ی Largeِ همان تصویر اصلی هم داخل گالری نیفتد. معادلِ منطقی:
   ```sql
   AND NOT EXISTS (
     SELECT 1
     FROM AsreKhodroFront.dbo.ContentFiles m
     WHERE m.RowId = sc.MainImageId
       AND m.FileId = cf.FileId
   )
   ```
6. ترتیب تصاویر به ترتیب حضورشان در محتوا:
   `ORDER BY cf.ContentId, cf.PeriorityInContent, cf.RowId`.
7. داخل هر خبر، URLهای تکراری حذف شوند ولی ترتیب اولین حضور حفظ شود.
8. خبرهایی که بعد از فیلترها هیچ تصویر گالری ندارند، در خروجی **نیایند**.

URLها را دقیقاً همان‌طور که در `cf.URL` ذخیره شده‌اند (**raw**، بدون absolute/rewrite) خروجی بده —
دقیقاً مثل رفتار فعلیِ اکسپورتر برای `imageUrl`. سمت وردپرس خودش URL را resolve می‌کند.

### فرمت خروجی JSON
خروجی **گروه‌بندی‌شده به‌ازای هر خبر**؛ هر رکورد دقیقاً دو فیلد:
```json
[
  { "contentId": 263703, "images": ["/Uploaded/Image/....jpg", "/Uploaded/Image/....jpg"] }
]
```
- نام فیلدها دقیقاً `contentId` و `images` (camelCase، هم‌خوان با بقیه‌ی خروجی‌ها).

### یکپارچه‌سازی با معماری فعلی (این الگوها را دنبال کن)
- CLI فقط flag محور است (نه subcommand). یک flag جدید `--gallery-only` اضافه کن، مشابه
  `--enrich-images-only`. آن را در `Cli/ExportOptions.cs` به یک property جدید `bool GalleryOnly` map کن.
- در `Program.cs` → `WordPressExporter.Run()`: وقتی `options.GalleryOnly` ست بود، به یک متد جدید
  `RunGalleryOnly(...)` مسیر بده و سپس return کن (بدون اجرای full export) — دقیقاً مثل مسیر فعلیِ
  `EnrichImagesOnly`.
- منطق اکسپورت را در یک سرویس جدید `Services/GalleryExporter.cs` بگذار.
- SQL را به‌صورت یک query جدیدِ paged در `Sql/WordPressExportQueries.cs` (یا `Sql/SqlFragments.cs`) اضافه کن.
- خروجی chunked با subfolder `gallery/` و progress key `gallery` در `.export-progress.json`.
  از زیرساخت موجود استفاده کن: `ChunkFileService.WriteChunkFile(outputDir, "gallery", index, rows)`،
  `ExportProgressService`، و برای resume همان الگوی `BatchedCollectionExporter`/`PostExporter`.
- خروجی در همان `options.Output` نوشته شود (کنار بقیه‌ی JSONها).

#### ⚠️ نکته‌ی حیاتی درباره‌ی chunking
چون خروجی «گروه‌بندی‌شده به‌ازای هر `contentId`» است، **نباید** تصاویر یک خبر بین دو chunk نصف شود.
بنابراین صفحه‌بندی (pagination) را روی **contentId** انجام بده، نه روی ردیف‌های `ContentFiles`.
پیشنهاد پیاده‌سازی:
- روی `SingleContent` (به‌ترتیب `ContentId`) با `OFFSET/FETCH` پنجره بردار، یا بازه‌های `contentId`
  را (مثل windowed posts با `--start/--window/--file-chunk/--continue`) پیمایش کن.
- برای هر batch از contentIdها، ردیف‌های گالری‌شان را با query بالا بگیر، در حافظه بر اساس
  `contentId` گروه کن، و هر chunk را بنویس.
- resume باید مثل بقیه کار کند: اگر `progress.gallery.complete = true` بود دوباره کاری نکن؛ در غیر
  این صورت از offset/چانک ذخیره‌شده ادامه بده.
- از همان error-handling امن استفاده کن (split-on-corruption مثل
  `FetchCollectionPageSafely`/`FetchPostsPageSafely`؛ خطاهای Msg 605/823/824).

از flagهای موجود پشتیبانی کن: `--source` (پیش‌فرض `front` چون داده در Front است)،
`--resume`/`--no-resume`، و پارامترهای windowing/چانک
(`--start`, `--window`, `--file-chunk`, `--continue`, و در صورت لزوم `--end`).

### Acceptance / تست
- Build موفق: `dotnet build` بدون error/warning جدید.
- اجرای نمونه‌ی محدود (یک window کوچک) و بررسی این‌که در `{output}/gallery/` فایل‌های chunk ساخته
  می‌شوند و `.export-progress.json` کلید `gallery` دارد.
- برای `contentId = 263703` بررسی کن که در خروجی یک رکورد با آرایه‌ی `images`ِ مرتب و **بدون تصویر
  اصلی** وجود دارد (با اجرای دستیِ همان SQL روی DB مقایسه کن).
- اجرای مجدد با `--resume` نباید chunkها را دوباره بسازد (idempotent).
- اکسپورت‌های دیگر و مسیر full export دست‌نخورده و سالم بمانند.

### خارج از محدوده (انجام نده)
- سمت وردپرس/PHP importer را دست نزن (در تسک جداگانه `images` را به بدنه‌ی پست تزریق می‌کنیم).
- منطق تصویر اصلی/featured فعلی را تغییر نده.
- URLها را absolute یا rewrite نکن.

### خروجیِ گزارش نهایی
بعد از پیاده‌سازی، این‌ها را گزارش بده:
- لیست فایل‌های تغییر/اضافه‌شده.
- نام دقیق flag و یک نمونه دستور اجرا برای اکسپورتِ کاملِ گالریِ ۲۰۰k خبر (windowed/chunked).
- خلاصه‌ی SQLِ نهایی و ساختار خروجی.
