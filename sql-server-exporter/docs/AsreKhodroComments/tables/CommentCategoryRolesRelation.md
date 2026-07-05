# dbo.CommentCategoryRolesRelation

**Database:** AsreKhodroComments  
**Rows:** 29  
**Primary key:** CategoryId, RoleId  
**Group:** Security

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| CategoryId | int | NO | PK |
| RoleId | uniqueidentifier | NO | PK |
| AccessTime | datetime | NO |  |
| ExpireTime | datetime | NO |  |
| StatusId | tinyint | NO |  |

---

[← Back to AsreKhodroComments overview](../README.md)
