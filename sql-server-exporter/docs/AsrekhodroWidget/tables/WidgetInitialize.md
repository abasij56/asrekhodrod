# dbo.WidgetInitialize

**Database:** AsrekhodroWidget  
**Rows:** 274  
**Primary key:** Id  
**Group:** Widgets

## WordPress migration note

Widget record header (component, status).

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| WidgetComponentId | int | NO |  |
| Title | nvarchar(1024) | NO |  |
| Periority | int | YES |  |
| StatusId | tinyint | NO |  |

## Logical relationships (within AsrekhodroWidget)

- Referenced by `WidgetCommonInfo.WidgetId` — Widget content
- Referenced by `WidgetPrivateInfo.WidgetId` — Widget workflow
- Referenced by `WidgetCategoriesRelation.WidgetId` — Widget categories
- Referenced by `WidgetFiles.WidgetId` — Widget media
- Referenced by `WidgetParametersRelation.WidgetId` — Widget parameters

---

[← Back to AsrekhodroWidget overview](../README.md)
