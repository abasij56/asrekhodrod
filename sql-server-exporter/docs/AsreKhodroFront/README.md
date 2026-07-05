# AsreKhodroFront

**Role:** Published website cache (read-optimized)  

Denormalized snapshot of published content for the public site. `SingleContent` mirrors Back content; `Main*` tables are homepage module caches.

**WordPress priority:** Optional read model — prefer migrating from Back, then rebuild homepage blocks in WordPress.

**Exported:** 2026-06-16T14:15:45.237Z  
**Tables:** 21  
**Total rows:** 311,173,742  
**Declared foreign keys:** 0

## Important notes

- No declared SQL foreign keys — relationships are logical.
- `SingleContent` is the main published article table.
- `Main*` tables are homepage caches — rebuild in WordPress rather than migrate.
- `Hits` is very large — skip for content migration.

## Entity groups

### Core content

- [SingleContent](./tables/SingleContent.md) — 197,643 rows
- [ContentCategories](./tables/ContentCategories.md) — 521,431 rows
- [ContentFiles](./tables/ContentFiles.md) — 951,416 rows
- [ContentsRelation](./tables/ContentsRelation.md) — 1,041 rows
- [KeywordsContent](./tables/KeywordsContent.md) — 310,801 rows

### Taxonomy & navigation

- [Categories](./tables/Categories.md) — 99 rows
- [Menu](./tables/Menu.md) — 19 rows

### Homepage / featured caches

- [MainContents](./tables/MainContents.md) — 5 rows
- [MainLastContents](./tables/MainLastContents.md) — 197,572 rows
- [MainMultimedia](./tables/MainMultimedia.md) — 150 rows
- [MainSlider](./tables/MainSlider.md) — 10 rows
- [MainTicker](./tables/MainTicker.md) — 5 rows
- [MainTopHits](./tables/MainTopHits.md) — 0 rows
- [Parsik](./tables/Parsik.md) — 10 rows
- [SpecialEvents](./tables/SpecialEvents.md) — 1 rows
- [SalesLastConditions](./tables/SalesLastConditions.md) — 0 rows
- [TopHits](./tables/TopHits.md) — 33 rows

### Monetization

- [Advertisements](./tables/Advertisements.md) — 765 rows

### Analytics

- [Hits](./tables/Hits.md) — 308,992,740 rows

### Other

- [Departments](./tables/Departments.md) — 1 rows
- [sysdiagrams](./tables/sysdiagrams.md) — 0 rows

## All tables

| Table | Rows | Group | WordPress hint |
|-------|------|-------|----------------|
| [Advertisements](./tables/Advertisements.md) | 765 | Monetization | Banner ads → custom post type or ad plugin |
| [Categories](./tables/Categories.md) | 99 | Taxonomy & navigation | Category tree (published copy) |
| [ContentCategories](./tables/ContentCategories.md) | 521,431 | Core content | Post–category links (published copy) |
| [ContentFiles](./tables/ContentFiles.md) | 951,416 | Core content | Media attached to content (published copy) |
| [ContentsRelation](./tables/ContentsRelation.md) | 1,041 | Core content | Related articles (parent/child) |
| [Departments](./tables/Departments.md) | 1 | Other | Contact departments → static pages or CPT |
| [Hits](./tables/Hits.md) | 308,992,740 | Analytics | Page-view log (~300M rows) — skip for migration |
| [KeywordsContent](./tables/KeywordsContent.md) | 310,801 | Core content | Tags/keywords per article |
| [MainContents](./tables/MainContents.md) | 5 | Homepage / featured caches | Homepage featured cache — rebuild in WordPress |
| [MainLastContents](./tables/MainLastContents.md) | 197,572 | Homepage / featured caches | Latest articles cache — rebuild in WordPress |
| [MainMultimedia](./tables/MainMultimedia.md) | 150 | Homepage / featured caches | — |
| [MainSlider](./tables/MainSlider.md) | 10 | Homepage / featured caches | — |
| [MainTicker](./tables/MainTicker.md) | 5 | Homepage / featured caches | — |
| [MainTopHits](./tables/MainTopHits.md) | 0 | Homepage / featured caches | — |
| [Menu](./tables/Menu.md) | 19 | Taxonomy & navigation | Site navigation → WordPress menus |
| [Parsik](./tables/Parsik.md) | 10 | Homepage / featured caches | — |
| [SalesLastConditions](./tables/SalesLastConditions.md) | 0 | Homepage / featured caches | — |
| [SingleContent](./tables/SingleContent.md) | 197,643 | Core content | Primary published article view |
| [SpecialEvents](./tables/SpecialEvents.md) | 1 | Homepage / featured caches | — |
| [sysdiagrams](./tables/sysdiagrams.md) | 0 | Other | SQL Server internal — ignore |
| [TopHits](./tables/TopHits.md) | 33 | Homepage / featured caches | — |

## Relationships

- [Within-database relationships](./relationships.md)
- [Cross-database map](../cross-database-relationships.md)

---

[← Back to all databases](../README.md)
