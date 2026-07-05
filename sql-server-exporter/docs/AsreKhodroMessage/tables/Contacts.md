# dbo.Contacts

**Database:** AsreKhodroMessage  
**Rows:** 7,209  
**Primary key:** Id  
**Group:** Contacts & newsletter

## WordPress migration note

Newsletter/subscriber contacts → MailPoet or similar.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| Name | nvarchar(50) | YES |  |
| Family | nvarchar(100) | YES |  |
| NameFamily | nvarchar(255) | YES |  |
| Birthdate | datetime | YES |  |
| Email | nvarchar(50) | YES |  |
| Mobile | nvarchar(50) | YES |  |
| Phone | nvarchar(50) | YES |  |
| Address | nvarchar(1024) | YES |  |
| IPAddress | nvarchar(50) | YES |  |
| Description | nvarchar(1024) | YES |  |
| ConfirmationSendDate | datetime | YES |  |
| CreationDate | datetime | NO |  |
| UnsubscribeDate | datetime | YES |  |
| Confirmed | bit | YES |  |

## Logical relationships (within AsreKhodroMessage)

- Referenced by `ContactsInGroups.ContactId` — Group membership
- Referenced by `ContactDemands.ContactId` — Subscription preferences

---

[← Back to AsreKhodroMessage overview](../README.md)
