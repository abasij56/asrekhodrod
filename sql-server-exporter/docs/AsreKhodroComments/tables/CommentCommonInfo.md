# dbo.CommentCommonInfo

**Database:** AsreKhodroComments  
**Rows:** 6,808  
**Primary key:** Id  
**Group:** Comments

## WordPress migration note

Comment text keyed by `CommentId`.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| CommentId | int | NO |  |
| ObjectId | int | NO |  |
| Message | nvarchar(1024) | NO |  |

## Logical relationships (within AsreKhodroComments)

- `CommentId` → `CommentInitialize.Id` — Comment text

## Cross-database links

- Referenced from **AsreKhodroBack**.ContentCommonInfo.ContentId — Comments attach to articles via ObjectId = ContentId. *(verified: 6807 / 6808 comments matched)*

---

[← Back to AsreKhodroComments overview](../README.md)
