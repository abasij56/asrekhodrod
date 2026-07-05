# dbo.RolesModulesRelation

**Database:** AsreKhodroBack  
**Rows:** 29  
**Primary key:** Id  
**Group:** Users & security

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| RoleId | uniqueidentifier | YES |  |
| ModuleId | int | NO |  |
| AccessTime | datetime | YES |  |
| ExpireTime | datetime | YES |  |
| Description | nvarchar(MAX) | YES |  |

---

[← Back to AsreKhodroBack overview](../README.md)
