# dbo.WidgetParametersRelation

**Database:** AsrekhodroWidget  
**Rows:** 1,148  
**Primary key:** Id  
**Group:** Widgets

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| WidgetId | int | NO |  |
| ParameterId | int | NO |  |
| Value | nvarchar(MAX) | YES |  |
| CreateTime | datetime | NO |  |
| Description | nvarchar(MAX) | YES |  |

## Logical relationships (within AsrekhodroWidget)

- `WidgetId` → `WidgetInitialize.Id` — Widget parameters

---

[← Back to AsrekhodroWidget overview](../README.md)
