# dbo.WidgetPrivateInfo

**Database:** AsrekhodroWidget  
**Rows:** 274  
**Primary key:** Id  
**Group:** Widgets

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| WidgetId | int | NO |  |
| CreateTime | datetime | NO |  |
| LastModifyTime | datetime | YES |  |
| Owner | uniqueidentifier | NO |  |
| BodyCharCount | int | NO |  |
| LastUser | uniqueidentifier | NO |  |
| CurrentUser | uniqueidentifier | YES |  |
| SenderUser | uniqueidentifier | YES |  |
| EditingUser | uniqueidentifier | YES |  |
| PublisherUser | uniqueidentifier | YES |  |
| LastModifyerUser | uniqueidentifier | NO |  |

## Logical relationships (within AsrekhodroWidget)

- `WidgetId` → `WidgetInitialize.Id` — Widget workflow

---

[← Back to AsrekhodroWidget overview](../README.md)
