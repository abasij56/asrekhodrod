# Asre Khodro Theme

WordPress theme for **عصر خودرو** — Timber + ACF Pro, based on the approved `prototype/`.

## Requirements

- WordPress 6.0+
- PHP 8.0+ (tested on 8.1; Timber pinned to 2.3.x — 2.4+ requires PHP 8.2)
- [ACF Pro](https://www.advancedcustomfields.com/) 6.x
- Composer

## Install

```bash
cd awp/wp-content/themes/asrekhodro-theme
composer install
```

Activate **Asre Khodro** in WordPress admin.

## Setup

1. **Settings → Reading** — set homepage to “A static page” or leave default (theme uses `front-page.php`).
2. **Appearance → Menus** — assign menus to Main / Footer locations.
3. **Theme Settings** (ACF options) — hero posts, featured category, news list count (max 40).
4. **Ads** — create `ad_slot` posts and assign `ad_position` taxonomy: `menu_strip`, `sidebar_left`, `content_row`.
5. Run **AsreKhodro Importer** (Tools) after JSON export is in `wp-content/asrekhodro-import/`.

## Structure

| Path | Purpose |
|------|---------|
| `inc/` | PHP bootstrap, CPTs, redirects, search |
| `views/` | Twig templates |
| `assets/css/style.css` | Prototype styles |
| `assets/js/app.js` | Nav, ticker, AJAX search, lazy reveal |

## Decisions (implemented)

- News → standard `post`
- Ads → CPT `ad_slot`
- Homepage news list → 40 items server-rendered, lazy images
- Legacy URLs → 301 redirect via `_asrekhodro_content_id` (`/News/{id}/...`)
- Importer v0.1.5 → extra meta + `pageUrl` / legacy path

## Legacy redirects

Old paths like `/News/263703/...` redirect to the WordPress permalink when `_asrekhodro_content_id` matches.

Define media base URL in `wp-config.php` if needed:

```php
define( 'ASREKHODRO_MEDIA_BASE_URL', 'https://www.asrekhodro.com' );
```
