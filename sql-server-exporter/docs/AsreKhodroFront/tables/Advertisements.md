# dbo.Advertisements

**Database:** AsreKhodroFront  
**Rows:** 765  
**Primary key:** Id  
**Group:** Monetization

## WordPress migration note

Banner ads → custom post type or ad plugin.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| SiteComponentId | int | NO |  |
| DomainId | int | NO |  |
| FileURL | nvarchar(MAX) | NO |  |
| Title | nvarchar(1024) | NO |  |
| CategoryId | int | YES |  |
| PositionId | tinyint | NO |  |
| Width | int | YES |  |
| Height | int | YES |  |
| Periority | nvarchar(50) | NO |  |
| Link | nvarchar(MAX) | YES |  |
| CreateTime | datetime | NO |  |
| isActive | bit | YES |  |

## Logical relationships (within AsreKhodroFront)

- `CategoryId` → `Categories.Id` — Optional ad category

---

[← Back to AsreKhodroFront overview](../README.md)
