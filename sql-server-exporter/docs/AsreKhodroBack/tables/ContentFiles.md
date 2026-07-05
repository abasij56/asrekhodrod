# dbo.ContentFiles

**Database:** AsreKhodroBack  
**Rows:** 350,650  
**Primary key:** Id  
**Group:** Content (split model)

## WordPress migration note

Links content to files in the file subsystem.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| ContentId | int | NO |  |
| FileId | int | NO |  |
| FileTypeId | int | NO |  |
| IsMain | bit | NO |  |
| StatusId | tinyint | NO |  |
| Periority | int | NO |  |
| Title | nvarchar(1024) | YES |  |

## Logical relationships (within AsreKhodroBack)

- `ContentId` → `ContentInitialize.Id` — Content media link
- `FileId` → `FileInitialize.Id` — File reference

## Cross-database links

- `ContentId` → **AsreKhodroFront**.ContentFiles.ContentId — Media attachments replicated to Front. *(verified: logical copy)*

---

[← Back to AsreKhodroBack overview](../README.md)
