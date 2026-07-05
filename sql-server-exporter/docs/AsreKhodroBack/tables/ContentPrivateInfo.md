# dbo.ContentPrivateInfo

**Database:** AsreKhodroBack  
**Rows:** 199,683  
**Primary key:** Id  
**Group:** Content (split model)

## WordPress migration note

Editorial workflow: owners, modify times, publisher users.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| ContentId | int | NO |  |
| CreateTime | datetime | NO |  |
| LastModifyTime | datetime | YES |  |
| Owner | uniqueidentifier | NO |  |
| BodyCharCount | int | NO |  |
| LastUser | uniqueidentifier | NO |  |
| CurrentUser | uniqueidentifier | YES |  |
| SenderUser | uniqueidentifier | YES |  |
| EditingUser | uniqueidentifier | YES |  |
| PublisherUser | uniqueidentifier | YES |  |
| LastModifyerUser | uniqueidentifier | NO |  |
| TypistUser | uniqueidentifier | YES |  |

## Logical relationships (within AsreKhodroBack)

- `ContentId` → `ContentInitialize.Id` — Workflow → content header

---

[← Back to AsreKhodroBack overview](../README.md)
