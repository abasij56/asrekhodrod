# dbo.FilePrivateInfo

**Database:** AsrekhodroWidget  
**Rows:** 244  
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

---

[← Back to AsrekhodroWidget overview](../README.md)
