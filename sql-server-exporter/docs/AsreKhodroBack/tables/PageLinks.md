# dbo.PageLinks

**Database:** AsreKhodroBack  
**Rows:** 114  
**Primary key:** Id  
**Group:** Site structure

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| DomainId | int | NO |  |
| SiteComponentId | int | YES |  |
| ParentId | int | YES |  |
| Title | nvarchar(256) | NO |  |
| Name | nvarchar(256) | NO |  |
| IconURL | nvarchar(512) | YES |  |
| PageId | int | YES |  |
| AlternativeURL | nvarchar(512) | YES |  |
| MenuPositionId | tinyint | YES |  |
| Params | nvarchar(1024) | YES |  |
| IsBackend | bit | NO |  |
| Periority | int | NO |  |
| LinkTargetId | tinyint | NO |  |
| StatusId | tinyint | NO |  |

---

[← Back to AsreKhodroBack overview](../README.md)
