# dbo.WidgetParameters

**Database:** AsrekhodroWidget  
**Rows:** 3  
**Primary key:** Id  
**Group:** Widgets

## WordPress migration note

Configurable widget parameters.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| WidgetComponentId | int | NO |  |
| WidgetId | int | YES |  |
| Title | nvarchar(1024) | NO |  |
| VariableTypeId | tinyint | NO |  |
| DefaultValue | nvarchar(MAX) | YES |  |
| Description | nvarchar(MAX) | YES |  |

---

[← Back to AsrekhodroWidget overview](../README.md)
