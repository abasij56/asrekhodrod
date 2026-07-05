# AsreKhodro database documentation

Schema reference for migrating AsreKhodro SQL Server data to WordPress.

**Databases documented:** 5  
**Generated:** 2026-06-16T14:15:47.274Z  
**Server:** local SQL Server (`sqlcmd -S .`)

## Start here

- **[Cross-database relationships](./cross-database-relationships.md)** — how the 5 DBs connect

## Databases

- **[AsreKhodroBack](./AsreKhodroBack/README.md)** — 56 tables, 4,321,045 rows — Master CMS (admin backend)
- **[AsreKhodroComments](./AsreKhodroComments/README.md)** — 7 tables, 27,260 rows — User comments on site content
- **[AsreKhodroFront](./AsreKhodroFront/README.md)** — 21 tables, 311,173,742 rows — Published website cache (read-optimized)
- **[AsreKhodroMessage](./AsreKhodroMessage/README.md)** — 9 tables, 8,685 rows — Contacts, newsletter, and internal messaging
- **[AsrekhodroWidget](./AsrekhodroWidget/README.md)** — 23 tables, 4,615 rows — Sidebar / widget content module

## Regenerate all docs

```powershell
cd d:\prj-lenovo-shakhes\asrekhodro-1405\dev\sql-server-exporter
npm run docs
```
