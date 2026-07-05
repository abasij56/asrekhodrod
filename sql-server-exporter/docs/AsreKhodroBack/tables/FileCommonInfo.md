# dbo.FileCommonInfo

**Database:** AsreKhodroBack  
**Rows:** 287,559  
**Primary key:** Id  
**Group:** Files / media

## WordPress migration note

File metadata and URLs.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| FileId | int | NO |  |
| Author | nvarchar(256) | YES |  |
| EventTime | datetime | YES |  |
| ApproveTime | datetime | YES |  |
| ExpireTime | datetime | NO |  |
| Title2 | nvarchar(1024) | YES |  |
| Watermark | nvarchar(1024) | YES |  |
| Description | nvarchar(2048) | YES |  |

## Logical relationships (within AsreKhodroBack)

- `FileId` → `FileInitialize.Id` — File metadata

---

[← Back to AsreKhodroBack overview](../README.md)
