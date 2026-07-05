# dbo.Advertisments

**Database:** AsreKhodroBack  
**Rows:** 1,078  
**Primary key:** Id  
**Group:** Other

## WordPress migration note

Admin-side ad definitions (note spelling).

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| SiteComponentId | int | NO |  |
| DomainId | int | NO |  |
| Title | nvarchar(1024) | NO |  |
| FileId | int | NO |  |
| CategoryId | nvarchar(1024) | YES |  |
| MenuPositionId | tinyint | NO |  |
| Width | int | YES |  |
| Height | int | YES |  |
| Periority | nvarchar(50) | YES |  |
| LinkAddress | nvarchar(MAX) | YES |  |
| LinkTargetId | tinyint | YES |  |
| HTML | nvarchar(MAX) | YES |  |
| Description | nvarchar(1024) | YES |  |
| CreateTime | datetime | NO |  |
| isActive | bit | NO |  |

---

[← Back to AsreKhodroBack overview](../README.md)
