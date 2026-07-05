# AsreKhodroComments

**Role:** User comments on site content  

Comments linked to articles via `ObjectId` (matches `ContentId` in Back/Front). Has its own commenter `Users` table.

**WordPress priority:** Export comments after posts; map `ObjectId` → WordPress post ID.

**Exported:** 2026-06-16T14:15:44.100Z  
**Tables:** 7  
**Total rows:** 27,260  
**Declared foreign keys:** 0

## Important notes

- `ObjectId` on comments = `ContentId` in AsreKhodroBack (verified: 6807/6808 match).
- Comment `Users` are site commenters — not the same as Back admin `Users`.
- Export comments **after** posts, keeping ObjectId → post ID mapping.

## Entity groups

### Comments

- [CommentInitialize](./tables/CommentInitialize.md) — 6,808 rows
- [CommentCommonInfo](./tables/CommentCommonInfo.md) — 6,808 rows
- [CommentPrivateInfo](./tables/CommentPrivateInfo.md) — 6,807 rows
- [Opinions](./tables/Opinions.md) — 0 rows

### Users

- [Users](./tables/Users.md) — 6,803 rows

### Security

- [CommentCategoryRolesRelation](./tables/CommentCategoryRolesRelation.md) — 29 rows

### Reference data

- [Status](./tables/Status.md) — 5 rows

## All tables

| Table | Rows | Group | WordPress hint |
|-------|------|-------|----------------|
| [CommentCategoryRolesRelation](./tables/CommentCategoryRolesRelation.md) | 29 | Security | — |
| [CommentCommonInfo](./tables/CommentCommonInfo.md) | 6,808 | Comments | Comment text keyed by `CommentId` |
| [CommentInitialize](./tables/CommentInitialize.md) | 6,808 | Comments | Comment header: `ObjectId` = article ContentId, `UserId` → Comments |
| [CommentPrivateInfo](./tables/CommentPrivateInfo.md) | 6,807 | Comments | IP, session, publisher moderation data |
| [Opinions](./tables/Opinions.md) | 0 | Comments | Empty opinions table — reserved or unused |
| [Status](./tables/Status.md) | 5 | Reference data | — |
| [Users](./tables/Users.md) | 6,803 | Users | Commenter accounts (separate from Back admin users) |

## Relationships

- [Within-database relationships](./relationships.md)
- [Cross-database map](../cross-database-relationships.md)

---

[← Back to all databases](../README.md)
