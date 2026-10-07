# Ledgercraft
Responsive, open-source double-entry accounting built with PHP, MySQL, vanilla JavaScript, and CSS. MIT licensed. No Node.js, Composer packages, CDN, or frontend build is required to run the app.

## Your configured local installation
- App: http://127.0.0.1:8088
- PHP: 8.3.6, from your existing WAMP installation.
- MySQL: isolated instance on 127.0.0.1:3307. Your existing WAMP databases are unchanged.
- App database: ledgercraft. Test database: ledgercraft_test.
- MySQL files: the task's work/mysql-data directory (outside this source folder).
- Configuration: config/config.php (private; excluded from the deployment ZIP).
- Database administration credentials: the task's work/mysql-admin.json (private; excluded from the ZIP).
- Open ledgercraft.code-workspace in VS Code. PHP validation and the open-source Xdebug debugger are configured.

To restart after reboot, double-click Start Ledgercraft.cmd, or run bin/start-local.ps1 from PowerShell. It starts only the app's local database and PHP server. In VS Code, use Terminal → Run Task → Start accounting app, or F5 → Run PHP development server. Do not start a second PHP server on the same port. If the app is already running, use the browser or “Listen for Xdebug” instead.

The first screen creates your administrator; there is no default application password. Local PHP development automatically supplies the first-setup token only to loopback requests. On web hosting, enter the setup token from your private configuration. Create your first company after signing in. New companies default to PKR.

## Included
- Separate company books with company switching and a fixed base currency per company.
- Owner, Accountant, and Viewer roles, with server-side membership checks on every company operation.
- Sales invoices, purchase bills, line items, printable invoices / browser Save as PDF.
- Partial and full receipts and payments; outstanding receivables and payables.
- Direct income receipts, expenses, cash and bank accounts, and transfers.
- Balanced journals, account ledgers, chart of accounts, trial balance, profit and loss, and balance sheet.
- Journal reversals and unpaid invoice voiding that preserve originals.
- Audit trail, CSV trial balance export, password changes.
- Fluid layouts for phones, tablets, and desktops; keyboard-accessible native dialogs and reduced-motion support.

## Accounting rules
All amounts are stored in integer minor units (paisa/cents); quantities have up to four decimals. Invoice item totals round half-up to two decimals. Every journal is validated before posting; writes use InnoDB transactions. Company row locks serialize postings and invoice numbering. Request keys prevent duplicate postings on retry.

Receivable and payable control accounts cannot be used in manual journals. Use invoices and their payment actions to keep these controls in agreement with the subledgers. Manual opening balances for cash/bank can be posted against owner capital. Existing customer/supplier opening balances should be entered as opening invoices/bills.

Sales: debit receivables, credit sales revenue.
Purchases: debit purchases expense, credit payables.
Receipts: debit cash/bank, credit receivables.
Supplier payments: debit payables, credit cash/bank.
Direct income: debit cash/bank, credit the selected revenue account. Use invoice payments for existing invoices so revenue is not counted twice.
Expenses: debit expense, credit cash/bank.
Transfers: debit destination, credit source.

Posted entries are immutable. Reverse erroneous manual entries, direct income receipts, expenses, or transfers. Only unpaid invoices can be voided; paid invoices cannot be voided using this release. Reversals use a date on or after the original transaction.

Reports use all posted dates unless an as-of date is selected. Profit and loss is cumulative, not a fiscal-year close. Each company has one currency; no exchange-rate conversion or consolidation is performed.

## Requirements
- PHP 8.2+ (tested on 8.3.6), 64-bit.
- PDO MySQL, mbstring, JSON, and session extensions.
- MySQL 8.0+ with InnoDB (tested on MySQL 8.3.0).
- A current browser with native dialog support.
- HTTPS on online installations.
- Apache, Nginx, IIS with PHP configured, or PHP's local development server.
Static-only hosting cannot run PHP/MySQL. Other MySQL-compatible databases have not been tested.

