# dbo.WidgetCommonInfo

**Database:** AsrekhodroWidget  
**Rows:** 274  
**Primary key:** Id  
**Group:** Widgets

## WordPress migration note

Widget display content and timing.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| WidgetId | int | NO |  |
| Author | nvarchar(256) | YES |  |
| ContentTime | datetime | YES |  |
| PublishTime | datetime | YES |  |
| ExpireTime | datetime | YES |  |
| OverTitle | nvarchar(1024) | YES |  |
| UnderTitle | nvarchar(1024) | YES |  |
| ShortBody | nvarchar(2048) | YES |  |
| BodyText | nvarchar(MAX) | YES |  |
| Footer | nvarchar(2048) | YES |  |

## Logical relationships (within AsrekhodroWidget)

- `WidgetId` → `WidgetInitialize.Id` — Widget content

---

[← Back to AsrekhodroWidget overview](../README.md)
