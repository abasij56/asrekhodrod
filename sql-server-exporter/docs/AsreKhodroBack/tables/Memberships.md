# dbo.Memberships

**Database:** AsreKhodroBack  
**Rows:** 28  
**Primary key:** UserId  
**Group:** Users & security

## Columns

| Column | Type | Nullable | Key |
|--------|------|----------|-----|
| ApplicationId | uniqueidentifier | NO |  |
| UserId | uniqueidentifier | NO | PK |
| Password | nvarchar(128) | NO |  |
| PasswordFormat | int | NO |  |
| PasswordSalt | nvarchar(128) | NO |  |
| Email | nvarchar(256) | YES |  |
| PasswordQuestion | nvarchar(256) | YES |  |
| PasswordAnswer | nvarchar(128) | YES |  |
| IsApproved | bit | NO |  |
| IsLockedOut | bit | NO |  |
| CreateDate | datetime | NO |  |
| LastLoginDate | datetime | NO |  |
| LastPasswordChangedDate | datetime | NO |  |
| LastLockoutDate | datetime | NO |  |
| FailedPasswordAttemptCount | int | NO |  |
| FailedPasswordAttemptWindowStart | datetime | NO |  |
| FailedPasswordAnswerAttemptCount | int | NO |  |
| FailedPasswordAnswerAttemptWindowsStart | datetime | NO |  |
| Comment | nvarchar(256) | YES |  |

## Logical relationships (within AsreKhodroBack)

- `UserId` → `Users.UserId` — Membership

---

[← Back to AsreKhodroBack overview](../README.md)
