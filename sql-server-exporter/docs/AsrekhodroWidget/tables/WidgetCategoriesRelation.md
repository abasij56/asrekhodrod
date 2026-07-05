# dbo.WidgetCategoriesRelation

**Database:** AsrekhodroWidget  
**Rows:** 488  
**Primary key:** Id  
**Group:** Widgets

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| WidgetId | int | NO |  |
| CategoryId | int | NO |  |
| IsMain | bit | NO |  |
| StatusId | tinyint | NO |  |

## Logical relationships (within AsrekhodroWidget)

- `WidgetId` → `WidgetInitialize.Id` — Widget categories
- `CategoryId` → `WidgetCategories.Id` — Category link

---

[← Back to AsrekhodroWidget overview](../README.md)
