# dbo.Profiles

**Database:** AsreKhodroBack  
**Rows:** 28  
**Primary key:** UserId  
**Group:** Users & security

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| UserId | uniqueidentifier | NO | PK |
| PropertyNames | nvarchar(4000) | NO |  |
| PropertyValueStrings | nvarchar(4000) | NO |  |
| PropertyValueBinary | image | NO |  |
| LastUpdatedDate | datetime | NO |  |

## Logical relationships (within AsreKhodroBack)

- `UserId` → `Users.UserId` — User profile

---

[← Back to AsreKhodroBack overview](../README.md)
