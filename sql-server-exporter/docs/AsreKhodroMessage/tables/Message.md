# dbo.Message

**Database:** AsreKhodroMessage  
**Rows:** 21  
**Primary key:** Id  
**Group:** Messaging

## WordPress migration note

Internal messaging queue.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| ServerId | nvarchar(MAX) | YES |  |
| MessageTypeId | int | NO |  |
| From | nvarchar(256) | YES |  |
| To | nvarchar(MAX) | YES |  |
| Cc | nvarchar(MAX) | YES |  |
| Bcc | nvarchar(MAX) | YES |  |
| Subject | nvarchar(1024) | YES |  |
| Body | nvarchar(MAX) | YES |  |
| CharCount | int | YES |  |
| Starred | bit | NO |  |
| DateTime | datetime | NO |  |
| Status | tinyint | NO |  |

## Logical relationships (within AsreKhodroMessage)

- `MessageTypeId` → `MessageType.Id` — Message classification

---

[← Back to AsreKhodroMessage overview](../README.md)
