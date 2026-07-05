# dbo.WidgetFiles

**Database:** AsrekhodroWidget  
**Rows:** 249  
**Primary key:** Id  
**Group:** Widgets

## WordPress migration note

Media attached to widgets.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| WidgetId | int | NO |  |
| FileId | int | NO |  |
| FileTypeId | int | NO |  |
| IsMain | bit | NO |  |
| StatusId | tinyint | NO |  |
| Periority | int | NO |  |
| Title | nvarchar(1024) | YES |  |

## Logical relationships (within AsrekhodroWidget)

- `WidgetId` → `WidgetInitialize.Id` — Widget media
- `FileId` → `FileInitialize.Id` — File link

---

[← Back to AsrekhodroWidget overview](../README.md)
