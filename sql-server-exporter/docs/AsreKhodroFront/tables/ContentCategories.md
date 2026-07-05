# dbo.ContentCategories

**Database:** AsreKhodroFront  
**Rows:** 521,431  
**Primary key:** Id  
**Group:** Core content

## WordPress migration note

Post–category links (published copy).

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| ContentId | int | NO |  |
| CategoryId | int | NO |  |
| IsMain | bit | NO |  |
| Id | int | NO | PK |

## Logical relationships (within AsreKhodroFront)

- `ContentId` → `SingleContent.ContentId` — Content belongs to categories
- `CategoryId` → `Categories.Id` — Category assignment

## Cross-database links

- Referenced from **AsreKhodroBack**.ContentCategories.ContentId — Post–category links replicated to Front (row counts differ slightly). *(verified: logical copy)*

---

[← Back to AsreKhodroFront overview](../README.md)
