# dbo.FilesFiletypes

**Database:** AsreKhodroBack  
**Rows:** 839,678  
**Primary key:** Id  
**Group:** Files / media

## WordPress migration note

File variants (sizes/types) — important for images.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| FileId | int | NO |  |
| FileTypeId | int | NO |  |
| FileLength | nvarchar(50) | YES |  |
| FileSize | nvarchar(50) | YES |  |
| ImageDimensionId | int | YES |  |
| Url | nvarchar(512) | NO |  |

## Logical relationships (within AsreKhodroBack)

- `FileId` → `FileInitialize.Id` — File variants

---

[← Back to AsreKhodroBack overview](../README.md)