## Install on another local machine or server
1. Extract the deployment ZIP.
2. Create an empty MySQL database and an application user.
3. Copy config/config.example.php to config/config.php.
4. Set database host, port, database, username, password, timezone, and a random setup_token of at least 24 characters. Generate a token with:
   php -r "echo bin2hex(random_bytes(24));"
5. Import database/schema.sql in your hosting control panel / phpMyAdmin, or run:
   php bin/install.php
   The account used for installation needs CREATE/INDEX/REFERENCES privileges. After installation, the runtime account only needs SELECT, INSERT, UPDATE, DELETE on this database.
6. Point the website document root to the public directory.
7. Enable HTTPS and set secure_cookies to true.
8. Open the site and create the first administrator using the setup token.
9. Create a company, then add users from Team & access.

Do not upload your existing local config/config.php to another server. Use that server's own credentials. No database password, administrator password, or setup token is included in the release ZIP.

## Shared hosting
Preferred: place app, config, database and the remaining project outside public_html, then configure the domain document root to the project's public directory.

If your host requires public_html:
- Put the public directory's contents into public_html.
- Put app and config beside public_html, one level above it.
- Keep the relative require paths in index.php and api.php pointing to that parent directory.
- Import schema.sql through phpMyAdmin.
- Do not put config files in an exposed web directory.

For Apache hosts where the entire project must sit under a domain folder, the provided root .htaccess routes requests into public and denies access to internal folders. This requires mod_rewrite and AllowOverride. Verify these rules with your host. Prefer a public-only document root. Nginx ignores .htaccess; use deploy/nginx.conf as a starting point. IIS should also use public as its physical path.

No background worker, scheduler, SSH, custom routing module, or build pipeline is required for ordinary use.

## Developer workflow
Files:
- public/index.php: app shell
- public/assets/app.js: interface and interactions
- public/assets/app.css: responsive design
- public/api.php: sessions, authentication and JSON endpoints
- app/accounting.php: accounting rules and transactions
- app/bootstrap.php: configuration, PDO, sessions and validation
- database/schema.sql: normalized schema
- tests/accounting.php: accounting and authorization checks
- tests/concurrent-payment.php: independent-process payment race check

The workspace PHP path is configured for this Windows PC. Change .vscode/settings.json, tasks.json and launch.json if PHP is elsewhere. The browser frontend has no install step.

For a generic local development server:
php -S 127.0.0.1:8088 -t public

The optional local Windows startup script uses this PC's existing WAMP paths and task-local MySQL data directory; use your own MySQL service on another computer.

## Verification
Prepare ledgercraft_test with the same schema and grant your configured application user data privileges on it. Then run:
php -d xdebug.mode=off tests/accounting.php
php -d xdebug.mode=off tests/concurrent-payment.php
php -d xdebug.mode=off tests/income.php

The tests explicitly select ledgercraft_test. They create test users and companies there and do not modify the live ledgercraft database. Test fixture credentials are kept under var/ (ignored by Git).

Validated here:
- 27 accounting and company-access checks.
- 15 additional Income checks, plus mobile posting and reversal browser checks.
- One independent-process race test: two payments competing for one balance; exactly one succeeds.
- All 11 secondary views render in Edge.
- Browser invoice posting and partial payment.
- CSRF rejection and unauthenticated access rejection.
- Layout widths 320, 390, 768, 1440, 2560 pixels, without page overflow.
- Phone invoice form inspected visually.
- No JavaScript runtime errors in the checked flows.

## Backups
Use your host's scheduled MySQL backups, or mysqldump with --single-transaction. Store backups outside the web root and test restores into a separate database. Back up your private config separately. The ZIP is source code only, not a database backup.

## Current scope
This release covers the requested core accounting workflows. It does not calculate taxes, manage inventory, reconcile bank statements, import bank feeds, close fiscal periods, consolidate currencies, send invoice emails, or issue tax-authority/e-invoicing submissions. Online publishing and server-specific setup still require your hosting details. Large datasets should add server-side pagination before high-volume use; this version loads a company's records into its workspace.
