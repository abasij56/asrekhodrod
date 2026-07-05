# dbo.FilePrivateInfo

**Database:** AsreKhodroBack  
**Rows:** 287,559  
**Primary key:** Id  
**Group:** Files / media

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| FileId | int | NO |  |
| CreateTime | datetime | NO |  |
| LastModifyTime | datetime | YES |  |
| Owner | uniqueidentifier | YES |  |
| LastUser | uniqueidentifier | YES |  |
| CurrentUser | uniqueidentifier | YES |  |
| EditingUser | uniqueidentifier | YES |  |
| ApproverUser | uniqueidentifier | YES |  |
| LastModifyerUser | uniqueidentifier | YES |  |
| Description | nvarchar(1024) | YES |  |

## Logical relationships (within AsreKhodroBack)

- `FileId` → `FileInitialize.Id` — File workflow

---

[← Back to AsreKhodroBack overview](../README.md)
