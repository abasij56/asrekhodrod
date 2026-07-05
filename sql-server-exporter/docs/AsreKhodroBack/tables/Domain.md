# dbo.Domain

**Database:** AsreKhodroBack  
**Rows:** 4  
**Primary key:** Id  
**Group:** Site structure

## WordPress migration note

Multi-language site domains (fa-IR, en-US, etc.).

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | int | NO | PK |
| Language | nvarchar(150) | NO |  |
| Calture | nvarchar(10) | NO |  |
| Direction | nvarchar(3) | NO |  |
| URL | nvarchar(256) | YES |  |
| FlagURL | nvarchar(256) | YES |  |
| StatusId | tinyint | NO |  |

## Logical relationships (within AsreKhodroBack)

- Referenced by `ContentInitialize.DomainId` — Content language/domain

## Cross-database links

- `Id` → **AsrekhodroWidget**.FileInitialize.DomainId — Widget files scoped to the same multi-language domains as Back. *(verified: DomainId=1 in Widget)*

---

[← Back to AsreKhodroBack overview](../README.md)
