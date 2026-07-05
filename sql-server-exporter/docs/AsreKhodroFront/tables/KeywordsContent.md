# dbo.KeywordsContent

**Database:** AsreKhodroFront  
**Rows:** 310,801  
**Primary key:** *(none)*  
**Group:** Core content

## WordPress migration note

Tags/keywords per article.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| KeywordId | int | NO |  |
| KeywordTitle | nvarchar(2048) | NO |  |
| ContentId | int | NO |  |
| ContentTitle | nvarchar(1000) | NO |  |

## Logical relationships (within AsreKhodroFront)

- `ContentId` → `SingleContent.ContentId` — Tags/keywords for content

## Cross-database links

- Referenced from **AsreKhodroBack**.KeywordsContent.ContentId — Keywords/tags replicated to Front. *(verified: logical copy)*

---

[← Back to AsreKhodroFront overview](../README.md)
