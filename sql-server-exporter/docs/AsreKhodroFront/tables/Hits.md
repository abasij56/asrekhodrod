# dbo.Hits

**Database:** AsreKhodroFront  
**Rows:** 308,992,740  
**Primary key:** Id  
**Group:** Analytics

## WordPress migration note

Page-view log (~300M rows) — skip for migration.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| SiteComponentId | int | NO |  |
| ItemId | int | NO |  |
| UserIP | nvarchar(50) | YES |  |
| URL | nvarchar(512) | YES |  |
| HitDate | datetime | NO |  |

## Logical relationships (within AsreKhodroFront)

- `ItemId` → `SingleContent.ContentId` — Content view tracking

---

[← Back to AsreKhodroFront overview](../README.md)
