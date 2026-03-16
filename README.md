# PharmaFlow Pro

A complete, web-based pharmacy operations platform built with PHP and MySQL for day-to-day retail pharmacy workflows.

This project centralizes inventory, billing, prescriptions, suppliers, returns, reporting, user access control, and operational safeguards in one application. It is designed to reduce manual work, improve stock and cash accuracy, and support faster customer service.

## Table of Contents

- Project Overview
- Core Features
- Technology Stack
- Project Structure
- Prerequisites
- Installation and Setup
- Configuration
- Usage and Typical Workflows
- Security and Operational Notes
- API Endpoints
- Troubleshooting
- Contribution Guide
- License
- Acknowledgments

## Project Overview

PharmaFlow Pro helps pharmacy teams manage end-to-end operations:

- Maintain medicine catalog, pricing, stock, categories, and batches.
- Process sales with invoice grouping and payment details.
- Handle customer returns with status approvals and refund tracking.
- Manage suppliers, purchases, payables, and purchase returns.
- Track prescriptions and prescription item fulfillment.
- Monitor expiration risk, reorder needs, and stock alerts.
- Generate statistics, top-sales insights, and financial summaries.
- Operate safely with role-based access, CSRF validation, and activity logging.

## Core Features

- Role-based authentication and authorization for Administrator, Pharmacist, and Staff.
- Dashboard variants for each role with quick access to relevant modules.
- Inventory lifecycle management with stock updates, logs, alerts, and reports.
- Billing and sales workflows including hold cart, invoice view, and sales records.
- Purchase management with line items, settlements, due tracking, and supplier payables.
- Sales return and purchase return workflows with auditing fields.
- Prescription management with itemized medicine details and status logs.
- Customer ledger support with contact, visit, and spending summaries.
- Backup and restore module with section password and run history tracking.
- Branch and stock transfer entities for multi-branch operation scenarios.
- CSV export/search/statistics APIs for operational integrations.

## Technology Stack

- Backend: PHP (procedural pages + model classes)
- Database: MySQL / MariaDB
- Frontend: HTML, CSS, Bootstrap 5, Bootstrap Icons, JavaScript
- Charts and analytics visuals: Chart.js
- Runtime environment: XAMPP (Apache + MariaDB)

## Project Structure

```text
pharmaflow_pro/
|-- add_initial_stock.php
|-- check_db.php
|-- index.php
|-- Project_Abstract_and_ER_Model.txt
|-- README.md
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
|       |-- add_new_features.php
|       |-- migrate.php
|       |-- scheduled_backup.php
|       |-- seed_admin.php
|       |-- setup_database.php
|       |-- update_medicines_table.php
|       |-- update_suppliers_table.php
|       `-- verify_admin.php
`-- public/
   |-- index.php
   |-- logout.php
   |-- styles.css
   |-- add/
   |   |-- add-medicine.php
   |   |-- categories.php
   |   `-- edit-medicine.php
   |-- api/
   |   |-- export_csv.php
   |   |-- get_statistics.php
   |   `-- search.php
   |-- check/
   |   `-- check-stock.php
   |-- dashboard/
   |   |-- dashboard.php
   |   |-- pharmacist_dashboard.php
   |   `-- staff_dashboard.php
   |-- expiration/
   |   `-- expiration-management.php
   |-- inventory/
   |   |-- alert_center.php
   |   |-- alerts.php
   |   |-- bulk_import.php
   |   |-- check_stock.php
   |   |-- inventory_report.php
   |   `-- reorder_suggestions.php
   |-- prescription/
   |   `-- prescription-management.php
   |-- purchase/
   |   |-- purchase-history.php
   |   |-- purchase-management.php
   |   |-- purchase-returns.php
   |   |-- settlements.php
   |   `-- supplier-payables.php
   |-- sales/
   |   |-- hold_cart_api.php
   |   |-- invoice.php
   |   |-- returns.php
   |   |-- sales_records.php
   |   `-- sell_medicine.php
   |-- settings/
   |   |-- backup_restore.php
   |   |-- branch_management.php
   |   |-- cash_register.php
   |   |-- customer_ledger.php
   |   |-- financial_reports.php
   |   |-- health_checks.php
   |   |-- permissions_matrix.php
   |   `-- stock_analytics.php
   |-- statistics/
   |   `-- statistics.php
   |-- supplier/
   |   `-- supplier-management.php
   |-- top_sales/
   |   `-- top-selling.php
   |-- update/
   |   `-- update-stock.php
   `-- users/
      |-- activity_log.php
      |-- add_user.php
      |-- manage_users.php
      `-- profile.php
```

High-level folders and responsibilities:

- app: bootstrap, authentication, configuration, database, and models.
- public: browser-accessible pages for dashboards and business modules.
- database: SQL dump and setup/migration utilities.
- database/setup: migration and maintenance scripts.

