# dbo.FileCommonInfo

**Database:** AsrekhodroWidget  
**Rows:** 244  
**Primary key:** Id  
**Group:** Files / media

## WordPress migration note

Widget media file metadata (separate file IDs from Back).

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

## Logical relationships (within AsrekhodroWidget)

- `FileId` → `FileInitialize.Id` — File metadata

---

[← Back to AsrekhodroWidget overview](../README.md)
