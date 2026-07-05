# dbo.Page

**Database:** AsreKhodroBack  
**Rows:** 69  
**Primary key:** Id  
**Group:** Site structure

## WordPress migration note

CMS pages configuration.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| Name | nvarchar(50) | NO |  |
| Title | nvarchar(50) | NO |  |
| TemplateId | int | NO |  |
| URL | nvarchar(512) | YES |  |
| Unicode | nvarchar(256) | YES |  |
| Keywords | nvarchar(1024) | YES |  |
| IsBackend | bit | NO |  |
| ExpireTime | datetime | YES |  |
| CreateDate | datetime | NO |  |
| StatusId | tinyint | NO |  |

---

[← Back to AsreKhodroBack overview](../README.md)
