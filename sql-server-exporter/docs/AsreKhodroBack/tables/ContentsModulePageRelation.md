# dbo.ContentsModulePageRelation

**Database:** AsreKhodroBack  
**Rows:** 456,551  
**Primary key:** Id  
**Group:** Content (split model)

## WordPress migration note

Content placement in modules/pages.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| ContentId | int | NO |  |
| ModuleId | int | NO |  |
| CategoryId | int | YES |  |
| PageId | int | NO |  |
| LinkTargetId | tinyint | NO |  |
| Periority | bigint | NO |  |
| StatusId | tinyint | NO |  |

## Logical relationships (within AsreKhodroBack)

- `ContentId` → `ContentInitialize.Id` — Content placement

---

[← Back to AsreKhodroBack overview](../README.md)
