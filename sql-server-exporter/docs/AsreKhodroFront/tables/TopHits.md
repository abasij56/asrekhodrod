# dbo.TopHits

**Database:** AsreKhodroFront  
**Rows:** 33  
**Primary key:** ContentId  
**Group:** Homepage / featured caches

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| ContentId | int | NO | PK |
| SiteComponent | int | YES |  |
| DomainId | int | YES |  |
| MainCategoryTitle | nvarchar(256) | YES |  |
| OverTitle | nvarchar(1024) | YES |  |
| Title | nvarchar(MAX) | NO |  |
| UnderTitle | nvarchar(1024) | YES |  |
| ShortBody | nvarchar(MAX) | YES |  |
| PublishTime | datetime | NO |  |
| PageURL | nvarchar(512) | YES |  |
| Periority | bigint | YES |  |
| ImageURL | nvarchar(1024) | YES |  |
| HitCount | int | YES |  |

## Logical relationships (within AsreKhodroFront)

- `ContentId` → `SingleContent.ContentId` — Popular content

---

[← Back to AsreKhodroFront overview](../README.md)
