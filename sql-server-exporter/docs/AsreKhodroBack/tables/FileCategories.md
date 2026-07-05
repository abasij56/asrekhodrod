# dbo.FileCategories

**Database:** AsreKhodroBack  
**Rows:** 547,809  
**Primary key:** Id  
**Group:** Files / media

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| FileId | int | NO |  |
| CategoryId | int | NO |  |
| IsMain | bit | NO |  |
| StatusId | tinyint | NO |  |

## Logical relationships (within AsreKhodroBack)

- `FileId` → `FileInitialize.Id` — File categories

---

[← Back to AsreKhodroBack overview](../README.md)
