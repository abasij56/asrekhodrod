# dbo.Departments

**Database:** AsreKhodroFront  
**Rows:** 1  
**Primary key:** Id  
**Group:** Other

## WordPress migration note

Contact departments → static pages or CPT.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| Title | nvarchar(50) | NO |  |
| FullName | nvarchar(256) | YES |  |
| Email | nvarchar(512) | NO |  |
| Phone | nvarchar(50) | YES |  |
| Address | nvarchar(1024) | YES |  |
| Description | nvarchar(1024) | YES |  |
| CreateTime | datetime | NO |  |

---

[← Back to AsreKhodroFront overview](../README.md)
