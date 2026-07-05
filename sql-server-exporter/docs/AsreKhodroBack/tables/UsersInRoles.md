# dbo.UsersInRoles

**Database:** AsreKhodroBack  
**Rows:** 28  
**Primary key:** UserId, RoleId  
**Group:** Users & security

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| UserId | uniqueidentifier | NO | PK |
| RoleId | uniqueidentifier | NO | PK |

## Logical relationships (within AsreKhodroBack)

- `UserId` → `Users.UserId` — Role assignment

---

[← Back to AsreKhodroBack overview](../README.md)
