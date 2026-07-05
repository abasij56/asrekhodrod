# dbo.ContentRelation

**Database:** AsreKhodroBack  
**Rows:** 1,083  
**Primary key:** ParentContentId, ChildContentId  
**Group:** Content (split model)

## WordPress migration note

Related content links (Back spelling).

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| ParentContentId | int | NO | PK |
| ChildContentId | int | NO | PK |
| IsActive | bit | NO |  |

## Logical relationships (within AsreKhodroBack)

- `ParentContentId` → `ContentInitialize.Id` — Related content

---

[← Back to AsreKhodroBack overview](../README.md)
