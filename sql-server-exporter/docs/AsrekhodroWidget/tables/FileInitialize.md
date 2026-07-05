# dbo.FileInitialize

**Database:** AsrekhodroWidget  
**Rows:** 244  
**Primary key:** Id  
**Group:** Files / media

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| DomainId | int | NO |  |
| FileName | nvarchar(30) | NO |  |
| Title | nvarchar(256) | NO |  |
| Periority | int | NO |  |
| Params | nvarchar(256) | YES |  |
| RoleId | uniqueidentifier | YES |  |
| StatusId | tinyint | NO |  |

## Logical relationships (within AsrekhodroWidget)

- Referenced by `WidgetFiles.FileId` — File link
- Referenced by `FileCommonInfo.FileId` — File metadata
- `DomainId` → `Domain.Id` — Cross-DB: domain in AsreKhodroBack

## Cross-database links

- Referenced from **AsreKhodroBack**.Domain.Id — Widget files scoped to the same multi-language domains as Back. *(verified: DomainId=1 in Widget)*

---

[← Back to AsrekhodroWidget overview](../README.md)
