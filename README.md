# POS STORE v1.0 — Phase 1: Foundation

A Point of Sale system built on PHP 7.4, SQL Server 2014 (via PDO_SQLSRV),
Bootstrap 5, and jQuery/AJAX, following a lightweight MVC structure.

This delivery contains **Phase 1 — Foundation**: secure authentication,
session management, the app layout shell, and the full database schema
that later phases (Dashboard, Products, POS Screen, Reports) will build on.

## What's included in Phase 1

- Secure AJAX login (no page reload) with client + server-side validation
- bcrypt password hashing (`password_hash` / `password_verify`)
- CSRF token protection on all POST requests
- XSS filtering / output escaping helpers
- PDO prepared statements everywhere (SQL injection protection)
- Session timeout (idle) + periodic session ID regeneration (session fixation protection)
- Remember Me (selector/validator token pattern — safe against DB-leak replay)
- Account lockout after repeated failed logins
- Login logs + general activity/audit log
- Role-based access control scaffold (`SessionManager::requireRole()`)
- Responsive Bootstrap 5 layout shell: navbar, collapsible sidebar, dashboard placeholder
- Full SQL Server schema for every module (Users, Roles, Permissions, Products,
  Categories, Customers, Suppliers, Inventory, Sales, Sale Details, Purchases,
  Purchase Details, Settings, Activity Logs)

## Requirements

- PHP 7.4.4+
- PHP extension: `pdo_sqlsrv` (Microsoft Drivers for PHP for SQL Server)
- SQL Server 2014 or later
- Apache with `mod_rewrite` and `mod_headers` enabled (or adapt `.htaccess` rules for Nginx/IIS)

## Setup

1. **Create the database.**
   Run `database/pos_store.sql` against your SQL Server instance
   (via SSMS, `sqlcmd`, or Azure Data Studio). It will create the
   `pos_store` database, every table, seed Roles/Permissions/Settings,
   and a default administrator account.

2. **Configure the app.**
   Open `config/config.php` and update:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` to match your SQL Server instance
   - `BASE_URL` to match where the app is hosted (e.g. `/pos_store` or `''` for root)
   - `APP_ENV` to `production` before going live (this disables verbose error output)

3. **Point your web server** at the project root, with `index.php` /
   `login.php` as the directory index (already set in `.htaccess`).

4. **Log in.**
   Default administrator credentials:
   - Username: `admin`
   - Password: `Admin@12345`

   **Change this password immediately after your first login** — password
   management UI ships in a later phase, so for now update it directly:
   generate a new hash with `password_hash('yourNewPassword', PASSWORD_BCRYPT)`
   in a throwaway PHP script and `UPDATE Users SET password_hash = '...' WHERE username = 'admin'`.

5. **Uploads folder.**
   Create `assets/uploads/products/` and make sure it's writable by the
   web server user — the Product module (Phase 2) will write images there.

## Project structure

```
POS_STORE/
├── index.php              Front controller for authenticated pages
├── login.php               Public login entry point
├── logout.php               Destroys session + remember-me token
├── config/
│   ├── config.php           App constants, env, autoloader
│   ├── database.php         PDO_SQLSRV singleton connection
│   ├── session.php           Secure session bootstrap + timeout
│   └── security.php          CSRF, XSS filtering, validation helpers
├── app/
│   ├── controllers/
│   │   └── LoginController.php
│   ├── models/
│   │   └── User.php
│   └── helpers/
│       └── Helper.php
├── assets/
│   ├── css/style.css
│   └── js/{app.js, login.js}
├── views/
│   └── login.php
├── includes/
│   ├── header.php / footer.php / navbar.php / sidebar.php
├── database/
│   └── pos_store.sql
├── .htaccess
└── README.md
```

## Security notes

- All SQL is executed through parameterized PDO prepared statements —
  never through string-concatenated queries.
- All output that includes user-supplied data is passed through
  `Security::escape()` before being echoed into HTML.
- The CSRF token is regenerated on login and validated (via `hash_equals`,
  timing-safe) on every state-changing POST request.
- Session cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` when served
  over HTTPS.
- Config files under `config/` are blocked from direct browser access at
  the web-server level (see `.htaccess`) in addition to PHP-level guards.

## Roadmap

- **Phase 2:** Dashboard widgets/charts, Product module (CRUD, barcode/QR,
  image upload, AJAX search/pagination/sorting), Categories, Customers, Suppliers, Inventory
- **Phase 3:** POS Screen (barcode scanning, cart, hold/resume, Cash/GCash/Maya/Card payments, receipts), Sales, Purchases
- **Phase 4:** Reports (daily/weekly/monthly/yearly, profit, expenses, fast/slow moving), Roles & Permissions UI, Activity Logs UI, Settings
