# AsreKhodroFront — relationships

> No SQL foreign keys declared in this database.

## Logical relationships

| From | Column | To | Column | Notes |
|------|--------|----|--------|-------|
| ContentCategories | ContentId | SingleContent | ContentId | Content belongs to categories |
| ContentCategories | CategoryId | Categories | Id | Category assignment |
| ContentFiles | ContentId | SingleContent | ContentId | Media attached to content |
| KeywordsContent | ContentId | SingleContent | ContentId | Tags/keywords for content |
| ContentsRelation | ParentContentId | SingleContent | ContentId | Related content (parent) |
| ContentsRelation | ChildContentId | SingleContent | ContentId | Related content (child) |
| Categories | ParentId | Categories | Id | Category hierarchy |
| Menu | ParentId | Menu | Id | Menu hierarchy |
| Advertisements | CategoryId | Categories | Id | Optional ad category |
| Hits | ItemId | SingleContent | ContentId | Content view tracking |
| TopHits | ContentId | SingleContent | ContentId | Popular content |
| MainContents | ContentId | SingleContent | ContentId | Homepage cache |
| MainLastContents | ContentId | SingleContent | ContentId | Latest content cache |

## Cross-database links

| Direction | Link | Verified |
|-----------|------|----------|
| ← AsreKhodroBack | ContentCommonInfo.ContentId → SingleContent.ContentId | 197643 matching rows |
| ← AsreKhodroBack | Categories.Id → Categories.Id | 99 matching rows |
| ← AsreKhodroBack | ContentCategories.ContentId → ContentCategories.ContentId | logical copy |
| ← AsreKhodroBack | ContentFiles.ContentId → ContentFiles.ContentId | logical copy |
| ← AsreKhodroBack | KeywordsContent.ContentId → KeywordsContent.ContentId | logical copy |

---

[← Back to AsreKhodroFront overview](./README.md)
