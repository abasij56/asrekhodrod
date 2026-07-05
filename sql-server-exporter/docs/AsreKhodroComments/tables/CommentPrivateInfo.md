# dbo.CommentPrivateInfo

**Database:** AsreKhodroComments  
**Rows:** 6,807  
**Primary key:** Id  
**Group:** Comments

## WordPress migration note

IP, session, publisher moderation data.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| CommentId | int | NO |  |
| ObjectId | int | NO |  |
| UserIPAddress | nvarchar(50) | NO |  |
| UserMACAddress | nvarchar(50) | YES |  |
| UserInformation | nvarchar(MAX) | YES |  |
| UserSessionId | nvarchar(128) | YES |  |
| PublishTime | datetime | YES |  |
| PublisherUserId | uniqueidentifier | YES |  |
| CommentReader | uniqueidentifier | YES |  |

## Logical relationships (within AsreKhodroComments)

- `CommentId` → `CommentInitialize.Id` — Comment moderation

---

[← Back to AsreKhodroComments overview](../README.md)
