# آیکون‌های SVG — کار اینفو

مجموعه آیکون‌های مرتبط با خودرو برای پروتوتایپ و پیاده‌سازی نهایی.

## ساختار

| پوشه | تعداد | منبع | لایسنس |
|------|-------|------|--------|
| `tabler/` | ~370 | [Tabler Icons](https://tabler.io/icons) outline | MIT |
| `tabler-filled/` | ~100 | Tabler Icons filled (همان نام‌های موجود) | MIT |
| `lucide/` | 288 | [Lucide](https://lucide.dev) via `lucide-static` | ISC |
| `custom/` | 1 | sprite اختصاصی عصر خودرو (`car-spec-icons.svg`) | پروژه |

فهرست هر مجموعه در `_manifest.txt` داخل همان پوشه است.

## دسته‌بندی Tabler (پرکاربردترین)

- **خودرو:** `car`, `car-4wd`, `car-suv`, `car-garage`, `car-crash`, `car-door`, …
- **موتور:** `engine`, `engine-off`, `manual-gears`, `steering-wheel`, `tire`, `wheel`
- **سوخت/برق:** `gas-station`, `charging-pile`, `battery-*`, `plug`, `bolt`, `ev-charger`
- **جاده:** `road`, `parking`, `route`, `navigation`, `map-pin`, `traffic-cone`, `u-turn-*`
- **داشبورد:** `gauge`, `dashboard`
- **سرویس:** `tool`, `wrench`, `wash-machine`, `settings`
- **ایمنی/سنسور:** `radar`, `camera`, `cctv`, `shield`, `alarm`

## استفاده

```html
<!-- Tabler / Lucide — فایل تکی -->
<img src="icons/tabler/engine.svg" alt="" width="24" height="24" />

<!-- Sprite اختصاصی -->
<svg class="ak-icon"><use href="icons/custom/car-spec-icons.svg#ak-cylinder"/></svg>
```

## دانلود مجدد

```powershell
# Tabler: clone مخزن و کپی از icons/outline
git clone --depth 1 https://github.com/tabler/tabler-icons.git .tmp-icons

# Lucide:
npm install lucide-static --prefix .tmp-npm
# فایل‌ها در node_modules/lucide-static/icons/
```

## نکته

برای **کارت‌های منتخب** فقط ۳ آیکون لازم است. برای **صفحه تک خودرو** حدود ۱۵–۲۰ آیکون اختصاصی کافی است؛ بقیه فیلدها می‌توانند از آیکون عمومی (مثلاً `check`, `info-circle`) استفاده کنند.
