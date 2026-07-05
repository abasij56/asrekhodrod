# AsreKhodroBack

**Role:** Master CMS (admin backend)  

Authoritative source for content, media, categories, users, and site configuration. Content is split across Initialize / CommonInfo / PrivateInfo tables.

**WordPress priority:** Primary export source for posts, media, categories, and users.

**Exported:** 2026-06-16T14:15:43.105Z  
**Tables:** 56  
**Total rows:** 4,321,045  
**Declared foreign keys:** 4

## Important notes

- This is the **master CMS database** — export content from here first.
- Content is split: `ContentInitialize` (header) + `ContentCommonInfo` (body) + `ContentPrivateInfo` (workflow).
- `ContentInitialize.Id` equals `ContentId` used in Front and Comments.
- Files use a parallel split model: Initialize / CommonInfo / PrivateInfo / FilesFiletypes.
- ASP.NET Identity tables (`AspNet*`) hold admin users alongside legacy `Users`.

## Entity groups

### Content (split model)

- [ContentInitialize](./tables/ContentInitialize.md) — 202,335 rows
- [ContentCommonInfo](./tables/ContentCommonInfo.md) — 199,653 rows
- [ContentPrivateInfo](./tables/ContentPrivateInfo.md) — 199,683 rows
- [ContentCategories](./tables/ContentCategories.md) — 336,788 rows
- [ContentFiles](./tables/ContentFiles.md) — 350,650 rows
- [ContentRelation](./tables/ContentRelation.md) — 1,083 rows
- [ContentsModulePageRelation](./tables/ContentsModulePageRelation.md) — 456,551 rows
- [ContentsOffer](./tables/ContentsOffer.md) — 1 rows
- [ContentType](./tables/ContentType.md) — 19 rows
- [ContentClassify](./tables/ContentClassify.md) — 3 rows
- [ContentSource](./tables/ContentSource.md) — 4 rows

### Files / media

- [FileInitialize](./tables/FileInitialize.md) — 287,559 rows
- [FileCommonInfo](./tables/FileCommonInfo.md) — 287,559 rows
- [FilePrivateInfo](./tables/FilePrivateInfo.md) — 287,559 rows
- [FilesFiletypes](./tables/FilesFiletypes.md) — 839,678 rows
- [FileCategories](./tables/FileCategories.md) — 547,809 rows
- [FileType](./tables/FileType.md) — 5 rows
- [ImageDimension](./tables/ImageDimension.md) — 5 rows

### Taxonomy & keywords

- [Categories](./tables/Categories.md) — 99 rows
- [Keywords](./tables/Keywords.md) — 8,898 rows
- [KeywordsContent](./tables/KeywordsContent.md) — 313,259 rows
- [KeywordHasToLink](./tables/KeywordHasToLink.md) — 109 rows

### Site structure

- [Domain](./tables/Domain.md) — 4 rows
- [SiteComponents](./tables/SiteComponents.md) — 11 rows
- [Module](./tables/Module.md) — 91 rows
- [Page](./tables/Page.md) — 69 rows
- [PageLinks](./tables/PageLinks.md) — 114 rows
- [PageLinkRolesRelation](./tables/PageLinkRolesRelation.md) — 69 rows
- [MenuPosition](./tables/MenuPosition.md) — 16 rows

### Users & security

- [Users](./tables/Users.md) — 28 rows
- [Profiles](./tables/Profiles.md) — 28 rows
- [Memberships](./tables/Memberships.md) — 28 rows
- [UsersInRoles](./tables/UsersInRoles.md) — 28 rows
- [Roles](./tables/Roles.md) — 2 rows
- [RoleRelation](./tables/RoleRelation.md) — 0 rows
- [AspNetUsers](./tables/AspNetUsers.md) — 8 rows
- [AspNetRoles](./tables/AspNetRoles.md) — 1 rows
- [AspNetUserRoles](./tables/AspNetUserRoles.md) — 8 rows
- [AspNetUserClaims](./tables/AspNetUserClaims.md) — 0 rows
- [AspNetUserLogins](./tables/AspNetUserLogins.md) — 0 rows
- [CategoriesRolesRelation](./tables/CategoriesRolesRelation.md) — 105 rows
- [RolesModulesRelation](./tables/RolesModulesRelation.md) — 29 rows
- [Applications](./tables/Applications.md) — 2 rows

### Other

- [Advertisments](./tables/Advertisments.md) — 1,078 rows
- [Hits](./tables/Hits.md) — 0 rows
- [Rating](./tables/Rating.md) — 0 rows
- [Tags](./tables/Tags.md) — 0 rows
- [TagToItems](./tables/TagToItems.md) — 0 rows
- [ChangeQueue](./tables/ChangeQueue.md) — 0 rows
- [ChangeQueueType](./tables/ChangeQueueType.md) — 3 rows
- [LinkTarget](./tables/LinkTarget.md) — 4 rows
- [Status](./tables/Status.md) — 7 rows
- [MediaMirror](./tables/MediaMirror.md) — 0 rows
- [TmpTbl](./tables/TmpTbl.md) — 0 rows
- [sysdiagrams](./tables/sysdiagrams.md) — 2 rows
- [__MigrationHistory](./tables/__MigrationHistory.md) — 1 rows

## All tables

