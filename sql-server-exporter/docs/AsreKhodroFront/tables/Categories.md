# dbo.Categories

**Database:** AsreKhodroFront  
**Rows:** 99  
**Primary key:** Id  
**Group:** Taxonomy & navigation

## WordPress migration note

Category tree (published copy).

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| DomainId | int | NO |  |
| ParentId | int | YES |  |
| SiteComponentId | int | NO |  |
| Periority | int | NO |  |
| Title | nvarchar(256) | NO |  |
| StatusId | tinyint | NO |  |
| ImageId | int | YES |  |
| Params | nvarchar(256) | YES |  |
| Description | nvarchar(1024) | YES |  |

## Logical relationships (within AsreKhodroFront)

- Referenced by `ContentCategories.CategoryId` — Category assignment
- `ParentId` → `Categories.Id` — Category hierarchy
- Referenced by `Advertisements.CategoryId` — Optional ad category

## Cross-database links

- Referenced from **AsreKhodroBack**.Categories.Id — Category tree copied to Front (99 categories, IDs match). *(verified: 99 matching rows)*

---

[← Back to AsreKhodroFront overview](../README.md)
