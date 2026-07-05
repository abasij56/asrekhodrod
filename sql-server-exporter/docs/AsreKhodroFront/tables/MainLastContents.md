# dbo.MainLastContents

**Database:** AsreKhodroFront  
**Rows:** 197,572  
**Primary key:** RowId, ContentId  
**Group:** Homepage / featured caches

## WordPress migration note

Latest articles cache — rebuild in WordPress.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| RowId | int | NO | PK |
| ContentId | int | NO | PK |
| DomainId | int | NO |  |
| SiteComponentId | int | YES |  |
| MainCategoryId | int | YES |  |
| MainCategoryTitle | nvarchar(256) | YES |  |
| OverTitle | nvarchar(1024) | YES |  |
| Title | nvarchar(MAX) | NO |  |
| UnderTitle | nvarchar(1024) | YES |  |
| ShortBody | nvarchar(MAX) | YES |  |
| Params | nvarchar(256) | YES |  |
| Tags | nvarchar(MAX) | YES |  |
| PublishTime | datetime | NO |  |
| PageURL | nvarchar(512) | YES |  |
| Periority | bigint | YES |  |
| MainImageId | int | YES |  |
| ImageURL | nvarchar(1024) | YES |  |

## Logical relationships (within AsreKhodroFront)

- `ContentId` → `SingleContent.ContentId` — Latest content cache

---

[← Back to AsreKhodroFront overview](../README.md)
