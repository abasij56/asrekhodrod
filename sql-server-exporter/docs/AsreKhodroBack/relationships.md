# AsreKhodroBack — relationships

## Declared foreign keys (4)

| From | Column | To | Column | Constraint |
|------|--------|----|--------|------------|
| AspNetUserClaims | UserId | AspNetUsers | Id | FK_dbo.AspNetUserClaims_dbo.AspNetUsers_UserId |
| AspNetUserLogins | UserId | AspNetUsers | Id | FK_dbo.AspNetUserLogins_dbo.AspNetUsers_UserId |
| AspNetUserRoles | RoleId | AspNetRoles | Id | FK_dbo.AspNetUserRoles_dbo.AspNetRoles_RoleId |
| AspNetUserRoles | UserId | AspNetUsers | Id | FK_dbo.AspNetUserRoles_dbo.AspNetUsers_UserId |

## Logical relationships

| From | Column | To | Column | Notes |
|------|--------|----|--------|-------|
| ContentCommonInfo | ContentId | ContentInitialize | Id | Body/metadata → content header |
| ContentPrivateInfo | ContentId | ContentInitialize | Id | Workflow → content header |
| ContentCategories | ContentId | ContentInitialize | Id | Category assignment |
| ContentCategories | CategoryId | Categories | Id | Category link |
| ContentFiles | ContentId | ContentInitialize | Id | Content media link |
| ContentFiles | FileId | FileInitialize | Id | File reference |
| FileCommonInfo | FileId | FileInitialize | Id | File metadata |
| FilePrivateInfo | FileId | FileInitialize | Id | File workflow |
| FilesFiletypes | FileId | FileInitialize | Id | File variants |
| FileCategories | FileId | FileInitialize | Id | File categories |
| KeywordsContent | ContentId | ContentInitialize | Id | Keywords on content |
| ContentRelation | ParentContentId | ContentInitialize | Id | Related content |
| Categories | ParentId | Categories | Id | Category tree |
| CategoriesRolesRelation | CategoryId | Categories | Id | Category permissions |
| ContentInitialize | DomainId | Domain | Id | Content language/domain |
| Profiles | UserId | Users | UserId | User profile |
| Memberships | UserId | Users | UserId | Membership |
| UsersInRoles | UserId | Users | UserId | Role assignment |
| ContentsModulePageRelation | ContentId | ContentInitialize | Id | Content placement |

## Cross-database links

| Direction | Link | Verified |
|-----------|------|----------|
| → AsreKhodroFront | ContentCommonInfo.ContentId → SingleContent.ContentId | 197643 matching rows |
| → AsreKhodroFront | Categories.Id → Categories.Id | 99 matching rows |
| → AsreKhodroComments | ContentCommonInfo.ContentId → CommentCommonInfo.ObjectId | 6807 / 6808 comments matched |
| → AsreKhodroFront | ContentCategories.ContentId → ContentCategories.ContentId | logical copy |
| → AsreKhodroFront | ContentFiles.ContentId → ContentFiles.ContentId | logical copy |
| → AsreKhodroFront | KeywordsContent.ContentId → KeywordsContent.ContentId | logical copy |
| → AsrekhodroWidget | Domain.Id → FileInitialize.DomainId | DomainId=1 in Widget |
| → AsreKhodroBack | ContentInitialize.Id → ContentCommonInfo.ContentId | same database |

---

[← Back to AsreKhodroBack overview](./README.md)
