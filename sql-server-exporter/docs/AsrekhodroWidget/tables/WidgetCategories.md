# dbo.WidgetCategories

**Database:** AsrekhodroWidget  
**Rows:** 320  
**Primary key:** Id  
**Group:** Widgets

## WordPress migration note

Widget category tree.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| WidgetComponentId | int | NO |  |
| ParentId | int | YES |  |
| Periority | int | NO |  |
| Title | nvarchar(256) | NO |  |
| StatusId | tinyint | NO |  |
| ImageId | int | YES |  |
| Params | nvarchar(256) | YES |  |
| Description | nvarchar(1024) | YES |  |

## Logical relationships (within AsrekhodroWidget)

- Referenced by `WidgetCategoriesRelation.CategoryId` — Category link
- `ParentId` → `WidgetCategories.Id` — Category tree

---

[← Back to AsrekhodroWidget overview](../README.md)
