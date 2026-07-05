# dbo.MediaMirror

**Database:** AsreKhodroBack  
**Rows:** 0  
**Primary key:** Id  
**Group:** Other

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| DomainId | int | NO |  |
| SiteComponentId | int | NO |  |
| Title | nvarchar(1024) | NO |  |
| CategoryId | int | YES |  |
| LinkTargetId | tinyint | NO |  |
| DateTime | datetime | YES |  |
| PersianDateLinked | nvarchar(50) | YES |  |
| LinkURL | nvarchar(1024) | YES |  |
| Description | nvarchar(MAX) | YES |  |

---

[← Back to AsreKhodroBack overview](../README.md)
