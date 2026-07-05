# Sql Server Exporter (.NET)

پورت دات‌نت از ابزار `sql-server-exporter` (Node.js) برای استخراج داده‌های SQL Server و تولید JSON ورودی ایمپورت وردپرس.

الگوریتم، کوئری‌ها، fallback بین `AsreKhodroBack` / `AsreKhodroFront`، resume، chunking و enrich تصاویر همان نسخه Node است.

## پیش‌نیاز

- [.NET 8 SDK](https://dotnet.microsoft.com/download) یا جدیدتر
- دسترسی به SQL Server (همان سرور/پروفایل نسخه Node)
- برای احراز هویت Windows روی سرور محلی، فیلدهای `user` / `password` را خالی بگذارید

## راه‌اندازی

```powershell
cd sql-server-exporter-dotnet
copy connection.config.example.json connection.config.json
# connection.config.json را ویرایش کنید
```

## اجرا

از ریشه `sql-server-exporter-dotnet`:

```powershell
# نمونه (۱۰۰ پست)
dotnet run --project src/SqlServerExporter -- --limit 100

# همه داده‌ها (chunked)
dotnet run --project src/SqlServerExporter -- --all

# فقط Front (وقتی Back خراب است)
dotnet run --project src/SqlServerExporter -- --all --source=front

# فقط مجلات
dotnet run --project src/SqlServerExporter -- --magazine-limit 0

# enrich تصاویر از ContentFiles
dotnet run --project src/SqlServerExporter -- --enrich-images-only --source=front

# بدون resume
dotnet run --project src/SqlServerExporter -- --all --no-resume

# export پنجره‌ای — از رکورد ۰، تا آخر داده
dotnet run --project src/SqlServerExporter -- `
  --start 0 `
  --window 5000 `
  --file-chunk 1000 `
  --skip-batch 5 `
  --continue 1 `
  --source front

# export بازه‌ای — از رکورد ۲۰۰۰ تا قبل از ۵۰۰۰
dotnet run --project src/SqlServerExporter -- `
  --start 2000 `
  --end 5000 `
  --window 3000 `
  --file-chunk 1000 `
  --skip-batch 10 `
  --source front
```

خروجی پیش‌فرض: `../awp/wp-content/asrekhodro-import`

## آرگومان‌های CLI

| آرگومان | پیش‌فرض | توضیح |
|---------|---------|--------|
| `--profile` | `active` در config | پروفایل اتصال |
| `--server` | — | override سرور (بدون config) |
| `--source` | `auto` | `auto` / `front` / `back` |
| `--limit` | `100` (`0` با `--all`) | تعداد پست در حالت sample |
| `--review-limit` | `50` | تعداد بررسی‌ها در sample |
| `--magazine-limit` | `50` | تعداد مجلات در sample |
| `--output` | `awp/.../asrekhodro-import` | پوشه خروجی |
| `--all` | — | export کامل |
| `--no-resume` | — | شروع از صفر |
| `--enrich-images-only` | — | فقط enrich تصاویر پست |
| `--skip-content-file-images` | — | بدون enrich ContentFiles |
| `--start` | `0` | رکورد شروع (۰ = جدیدترین) |
| `--end` | — | رکورد پایان (انحصاری)؛ اگر باشد `continue` همیشه ۰ است |
| `--window` | `0` | تعداد رکورد در هر مرحله (وقتی `--end` نیست) |
| `--file-chunk` | `0` | تعداد ردیف در هر فایل JSON |
| `--skip-batch` | `1` | حداقل batch برای skip روی خرابی (Msg 605/823/824) |
| `--continue` | `0` | `1` = مرحله بعد تا پایان داده؛ `0` = فقط یک مرحله |

### حالت windowed

وقتی `--window` و `--file-chunk` هر دو بزرگ‌تر از صفر باشند:

- **`--start X`**: از رکورد X شروع کن (۰ = جدیدترین، ترتیب نزولی).
- **`--end E`** (اختیاری): تا قبل از رکورد E بخوان (`E - X` رکورد). اگر باشد `--continue` نادیده گرفته می‌شود (همیشه ۰).
- **`--window Y`**: وقتی `--end` نیست، در هر مرحله Y رکورد بخوان.
- **`--file-chunk Z`**: هر فایل `posts/posts-NNN.json` حداکثر Z رکورد.
- **`--skip-batch A`**: روی خرابی، batch نصف شود تا A؛ سپس A رکورد رد شود.
- **`--continue 0`**: فقط یک مرحله (یا بازه `--start`/`--end`).
- **`--continue 1`**: بعد از هر مرحله، از start+Y، start+2Y، … تا تمام داده.

تصاویر پست (`--source front`): `imageUrl` از `SingleContent.ImageURL`؛ سپس lookup تکی `ContentFiles` با `MainImageId=FileId` و `ImageDimensionId≠1`.

## ساختار پروژه

```
src/SqlServerExporter/
  Cli/           # parse آرگومان‌ها
  Config/        # connection.config.json
  Sql/           # کوئری‌ها و fragmentهای SQL
  Services/      # runner، chunk، progress، posts، images
  Export/        # WordPressExporter (جریان اصلی)
  Json/          # کمک‌کننده JSON rows
```

## مقایسه با نسخه Node

| Node (`sql-server-exporter`) | .NET (`sql-server-exporter-dotnet`) |
|------------------------------|-------------------------------------|
| `npm run export:sample` | `dotnet run --project src/SqlServerExporter -- --limit 100` |
| `npm run export:all` | `dotnet run --project src/SqlServerExporter -- --all` |
| `npm run enrich:post-images` | `dotnet run --project src/SqlServerExporter -- --enrich-images-only --source=front` |
| `sqlcmd` + `mssql` | `Microsoft.Data.SqlClient` + `FOR JSON PATH` |

## ایمپورت در وردپرس

پس از export:

**WP Admin → Tools → AsreKhodro Import → Run Import**
