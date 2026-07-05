# dbo.ContactDemands

**Database:** AsreKhodroMessage  
**Rows:** 1,437  
**Primary key:** *(none)*  
**Group:** Contacts & newsletter

## WordPress migration note

Subscription preferences (content type, categories).

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| ContactId | int | NO |  |
| ContentType | nvarchar(256) | YES |  |
| Distriction | nvarchar(50) | YES |  |
| Categories | nvarchar(512) | YES |  |
| TimePeriod | int | YES |  |

## Logical relationships (within AsreKhodroMessage)

- `ContactId` → `Contacts.Id` — Subscription preferences

---

[← Back to AsreKhodroMessage overview](../README.md)
