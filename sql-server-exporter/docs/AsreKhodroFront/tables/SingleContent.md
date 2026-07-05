# dbo.SingleContent

**Database:** AsreKhodroFront  
**Rows:** 197,643  
**Primary key:** ContentId  
**Group:** Core content

## WordPress migration note

Primary published article view. Prefer exporting from Back; use Front only as validation.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| ContentId | int | NO | PK |
| DomainId | int | NO |  |
| SiteComponentId | int | YES |  |
| MainCategoryId | int | YES |  |
| MainCategoryTitle | nvarchar(256) | YES |  |
| OverTitle | nvarchar(1024) | YES |  |
| Title | nvarchar(MAX) | NO |  |
| UnderTitle | nvarchar(1024) | YES |  |
| ShortBody | nvarchar(MAX) | YES |  |
| Body | nvarchar(MAX) | NO |  |
| Footer | nvarchar(MAX) | YES |  |
| Author | nvarchar(256) | YES |  |
| Params | nvarchar(256) | YES |  |
| Tags | nvarchar(MAX) | YES |  |
| PublishTime | datetime | NO |  |
| MainImageId | int | YES |  |
| ImageURL | nvarchar(1024) | YES |  |

## Logical relationships (within AsreKhodroFront)

- Referenced by `ContentCategories.ContentId` — Content belongs to categories
- Referenced by `ContentFiles.ContentId` — Media attached to content
- Referenced by `KeywordsContent.ContentId` — Tags/keywords for content
- Referenced by `ContentsRelation.ParentContentId` — Related content (parent)
- Referenced by `ContentsRelation.ChildContentId` — Related content (child)
- Referenced by `Hits.ItemId` — Content view tracking
- Referenced by `TopHits.ContentId` — Popular content
- Referenced by `MainContents.ContentId` — Homepage cache
- Referenced by `MainLastContents.ContentId` — Latest content cache

## Cross-database links

- Referenced from **AsreKhodroBack**.ContentCommonInfo.ContentId — Front publishes a subset of Back content (197,643 / 199,653 rows matched in sample). *(verified: 197643 matching rows)*

---

[← Back to AsreKhodroFront overview](../README.md)
