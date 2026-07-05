# dbo.AspNetUsers

**Database:** AsreKhodroBack  
**Rows:** 8  
**Primary key:** Id  
**Group:** Users & security

## WordPress migration note

ASP.NET Identity admin/editor accounts.

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| Id | nvarchar(128) | NO | PK |
| UserName | nvarchar(256) | NO |  |
| Name | nvarchar(256) | YES |  |
| Family | nvarchar(256) | YES |  |
| UserImageId | nvarchar(50) | YES |  |
| Gender | bit | YES |  |
| FatherName | nvarchar(50) | YES |  |
| PersonalIdentity | nvarchar(50) | YES |  |
| Birthdate | nvarchar(256) | YES |  |
| Description | nvarchar(1024) | YES |  |
| HasCookie | bit | YES |  |
| Email | nvarchar(256) | YES |  |
| EmailConfirmed | bit | NO |  |
| PasswordHash | nvarchar(MAX) | YES |  |
| SecurityStamp | nvarchar(MAX) | YES |  |
| PhoneNumber | nvarchar(MAX) | YES |  |
| PhoneNumberConfirmed | bit | NO |  |
| TwoFactorEnabled | bit | NO |  |
| LockoutEndDateUtc | datetime | YES |  |
| LockoutEnabled | bit | NO |  |
| AccessFailedCount | int | NO |  |

---

[← Back to AsreKhodroBack overview](../README.md)
