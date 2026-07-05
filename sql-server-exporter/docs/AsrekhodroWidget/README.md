# AsrekhodroWidget

**Role:** Sidebar / widget content module  

Separate widget CMS with its own files and categories. Uses `DomainId` aligned with Back domains.

**WordPress priority:** Map widgets to WordPress widgets, blocks, or custom post types.

**Exported:** 2026-06-16T14:15:47.273Z  
**Tables:** 23  
**Total rows:** 4,615  
**Declared foreign keys:** 12

## Important notes

- Separate widget CMS with its own file IDs (not shared with Back files).
- `DomainId` references the same domain concept as AsreKhodroBack.Domain.
- 274 widgets across 8 component types.

## Entity groups

### Widgets

- [WidgetInitialize](./tables/WidgetInitialize.md) — 274 rows
- [WidgetCommonInfo](./tables/WidgetCommonInfo.md) — 274 rows
- [WidgetPrivateInfo](./tables/WidgetPrivateInfo.md) — 274 rows
- [WidgetComponents](./tables/WidgetComponents.md) — 8 rows
- [WidgetCategories](./tables/WidgetCategories.md) — 320 rows
- [WidgetCategoriesRelation](./tables/WidgetCategoriesRelation.md) — 488 rows
- [WidgetCategoriesRolesRelation](./tables/WidgetCategoriesRolesRelation.md) — 319 rows
- [WidgetParameters](./tables/WidgetParameters.md) — 3 rows
- [WidgetParametersRelation](./tables/WidgetParametersRelation.md) — 1,148 rows
- [WidgetFiles](./tables/WidgetFiles.md) — 249 rows
- [WidgetStatus](./tables/WidgetStatus.md) — 5 rows

### Files / media

- [FileInitialize](./tables/FileInitialize.md) — 244 rows
- [FileCommonInfo](./tables/FileCommonInfo.md) — 244 rows
- [FilePrivateInfo](./tables/FilePrivateInfo.md) — 244 rows
- [FilesFiletypes](./tables/FilesFiletypes.md) — 260 rows
- [FileCategories](./tables/FileCategories.md) — 244 rows
- [FileType](./tables/FileType.md) — 4 rows
- [ImageDimension](./tables/ImageDimension.md) — 7 rows

### Other

- [Coin](./tables/Coin.md) — 0 rows
- [CoinsStats](./tables/CoinsStats.md) — 0 rows
- [Weather](./tables/Weather.md) — 0 rows
- [UpcomingWeather](./tables/UpcomingWeather.md) — 0 rows
- [VariableType](./tables/VariableType.md) — 6 rows

## All tables

| Table | Rows | Group | WordPress hint |
|-------|------|-------|----------------|
| [Coin](./tables/Coin.md) | 0 | Other | — |
| [CoinsStats](./tables/CoinsStats.md) | 0 | Other | — |
| [FileCategories](./tables/FileCategories.md) | 244 | Files / media | — |
| [FileCommonInfo](./tables/FileCommonInfo.md) | 244 | Files / media | Widget media file metadata (separate file IDs from Back) |
| [FileInitialize](./tables/FileInitialize.md) | 244 | Files / media | — |
| [FilePrivateInfo](./tables/FilePrivateInfo.md) | 244 | Files / media | — |
| [FilesFiletypes](./tables/FilesFiletypes.md) | 260 | Files / media | — |
| [FileType](./tables/FileType.md) | 4 | Files / media | — |
| [ImageDimension](./tables/ImageDimension.md) | 7 | Files / media | — |
| [UpcomingWeather](./tables/UpcomingWeather.md) | 0 | Other | — |
| [VariableType](./tables/VariableType.md) | 6 | Other | — |
| [Weather](./tables/Weather.md) | 0 | Other | — |
| [WidgetCategories](./tables/WidgetCategories.md) | 320 | Widgets | Widget category tree |
| [WidgetCategoriesRelation](./tables/WidgetCategoriesRelation.md) | 488 | Widgets | — |
| [WidgetCategoriesRolesRelation](./tables/WidgetCategoriesRolesRelation.md) | 319 | Widgets | — |
| [WidgetCommonInfo](./tables/WidgetCommonInfo.md) | 274 | Widgets | Widget display content and timing |
| [WidgetComponents](./tables/WidgetComponents.md) | 8 | Widgets | Widget type definitions (8 components) |
| [WidgetFiles](./tables/WidgetFiles.md) | 249 | Widgets | Media attached to widgets |
| [WidgetInitialize](./tables/WidgetInitialize.md) | 274 | Widgets | Widget record header (component, status) |
| [WidgetParameters](./tables/WidgetParameters.md) | 3 | Widgets | Configurable widget parameters |
| [WidgetParametersRelation](./tables/WidgetParametersRelation.md) | 1,148 | Widgets | — |
| [WidgetPrivateInfo](./tables/WidgetPrivateInfo.md) | 274 | Widgets | — |
| [WidgetStatus](./tables/WidgetStatus.md) | 5 | Widgets | — |

## Relationships

- [Within-database relationships](./relationships.md)
- [Cross-database map](../cross-database-relationships.md)

---

[← Back to all databases](../README.md)
