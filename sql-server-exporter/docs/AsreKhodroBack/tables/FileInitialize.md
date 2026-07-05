# dbo.FileInitialize

**Database:** AsreKhodroBack  
**Rows:** 287,559  
**Primary key:** Id  
**Group:** Files / media

## WordPress migration note

File record header (domain, status).

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

## Logical relationships (within AsreKhodroBack)

- Referenced by `ContentFiles.FileId` — File reference
- Referenced by `FileCommonInfo.FileId` — File metadata
- Referenced by `FilePrivateInfo.FileId` — File workflow
- Referenced by `FilesFiletypes.FileId` — File variants
- Referenced by `FileCategories.FileId` — File categories

---

[← Back to AsreKhodroBack overview](../README.md)