| Table | Rows | Group | WordPress hint |
|-------|------|-------|----------------|
| [__MigrationHistory](./tables/__MigrationHistory.md) | 1 | Other | — |
| [Advertisments](./tables/Advertisments.md) | 1,078 | Other | Admin-side ad definitions (note spelling) |
| [Applications](./tables/Applications.md) | 2 | Users & security | — |
| [AspNetRoles](./tables/AspNetRoles.md) | 1 | Users & security | — |
| [AspNetUserClaims](./tables/AspNetUserClaims.md) | 0 | Users & security | — |
| [AspNetUserLogins](./tables/AspNetUserLogins.md) | 0 | Users & security | — |
| [AspNetUserRoles](./tables/AspNetUserRoles.md) | 8 | Users & security | — |
| [AspNetUsers](./tables/AspNetUsers.md) | 8 | Users & security | ASP |
| [Categories](./tables/Categories.md) | 99 | Taxonomy & keywords | Master category tree (`ParentId`) |
| [CategoriesRolesRelation](./tables/CategoriesRolesRelation.md) | 105 | Users & security | — |
| [ChangeQueue](./tables/ChangeQueue.md) | 0 | Other | — |
| [ChangeQueueType](./tables/ChangeQueueType.md) | 3 | Other | — |
| [ContentCategories](./tables/ContentCategories.md) | 336,788 | Content (split model) | Many-to-many content ↔ categories |
| [ContentClassify](./tables/ContentClassify.md) | 3 | Content (split model) | — |
| [ContentCommonInfo](./tables/ContentCommonInfo.md) | 199,653 | Content (split model) | Article body and metadata (title fields, body, publish times) |
| [ContentFiles](./tables/ContentFiles.md) | 350,650 | Content (split model) | Links content to files in the file subsystem |
| [ContentInitialize](./tables/ContentInitialize.md) | 202,335 | Content (split model) | Content header: title, domain, type, status, priority |
| [ContentPrivateInfo](./tables/ContentPrivateInfo.md) | 199,683 | Content (split model) | Editorial workflow: owners, modify times, publisher users |
| [ContentRelation](./tables/ContentRelation.md) | 1,083 | Content (split model) | Related content links (Back spelling) |
| [ContentsModulePageRelation](./tables/ContentsModulePageRelation.md) | 456,551 | Content (split model) | Content placement in modules/pages |
| [ContentsOffer](./tables/ContentsOffer.md) | 1 | Content (split model) | — |
| [ContentSource](./tables/ContentSource.md) | 4 | Content (split model) | — |
| [ContentType](./tables/ContentType.md) | 19 | Content (split model) | — |
| [Domain](./tables/Domain.md) | 4 | Site structure | Multi-language site domains (fa-IR, en-US, etc |
| [FileCategories](./tables/FileCategories.md) | 547,809 | Files / media | — |
| [FileCommonInfo](./tables/FileCommonInfo.md) | 287,559 | Files / media | File metadata and URLs |
| [FileInitialize](./tables/FileInitialize.md) | 287,559 | Files / media | File record header (domain, status) |
| [FilePrivateInfo](./tables/FilePrivateInfo.md) | 287,559 | Files / media | — |
| [FilesFiletypes](./tables/FilesFiletypes.md) | 839,678 | Files / media | File variants (sizes/types) — important for images |
| [FileType](./tables/FileType.md) | 5 | Files / media | — |
| [Hits](./tables/Hits.md) | 0 | Other | — |
| [ImageDimension](./tables/ImageDimension.md) | 5 | Files / media | — |
| [KeywordHasToLink](./tables/KeywordHasToLink.md) | 109 | Taxonomy & keywords | — |
| [Keywords](./tables/Keywords.md) | 8,898 | Taxonomy & keywords | Keyword dictionary |
| [KeywordsContent](./tables/KeywordsContent.md) | 313,259 | Taxonomy & keywords | Content ↔ keyword assignments |
| [LinkTarget](./tables/LinkTarget.md) | 4 | Other | — |
| [MediaMirror](./tables/MediaMirror.md) | 0 | Other | — |
| [Memberships](./tables/Memberships.md) | 28 | Users & security | — |
| [MenuPosition](./tables/MenuPosition.md) | 16 | Site structure | — |
| [Module](./tables/Module.md) | 91 | Site structure | CMS modules tied to site components |
| [Page](./tables/Page.md) | 69 | Site structure | CMS pages configuration |
| [PageLinkRolesRelation](./tables/PageLinkRolesRelation.md) | 69 | Site structure | — |
| [PageLinks](./tables/PageLinks.md) | 114 | Site structure | — |
| [Profiles](./tables/Profiles.md) | 28 | Users & security | — |
| [Rating](./tables/Rating.md) | 0 | Other | — |
| [RoleRelation](./tables/RoleRelation.md) | 0 | Users & security | — |
| [Roles](./tables/Roles.md) | 2 | Users & security | — |
| [RolesModulesRelation](./tables/RolesModulesRelation.md) | 29 | Users & security | — |
| [SiteComponents](./tables/SiteComponents.md) | 11 | Site structure | — |
| [Status](./tables/Status.md) | 7 | Other | — |
| [sysdiagrams](./tables/sysdiagrams.md) | 2 | Other | — |
| [Tags](./tables/Tags.md) | 0 | Other | — |
| [TagToItems](./tables/TagToItems.md) | 0 | Other | — |
| [TmpTbl](./tables/TmpTbl.md) | 0 | Other | — |
| [Users](./tables/Users.md) | 28 | Users & security | Legacy membership users |
| [UsersInRoles](./tables/UsersInRoles.md) | 28 | Users & security | — |

## Relationships

- [Within-database relationships](./relationships.md)
- [Cross-database map](../cross-database-relationships.md)

---

[← Back to all databases](../README.md)
