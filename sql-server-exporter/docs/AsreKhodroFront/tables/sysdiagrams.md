# dbo.sysdiagrams

**Database:** AsreKhodroFront  
**Rows:** 0  
**Primary key:** diagram_id  
**Group:** Other

## WordPress migration note

SQL Server internal — ignore.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| name | nvarchar(128) | NO |  |
| principal_id | int | NO |  |
| diagram_id | int | NO | PK |
| version | int | YES |  |
| definition | varbinary(MAX) | YES |  |

---

[← Back to AsreKhodroFront overview](../README.md)
