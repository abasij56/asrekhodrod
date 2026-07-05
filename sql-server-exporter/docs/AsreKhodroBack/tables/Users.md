# dbo.Users

**Database:** AsreKhodroBack  
**Rows:** 28  
**Primary key:** UserId  
**Group:** Users & security

## WordPress migration note

Legacy membership users.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| ApplicationId | uniqueidentifier | NO |  |
| UserId | uniqueidentifier | NO | PK |
| UserName | nvarchar(50) | NO |  |
| IsAnonymous | bit | NO |  |
| LastActivityDate | datetime | NO |  |

## Logical relationships (within AsreKhodroBack)

- Referenced by `Profiles.UserId` — User profile
- Referenced by `Memberships.UserId` — Membership
- Referenced by `UsersInRoles.UserId` — Role assignment

---

[← Back to AsreKhodroBack overview](../README.md)
