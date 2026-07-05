# dbo.Module

**Database:** AsreKhodroBack  
**Rows:** 91  
**Primary key:** Id  
**Group:** Site structure

## WordPress migration note

CMS modules tied to site components.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| SiteComponentId | int | YES |  |
| ParentId | int | YES |  |
| Title | nvarchar(256) | NO |  |
| Name | nvarchar(256) | YES |  |
| HTML | nvarchar(MAX) | YES |  |
| TempHTML | nvarchar(MAX) | YES |  |
| Parameters | nvarchar(MAX) | YES |  |
| jsFile | nvarchar(512) | YES |  |
| cssFile | nvarchar(512) | YES |  |
| isDynamic | bit | NO |  |
| isFrontend | bit | NO |  |
| ShowOnPublishPage | bit | NO |  |
| ShowOnRoleManager | bit | NO |  |
| Periority | int | NO |  |
| StatusId | tinyint | NO |  |
| Description | nvarchar(MAX) | YES |  |

---

[← Back to AsreKhodroBack overview](../README.md)