Main module groups inside public:

- dashboard: role-based home pages.
- inventory: stock views, alerts, bulk import, analytics reports.
- sales: sell medicine, invoices, sales records, returns.
- purchase: purchase flow, settlements, returns, supplier payables.
- prescription: prescription management pages.
- settings: backup/restore, branch management, cash register, permissions matrix, financial reports.
- users: profile, user management, and activity log pages.
- api: lightweight endpoints for CSV export, search, and statistics.

## Prerequisites

- Windows/macOS/Linux with a local web stack (XAMPP recommended).
- PHP 8.0+ (8.2 used in current environment).
- MySQL 8+ or MariaDB 10.4+.
- Web browser (Chrome/Edge/Firefox).

## Installation and Setup

1. Clone or copy this project into your web root.

```bash
git clone <your-repository-url>
```

2. Place the folder at:

```text
C:/xampp/htdocs/pharmaflow_pro
```

3. Create a database named medical_management in phpMyAdmin or MySQL CLI.

4. Import the schema dump:

```text
database/medical_management.sql
```

5. Open configuration file and verify DB credentials:

```text
app/Config/config.php
```

6. Optional but recommended after import: run migration to ensure latest columns/tables/indexes exist.

```text
http://localhost/pharmaflow_pro/database/setup/migrate.php
```

7. Optional admin seed/update script:

```text
http://localhost/pharmaflow_pro/database/setup/seed_admin.php
```

8. Start application:

```text
http://localhost/pharmaflow_pro/public
```

## Configuration

Primary configuration constants are in app/Config/config.php:

- DB_HOST
- DB_NAME
- DB_USER
- DB_PASS
- APP_NAME
- BASE_URL
- BACKUP_RESTORE_PASSWORD

Important production checklist:

- Change BACKUP_RESTORE_PASSWORD before exposing the app.
- Use a strong database password and non-root database user.
- Set secure server and PHP settings for production.
- Keep BASE_URL aligned with your deployment path.

## Usage and Typical Workflows

### Login and Role Access

1. Open the public login page.
2. Sign in with a valid user.
3. You are redirected to a role-specific dashboard.

### Add and Sell Medicine

1. Create or edit medicine records with category, price, stock, and expiry details.
2. Use Sell Medicine page to add items and process the transaction.
3. View generated invoice and sales records for history and audit.

### Purchase and Supplier Flow

1. Add supplier details.
2. Create purchase with item lines and batch/expiry info.
3. Record settlement payments and monitor due amounts.
4. Process purchase returns when needed.

### Return Management

1. Open sales returns screen.
2. Select sale reference and return quantity.
3. Submit for approval and track status/refund details.

### Inventory Control

1. Use stock check and alert center to identify low stock or risk items.
2. Review reorder suggestions and analytics pages.
3. Use bulk CSV import for high-volume inventory updates.

### Backup and Restore

1. Open Backup & Restore in settings.
2. Enter section password.
3. Download full SQL snapshot or restore from SQL file (with confirmation).

## Security and Operational Notes

- CSRF token generation/verification is implemented for POST forms.
- Session cookie hardening and common security headers are enabled.
- Login flow includes rate limiting for repeated failed attempts.
- Activity logs are captured for important operations.
- Backup runs can be tracked using backup metadata tables.

## API Endpoints

Common utility endpoints under public/api:

- export_csv.php
- get_statistics.php
- search.php

These are intended for internal dashboard usage and operational exports.

## Troubleshooting

- Database connection failed:
   Verify DB credentials in app/Config/config.php and ensure MariaDB/MySQL is running.

- Missing columns or tables after old import:
   Run database/setup/migrate.php from browser.

- Backup/restore blocked:
   Confirm BACKUP_RESTORE_PASSWORD matches the entered section password.

- Access denied to a page:
   Check logged-in role and permissions matrix settings.

## Contribution Guide

Contributions are welcome.

Suggested contribution workflow:

1. Fork the repository.
2. Create a feature branch.
3. Keep changes focused and test key flows.
4. Document schema or behavior changes clearly.
5. Submit a pull request with summary, screenshots (if UI), and migration notes.

Recommended quality checks before PR:

- Verify login and role routing.
- Test sales, returns, and purchase workflows impacted by your changes.
- Confirm migrations are idempotent and safe on existing databases.
- Preserve CSRF and role checks on new forms/pages.

## License

No license file is currently included in this repository.

If you plan to open-source this project, add a LICENSE file (for example MIT, Apache-2.0, or GPL-3.0) and update this section accordingly.

## Acknowledgments

- Bootstrap and Bootstrap Icons for UI framework and icons.
- Chart.js for data visualization.
- XAMPP/MariaDB community tooling for local development and testing.
