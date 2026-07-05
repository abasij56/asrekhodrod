# dbo.ContentsRelation

**Database:** AsreKhodroFront  
**Rows:** 1,041  
**Primary key:** ParentContentId, ChildContentId  
**Group:** Core content

## WordPress migration note

Related articles (parent/child).

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| ParentContentId | int | NO | PK |
| ParentContentTitle | nvarchar(1024) | YES |  |
| ChildContentId | int | NO | PK |
| ChildContentTitle | nvarchar(1024) | YES |  |
| IsActive | bit | NO |  |

## Logical relationships (within AsreKhodroFront)

- `ParentContentId` → `SingleContent.ContentId` — Related content (parent)
- `ChildContentId` → `SingleContent.ContentId` — Related content (child)

---

[← Back to AsreKhodroFront overview](../README.md)
