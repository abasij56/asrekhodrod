# dbo.KeywordsContent

**Database:** AsreKhodroBack  
**Rows:** 313,259  
**Primary key:** Id  
**Group:** Taxonomy & keywords

## WordPress migration note

Content ↔ keyword assignments.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| KeywordId | int | NO |  |
| ContentId | int | NO |  |
| Relevancy | tinyint | YES |  |

## Logical relationships (within AsreKhodroBack)

- `ContentId` → `ContentInitialize.Id` — Keywords on content

## Cross-database links

- `ContentId` → **AsreKhodroFront**.KeywordsContent.ContentId — Keywords/tags replicated to Front. *(verified: logical copy)*

---

[← Back to AsreKhodroBack overview](../README.md)
