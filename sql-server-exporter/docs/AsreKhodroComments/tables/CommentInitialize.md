# dbo.CommentInitialize

**Database:** AsreKhodroComments  
**Rows:** 6,808  
**Primary key:** Id  
**Group:** Comments

## WordPress migration note

Comment header: `ObjectId` = article ContentId, `UserId` → Comments.Users.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| ParentId | int | YES |  |
| SiteComponentId | int | NO |  |
| ObjectId | int | NO |  |
| UserId | int | YES |  |
| StatusId | int | NO |  |
| CreateTime | datetime | NO |  |

## Logical relationships (within AsreKhodroComments)

- Referenced by `CommentCommonInfo.CommentId` — Comment text
- Referenced by `CommentPrivateInfo.CommentId` — Comment moderation
- `UserId` → `Users.Id` — Comment author
- `ParentId` → `CommentInitialize.Id` — Reply thread
- `ObjectId` → `ContentInitialize.Id` — Cross-DB: article in AsreKhodroBack (ContentId)

---

[← Back to AsreKhodroComments overview](../README.md)
