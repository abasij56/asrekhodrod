# AsreKhodroMessage — relationships

## Declared foreign keys (3)

| From | Column | To | Column | Constraint |
|------|--------|----|--------|------------|
| ContactsInGroups | ContactId | Contacts | Id | FK_ContactsInGroups_Contacts |
| ContactsInGroups | GroupId | ContactsGroup | Id | FK_ContactsInGroups_ContactsGroup |
| Message | MessageTypeId | MessageType | Id | FK_Message_MessageType |

## Logical relationships

| From | Column | To | Column | Notes |
|------|--------|----|--------|-------|
| ContactsInGroups | ContactId | Contacts | Id | Group membership |
| ContactDemands | ContactId | Contacts | Id | Subscription preferences |
| Message | MessageTypeId | MessageType | Id | Message classification |

---

[← Back to AsreKhodroMessage overview](./README.md)
