# dbo.Menu

**Database:** AsreKhodroFront  
**Rows:** 19  
**Primary key:** Id  
**Group:** Taxonomy & navigation

## WordPress migration note

Site navigation → WordPress menus.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| SiteComponentId | int | YES |  |
| DomainId | int | NO |  |
| ParentId | int | YES |  |
| ParentTitle | nvarchar(256) | YES |  |
| Title | nvarchar(256) | NO |  |
| Name | nvarchar(256) | NO |  |
| IconURL | nvarchar(512) | YES |  |
| PageURL | nvarchar(512) | NO |  |
| AlternativeURL | nvarchar(512) | YES |  |
| MenuPositionId | int | NO |  |
| Params | nvarchar(1024) | YES |  |
| Periority | bigint | NO |  |
| LinkTargetTitle | nvarchar(20) | NO |  |

## Logical relationships (within AsreKhodroFront)

- `ParentId` → `Menu.Id` — Menu hierarchy

---

[← Back to AsreKhodroFront overview](../README.md)
