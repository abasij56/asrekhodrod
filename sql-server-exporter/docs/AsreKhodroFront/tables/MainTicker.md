# dbo.MainTicker

**Database:** AsreKhodroFront  
**Rows:** 5  
**Primary key:** RowId, ContentId  
**Group:** Homepage / featured caches

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| RowId | int | NO | PK |
| ContentId | int | NO | PK |
| DomainId | int | NO |  |
| SiteComponentId | int | YES |  |
| OverTitle | nvarchar(1024) | YES |  |
| Title | nvarchar(MAX) | NO |  |
| UnderTitle | nvarchar(1024) | YES |  |
| Params | nvarchar(256) | YES |  |
| Tags | nvarchar(MAX) | YES |  |
| PublishTime | datetime | NO |  |
| PageURL | nvarchar(512) | YES |  |
| Periority | bigint | YES |  |

---

[← Back to AsreKhodroFront overview](../README.md)
