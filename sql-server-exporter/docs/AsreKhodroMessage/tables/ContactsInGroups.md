# dbo.ContactsInGroups

**Database:** AsreKhodroMessage  
**Rows:** 0  
**Primary key:** ContactId, GroupId  
**Group:** Contacts & newsletter

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| ContactId | int | NO | PK |
| GroupId | int | NO | PK |
| CreationDate | datetime | NO |  |

## Logical relationships (within AsreKhodroMessage)

- `ContactId` → `Contacts.Id` — Group membership

---

[← Back to AsreKhodroMessage overview](../README.md)
