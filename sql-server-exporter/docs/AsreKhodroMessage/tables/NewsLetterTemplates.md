# dbo.NewsLetterTemplates

**Database:** AsreKhodroMessage  
**Rows:** 11  
**Primary key:** Id  
**Group:** Contacts & newsletter

## WordPress migration note

Email HTML templates.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| Title | nvarchar(1024) | YES |  |
| HTML | nvarchar(MAX) | YES |  |
| District | nvarchar(50) | YES |  |
| Categories | nvarchar(256) | YES |  |
| Description | nvarchar(1024) | YES |  |
| CreateDate | datetime | NO |  |
| LastUpdate | datetime | YES |  |
| StatusId | tinyint | YES |  |

---

[← Back to AsreKhodroMessage overview](../README.md)
