# SecureScope

SecureScope is a PHP and MySQL cybersecurity assessment management system for a Web Development course. This first version establishes the project structure, secure database connection, authentication, role-based access control, dashboards, CSRF protection, and audit logging foundation.

## Current roles

- **Security Manager** — manages clients, accounts, projects, analyst assignments, reviews, reports, and remediation verification.
- **Security Analyst** — works only on assigned assessments and later manages findings, evidence, and recommendations.
- **Client** — accesses only its company data and later manages assets and remediation updates.

## Local XAMPP setup

1. Copy this project into `C:\xampp\htdocs\securescope`.
2. Import the approved `securescope_schema_v1` SQL file into phpMyAdmin. The database name must remain `securescope`.
3. Start **Apache** and **MySQL** in XAMPP.
4. The default configuration expects XAMPP's local MySQL account: host `127.0.0.1`, user `root`, and an empty password. Change these values through `SECURESCOPE_DB_*` environment variables if your setup differs.
5. Create the initial Manager account from a terminal in this folder:

   ```powershell
   C:\xampp\php\php.exe scripts\seed_admin.php manager@example.com Sara Ahmed "ChangeThisPassword123!"
   ```

6. Open [http://localhost/securescope/](http://localhost/securescope/).

If the project folder has a name other than `securescope`, set `SECURESCOPE_BASE_URL` to the matching URL path (for example, `/my-folder`).

## Structure

```text
config/       Application and PDO database configuration
includes/     Authentication, authorization, CSRF, audit, and shared helpers
partials/     Shared page layout
manager/      Security Manager pages
analyst/      Security Analyst pages
client/       Client portal pages
storage/      Non-versioned evidence upload storage
scripts/      Command-line development utilities
assets/        Basic CSS and JavaScript
```

## Security decisions already applied

- Passwords are stored with PHP's `password_hash()` and checked using `password_verify()`.
- PDO uses native prepared statements, preventing SQL injection through query values.
- Sessions use HttpOnly, SameSite=Lax cookies and regenerate their ID after login.
- Every request refreshes the signed-in user's status and role from the database.
- POST requests include a CSRF token.
- Role checks exist on each role dashboard; future CRUD pages will use the same `require_role()` function plus record-ownership checks.
- Authentication events are written to `audit_logs`; no password or password hash is placed in that log.

## Next build step

Implement the Manager CRUD in this order: clients, users, projects, analyst assignments, then assessments. Each operation will validate the business rules defined for SecureScope and record an audit entry.
 
the Application form Links
for the Admin to edit the form 
https://docs.google.com/forms/d/1FAXVXt033RC_CskZb1Ch1habPCilAzoq9wAN9xh_goc/edit
for the applicant to fill 
https://docs.google.com/forms/d/e/1FAIpQLSdshwkT9bwFT9KvM8ZHt5cP69viTk9E2cE6rxl8gw9VEvyUOw/viewform

