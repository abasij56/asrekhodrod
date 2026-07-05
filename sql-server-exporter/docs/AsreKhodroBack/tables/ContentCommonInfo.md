# dbo.ContentCommonInfo

**Database:** AsreKhodroBack  
**Rows:** 199,653  
**Primary key:** Id  
**Group:** Content (split model)

## WordPress migration note

Article body and metadata (title fields, body, publish times). Key table for WordPress posts.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| ContentId | int | NO |  |
| Author | nvarchar(256) | YES |  |
| ContentTime | datetime | YES |  |
| PublishTime | datetime | YES |  |
| ExpireTime | datetime | YES |  |
| OverTitle | nvarchar(1024) | YES |  |
| UnderTitle | nvarchar(1024) | YES |  |
| ShortBody | nvarchar(2048) | YES |  |
| BodyText | nvarchar(MAX) | YES |  |
| Footer | nvarchar(2048) | YES |  |

## Logical relationships (within AsreKhodroBack)

- `ContentId` → `ContentInitialize.Id` — Body/metadata → content header

## Cross-database links

- `ContentId` → **AsreKhodroFront**.SingleContent.ContentId — Front publishes a subset of Back content (197,643 / 199,653 rows matched in sample). *(verified: 197643 matching rows)*
- `ContentId` → **AsreKhodroComments**.CommentCommonInfo.ObjectId — Comments attach to articles via ObjectId = ContentId. *(verified: 6807 / 6808 comments matched)*
- Referenced from **AsreKhodroBack**.ContentInitialize.Id — Within Back: content header (Initialize) + body/metadata (CommonInfo/PrivateInfo). *(verified: same database)*

---

[← Back to AsreKhodroBack overview](../README.md)
