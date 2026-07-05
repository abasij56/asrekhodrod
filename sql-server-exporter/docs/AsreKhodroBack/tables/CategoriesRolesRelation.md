# dbo.CategoriesRolesRelation

**Database:** AsreKhodroBack  
**Rows:** 105  
**Primary key:** CategoryId, RoleId  
**Group:** Users & security

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| CategoryId | int | NO | PK |
| RoleId | uniqueidentifier | NO | PK |
| AccessTime | datetime | NO |  |
| ExpireTime | datetime | NO |  |
| StatusId | tinyint | NO |  |

## Logical relationships (within AsreKhodroBack)

- `CategoryId` → `Categories.Id` — Category permissions

---

[← Back to AsreKhodroBack overview](../README.md)
