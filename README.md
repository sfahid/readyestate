# Ready Estate

Ready Estate is a separate real estate and double-entry accounting application, built from the current Ledgercraft WAMP version. It uses plain PHP, MySQL, JavaScript and CSS. No Composer or Node install is needed. The original MIT license is included.

## Local WAMP setup

- WAMP app: `C:/wamp64/www/readyestate`
- URL: `http://localhost/readyestate/`
- Database: a separate `readyestate` MySQL 8 database with an application-only user.
- Private settings: `C:/wamp64/www/readyestate/config/config.php`.
- To create the first administrator, use the `setup_token` from that private configuration. No default administrator exists.
- New companies default to PKR.

Do not import a Ledgercraft or Ready Books data backup into this new installation. `database/fresh-install.sql` creates the 28 empty tables required for accounting, parties, property listings, deals, client money, company-owned assets, documents, tasks and follow-ups. `bin/install.php` performs the same schema installation and refuses a nonempty database.

The source project is in `Documents/Codex/Projects/ready-estate`; open `ready-estate.code-workspace` in VS Code. The WAMP deployment is a separate copy. When editing, deploy reviewed source changes to that copy while preserving its private `config/config.php`.

Documents are stored as blobs in the database; backups must include the complete database. The app provides journal, ledger, invoices, expenses, income, receivables, payables, cash/bank, reports, multi-company roles and the real estate workflows described in `REALESTATE.md`.

The root `.htaccess` routes requests to `public/` and denies access to code, configuration, database schema, development files and variable data. For online hosting, point the document root directly at `public/` and enable HTTPS with secure cookies. This local WAMP installation uses HTTP and secure cookies disabled.
