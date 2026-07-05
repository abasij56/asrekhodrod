# dbo.sysdiagrams

**Database:** AsreKhodroBack  
**Rows:** 2  
**Primary key:** diagram_id  
**Group:** Other

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| name | nvarchar(128) | NO |  |
| principal_id | int | NO |  |
| diagram_id | int | NO | PK |
| version | int | YES |  |
| definition | varbinary(MAX) | YES |  |

---

[← Back to AsreKhodroBack overview](../README.md)
