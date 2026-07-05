# AsreKhodroComments — relationships

> No SQL foreign keys declared in this database.

## Logical relationships

| From | Column | To | Column | Notes |
|------|--------|----|--------|-------|
| CommentCommonInfo | CommentId | CommentInitialize | Id | Comment text |
| CommentPrivateInfo | CommentId | CommentInitialize | Id | Comment moderation |
| CommentInitialize | UserId | Users | Id | Comment author |
| CommentInitialize | ParentId | CommentInitialize | Id | Reply thread |
| CommentInitialize | ObjectId | ContentInitialize | Id | Cross-DB: article in AsreKhodroBack (ContentId) |

## Cross-database links

| Direction | Link | Verified |
|-----------|------|----------|
| ← AsreKhodroBack | ContentCommonInfo.ContentId → CommentCommonInfo.ObjectId | 6807 / 6808 comments matched |

---

[← Back to AsreKhodroComments overview](./README.md)
