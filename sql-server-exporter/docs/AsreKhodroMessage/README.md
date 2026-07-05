# AsreKhodroMessage

**Role:** Contacts, newsletter, and internal messaging  

Subscriber contacts, newsletter templates, and message queue. Largely independent from content tables.

**WordPress priority:** Map contacts to newsletter plugin; messages are operational data.

**Exported:** 2026-06-16T14:15:46.231Z  
**Tables:** 9  
**Total rows:** 8,685  
**Declared foreign keys:** 3

## Important notes

- Standalone subscriber/contact system — no ContentId links to articles.
- `Contacts` (~7K) are newsletter subscribers.
- `NewsLetterTemplates` hold HTML email layouts.

## Entity groups

### Contacts & newsletter

- [Contacts](./tables/Contacts.md) — 7,209 rows
- [ContactsGroup](./tables/ContactsGroup.md) — 0 rows
- [ContactsInGroups](./tables/ContactsInGroups.md) — 0 rows
- [ContactDemands](./tables/ContactDemands.md) — 1,437 rows
- [NewsLetterTemplates](./tables/NewsLetterTemplates.md) — 11 rows

### Messaging

- [Message](./tables/Message.md) — 21 rows
- [MessageDestination](./tables/MessageDestination.md) — 0 rows
- [MessageType](./tables/MessageType.md) — 4 rows

### Reference data

- [Status](./tables/Status.md) — 3 rows

## All tables

| Table | Rows | Group | WordPress hint |
|-------|------|-------|----------------|
| [ContactDemands](./tables/ContactDemands.md) | 1,437 | Contacts & newsletter | Subscription preferences (content type, categories) |
| [Contacts](./tables/Contacts.md) | 7,209 | Contacts & newsletter | Newsletter/subscriber contacts → MailPoet or similar |
| [ContactsGroup](./tables/ContactsGroup.md) | 0 | Contacts & newsletter | Contact segmentation groups |
| [ContactsInGroups](./tables/ContactsInGroups.md) | 0 | Contacts & newsletter | — |
| [Message](./tables/Message.md) | 21 | Messaging | Internal messaging queue |
| [MessageDestination](./tables/MessageDestination.md) | 0 | Messaging | — |
| [MessageType](./tables/MessageType.md) | 4 | Messaging | — |
| [NewsLetterTemplates](./tables/NewsLetterTemplates.md) | 11 | Contacts & newsletter | Email HTML templates |
| [Status](./tables/Status.md) | 3 | Reference data | — |

## Relationships

- [Within-database relationships](./relationships.md)
- [Cross-database map](../cross-database-relationships.md)

---

[← Back to all databases](../README.md)
