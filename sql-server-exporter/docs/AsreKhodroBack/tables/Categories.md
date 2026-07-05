# dbo.Categories

**Database:** AsreKhodroBack  
**Rows:** 99  
**Primary key:** Id  
**Group:** Taxonomy & keywords

## WordPress migration note

Master category tree (`ParentId`).

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

## Logical relationships (within AsreKhodroBack)

- Referenced by `ContentCategories.CategoryId` — Category link
- `ParentId` → `Categories.Id` — Category tree
- Referenced by `CategoriesRolesRelation.CategoryId` — Category permissions

## Cross-database links

- `Id` → **AsreKhodroFront**.Categories.Id — Category tree copied to Front (99 categories, IDs match). *(verified: 99 matching rows)*

---

[← Back to AsreKhodroBack overview](../README.md)
