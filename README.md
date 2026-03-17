# PharmaFlow Pro

PharmaFlow Pro is a web-based pharmacy management system built with PHP and MySQL for day-to-day retail pharmacy operations.

It combines inventory, billing, purchases, suppliers, prescriptions, returns, cash controls, and reporting in one application.

## What This Project Covers

- Role-based login and dashboards (Administrator, Pharmacist, Staff)
- Medicine catalog and category management
- Stock updates, low-stock alerts, expiration monitoring, and reorder suggestions
- Sales flow with invoice generation and held cart support
- Sales returns workflow with status tracking
- Supplier and purchase lifecycle (history, returns, settlements, payables)
- Prescription tracking and fulfillment status logs
- Customer ledger and financial reports
- Backup and restore with audit metadata
- Activity logging and permissions matrix management

## Tech Stack

- Backend: PHP
- Database: MySQL / MariaDB
- Frontend: HTML, CSS, Bootstrap 5, Bootstrap Icons, JavaScript
- Charts: Chart.js
- Local environment: XAMPP (Apache + MariaDB)

## Project Structure

```text
pharmaflow_pro/
|-- app/
|   |-- auth.php
|   |-- init.php
|   |-- Config/
|   |   `-- config.php
|   |-- Core/
|   |   `-- Database.php
|   `-- Models/
|       |-- ActivityLog.php
|       |-- CashRegister.php
|       |-- Category.php
|       |-- Customer.php
|       |-- Inventory.php
|       |-- Medicine.php
|       |-- Prescription.php
|       |-- Purchase.php
|       |-- Returns.php
|       |-- Sale.php
|       |-- Supplier.php
|       `-- User.php
|-- database/
|   |-- medical_management.sql
|   `-- setup/
|       |-- setup_database.php
|       |-- migrate.php
|       |-- seed_admin.php
|       |-- verify_admin.php
|       |-- add_new_features.php
|       |-- scheduled_backup.php
|       |-- update_medicines_table.php
|       `-- update_suppliers_table.php
|-- public/
|   |-- index.php
|   |-- styles.css
|   |-- dashboard/
|   |-- inventory/
|   |-- sales/
|   |-- purchase/
|   |-- settings/
|   `-- users/
|-- add_initial_stock.php
|-- check_db.php
`-- index.php
```

## Prerequisites

- PHP 8.0+
- MySQL 8+ or MariaDB 10.4+
- Apache web server (XAMPP recommended)
- Browser (Chrome, Edge, Firefox)

## Quick Start (XAMPP)

1. Place the project in your web root:

```text
C:/xampp/htdocs/pharmaflow_pro
```

2. Create the database:

```text
medical_management
```

3. Import base schema:

```text
database/medical_management.sql
```

4. Configure database connection in:

```text
app/Config/config.php
```

5. Run migration scripts in browser (recommended):

```text
http://localhost/pharmaflow_pro/database/setup/migrate.php
```

6. Seed or reset default admin account (optional but useful for first login):

```text
http://localhost/pharmaflow_pro/database/setup/seed_admin.php
```

7. Verify admin account exists (optional):

```text
http://localhost/pharmaflow_pro/database/setup/verify_admin.php
```

8. Open the app:

```text
http://localhost/pharmaflow_pro/public
```

## Default Admin Login (from seed script)

If you run the seed script, the default admin account is:

- Email: admin1@pharmacy.com
- Password: admin123
- Role: Administrator

Change this password immediately after first login.

## Configuration Notes

Main settings are in `app/Config/config.php`:

- DB_HOST
- DB_USER
- DB_PASS
- DB_NAME
- APP_NAME
- BASE_URL
- BACKUP_RESTORE_PASSWORD

For local XAMPP, `BASE_URL` should usually be `/`.

## Typical Workflows

### Sales and Billing

1. Add medicines and stock.
2. Create a sale from Sell Medicine.
3. Print or review invoice from invoice module.
4. Track transaction history in sales records.

### Purchases and Supplier Dues

1. Add supplier records.
2. Create purchase entries and item lines.
3. Record settlements.
4. Monitor outstanding supplier payables.

### Stock Control

1. Review low-stock and alert center pages.
2. Use expiration management for near-expiry medicines.
3. Use reorder suggestions for procurement planning.

## Security Checklist (Important)

- Rotate all DB credentials in `app/Config/config.php`.
- Change `BACKUP_RESTORE_PASSWORD` from the default value.
- Do not keep default admin credentials in production.
- Use least-privileged DB user instead of root/admin.
- Restrict direct access to setup scripts in production.

## API Endpoints

Internal utility endpoints under `public/api/`:

- export_csv.php
- get_statistics.php
- search.php

## Troubleshooting

- Database connection error:
  - Recheck credentials in `app/Config/config.php`.
  - Confirm MySQL/MariaDB service is running.

- Missing tables/columns after import:
  - Run `database/setup/migrate.php` from browser.

- Cannot login with admin:
  - Run `database/setup/seed_admin.php`.
  - Re-verify with `database/setup/verify_admin.php`.

- Access denied on pages:
  - Verify logged-in role and permissions matrix settings.

## License

No license file is currently included.

If you plan to publish this project, add a LICENSE file and update this section.
