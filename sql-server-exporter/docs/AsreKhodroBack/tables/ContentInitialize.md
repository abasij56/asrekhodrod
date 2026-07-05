# dbo.ContentInitialize

**Database:** AsreKhodroBack  
**Rows:** 202,335  
**Primary key:** Id  
**Group:** Content (split model)

## WordPress migration note

Content header: title, domain, type, status, priority. PK `Id` = `ContentId` elsewhere.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| DomainId | int | NO |  |
| ContentTypeId | tinyint | YES |  |
| ContentClassifyId | tinyint | YES |  |
| ContentSourceId | tinyint | YES |  |
| Title | nvarchar(1000) | NO |  |
| Periority | int | NO |  |
| Params | nvarchar(MAX) | YES |  |
| RoleId | uniqueidentifier | YES |  |
| StatusId | tinyint | NO |  |

## Logical relationships (within AsreKhodroBack)

- Referenced by `ContentCommonInfo.ContentId` — Body/metadata → content header
- Referenced by `ContentPrivateInfo.ContentId` — Workflow → content header
- Referenced by `ContentCategories.ContentId` — Category assignment
- Referenced by `ContentFiles.ContentId` — Content media link
- Referenced by `KeywordsContent.ContentId` — Keywords on content
- Referenced by `ContentRelation.ParentContentId` — Related content
- `DomainId` → `Domain.Id` — Content language/domain
- Referenced by `ContentsModulePageRelation.ContentId` — Content placement

## Cross-database links

- `Id` → **AsreKhodroBack**.ContentCommonInfo.ContentId — Within Back: content header (Initialize) + body/metadata (CommonInfo/PrivateInfo). *(verified: same database)*

---

[← Back to AsreKhodroBack overview](../README.md)
