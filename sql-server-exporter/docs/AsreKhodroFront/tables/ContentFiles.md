# dbo.ContentFiles

**Database:** AsreKhodroFront  
**Rows:** 951,416  
**Primary key:** RowId, ContentId  
**Group:** Core content

## WordPress migration note

Media attached to content (published copy).

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| RowId | int | NO | PK |
| ContentId | int | NO | PK |
| FileId | int | NO |  |
| FileTypeId | int | NO |  |
| FileName | nvarchar(30) | NO |  |
| FileTitle | nvarchar(1024) | NO |  |
| Author | nvarchar(256) | YES |  |
| Watermark | nvarchar(1024) | YES |  |
| Description | nvarchar(2048) | YES |  |
| PeriorityInContent | bigint | NO |  |
| Params | nvarchar(256) | YES |  |
| EventTime | datetime | YES |  |
| ImageDimensionId | int | YES |  |
| URL | nvarchar(1024) | NO |  |
| IsMain | bit | YES |  |

## Logical relationships (within AsreKhodroFront)

- `ContentId` → `SingleContent.ContentId` — Media attached to content

## Cross-database links

- Referenced from **AsreKhodroBack**.ContentFiles.ContentId — Media attachments replicated to Front. *(verified: logical copy)*

---

[← Back to AsreKhodroFront overview](../README.md)
