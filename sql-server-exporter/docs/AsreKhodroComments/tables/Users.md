# dbo.Users

**Database:** AsreKhodroComments  
**Rows:** 6,803  
**Primary key:** Id  
**Group:** Users

## WordPress migration note

Commenter accounts (separate from Back admin users).

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| Email | nvarchar(1024) | YES |  |
| AliasName | nvarchar(512) | YES |  |
| MACAddress | nvarchar(1024) | YES |  |
| IsVerified | bit | NO |  |
| CreateTime | datetime | NO |  |

## Logical relationships (within AsreKhodroComments)

- Referenced by `CommentInitialize.UserId` — Comment author

---

[← Back to AsreKhodroComments overview](../README.md)
