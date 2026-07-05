# dbo.ContentCategories

**Database:** AsreKhodroBack  
**Rows:** 336,788  
**Primary key:** Id  
**Group:** Content (split model)

## WordPress migration note

Many-to-many content ↔ categories.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| ContentId | int | NO |  |
| CategoryId | int | NO |  |
| IsMain | bit | NO |  |
| StatusId | tinyint | NO |  |

## Logical relationships (within AsreKhodroBack)

- `ContentId` → `ContentInitialize.Id` — Category assignment
- `CategoryId` → `Categories.Id` — Category link

## Cross-database links

- `ContentId` → **AsreKhodroFront**.ContentCategories.ContentId — Post–category links replicated to Front (row counts differ slightly). *(verified: logical copy)*

---

[← Back to AsreKhodroBack overview](../README.md)
