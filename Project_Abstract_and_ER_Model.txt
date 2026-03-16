# PHARMAFLOW PRO - PROJECT ABSTRACT AND ER MODEL

## Revised Abstract

### Project Overview and Purpose
PharmaFlow Pro is a web-based, transactional information system developed to digitize and optimize core pharmacy operations. The project addresses common retail-pharmacy pain points such as fragmented stock tracking, manual billing errors, weak return traceability, delayed replenishment decisions, and limited operational visibility.

The platform unifies inventory control, sales billing, prescription processing, supplier and purchase workflows, return handling, financial/cash tracking, and role-based access management into a single system. Its primary objective is to improve service speed, data reliability, and decision quality while reducing operational risk.

### Scope
The implemented scope includes:

1. Medicine master management with categories, pricing, tax metadata, barcode support, and stock policy parameters.
2. Inventory lifecycle tracking through stock movement logs, expiration management, and batch-level records.
3. Sales processing with invoice references, discount/tax breakdown, payment metadata, and customer linkage.
4. Sales return management with status flow, refund audit fields, and processor accountability.
5. Supplier procurement workflows, including purchase entries, line items, settlements, and purchase returns.
6. Prescription management with prescription headers, item-level medicine mapping, and status history.
7. Operational governance via user roles, permission matrix, activity logs, backup registry, and alerts.
8. Support modules for customer ledger, branch structure, and stock transfer entities.

### Methodology
The solution follows a modular, data-centric design approach:

1. Requirement decomposition into operational domains: inventory, sales, purchasing, prescriptions, and governance.
2. Relational schema modeling in MySQL/MariaDB using InnoDB with primary keys, indexes, and foreign key constraints where strict integrity is required.
3. Workflow-first implementation in PHP, with role-aware routing and CRUD services mapped to domain models.
4. Security baseline integration using session hardening, CSRF verification, role checks, and login rate limiting.
5. Incremental schema evolution through migration scripts and backward-compatible updates.

### Key Contributions and Outcomes
The project contributes a practical, production-oriented pharmacy workflow model with the following strengths:

1. End-to-end operational traceability from purchase to sale to return.
2. Improved stock governance through reorder and expiration-aware structures.
3. Better financial transparency via payment, due, settlement, and refund fields.
4. Accountability mechanisms through activity logs, processor fields, and backup-run tracking.
5. Extensible architecture that supports multi-branch and analytics-driven enhancements.

In summary, the system demonstrates how a normalized relational backbone and role-based application layer can materially improve pharmacy efficiency, compliance readiness, and managerial insight.

## Revised ER Model

### 1. ER Modeling Objective
The ER model is designed to represent business-critical pharmacy data with clear entity boundaries, enforceable relationships, and query-efficient access patterns for day-to-day transaction workloads.

### 2. Core Entity Groups

#### A. Identity, Access, and Governance
1. USERS
   - Primary key: user_id
   - Core attributes: email, password_hash, role, is_active, profile fields

2. ROLE_PERMISSIONS
   - Primary key: id
   - Unique pair: role_name + permission_key
   - Purpose: permission matrix by role

3. ACTIVITY_LOGS
   - Primary key: id
   - Foreign key: user_id -> USERS.user_id
   - Purpose: audit trail of user actions

4. ALERT_EVENTS
   - Primary key: id
   - Foreign key: acknowledged_by -> USERS.user_id
   - Purpose: alert lifecycle and acknowledgment tracking

5. BACKUP_RUNS
   - Primary key: id
   - Foreign key: created_by -> USERS.user_id
   - Purpose: backup execution metadata and integrity evidence

#### B. Master Data
1. MEDICINE_CATEGORIES
   - Primary key: id
   - Unique: name

2. MEDICINES
   - Primary key: id
   - Foreign key: category_id -> MEDICINE_CATEGORIES.id
   - Core attributes: price, stock, reorder policy, tax metadata, barcode, prescription flag

3. SUPPLIERS
   - Primary key: id
   - Core attributes: contact and address information

4. CUSTOMERS
   - Primary key: id
   - Unique: mobile
   - Purpose: repeat-customer ledger and summary metrics

5. BRANCHES
   - Primary key: id
   - Unique: code
   - Purpose: multi-branch organization support

#### C. Inventory and Stock Control
1. INVENTORY_LOGS
   - Primary key: id
   - Foreign key: medicine_id -> MEDICINES.id
   - Purpose: stock in/out movement ledger

2. MEDICINE_BATCHES
   - Primary key: id
   - Foreign key: medicine_id -> MEDICINES.id
   - Purpose: batch-level quantity and expiry tracking

3. EXPIRATION_MANAGEMENT
   - Primary key: id
   - Foreign key: medicine_id -> MEDICINES.id
   - Purpose: explicit expiry monitoring records

4. STOCK_TRANSFERS
   - Primary key: id
   - Purpose: transfer header between branches

5. STOCK_TRANSFER_ITEMS
   - Primary key: id
   - Foreign keys:
     - transfer_id -> STOCK_TRANSFERS.id
     - medicine_id -> MEDICINES.id
   - Purpose: transfer line items

#### D. Sales and Returns
1. SALES
   - Primary key: id
   - Foreign key: medicine_id -> MEDICINES.id
   - Core attributes: invoice_number, pricing/tax fields, payment fields, customer fields, timestamps

2. RETURNS
   - Primary key: id
   - Foreign keys:
     - sale_id -> SALES.id
     - medicine_id -> MEDICINES.id
     - processed_by -> USERS.user_id
   - Purpose: return request, approval, and refund audit

3. HELD_CARTS
   - Primary key: id
   - Foreign key: created_by -> USERS.user_id
   - Unique: hold_code
   - Purpose: temporary cart persistence and resume flow

#### E. Prescription Domain
1. PRESCRIPTIONS
   - Primary key: id
   - Unique: prescription_code
   - Core attributes: patient/doctor, date, status, notes

2. PRESCRIPTION_ITEMS
   - Primary key: id
   - Foreign keys:
     - prescription_id -> PRESCRIPTIONS.id
     - medicine_id -> MEDICINES.id

3. PRESCRIBED_MEDICINES
   - Primary key: id
   - Foreign keys:
     - prescription_id -> PRESCRIPTIONS.id
     - medicine_id -> MEDICINES.id
   - Purpose: legacy or alternate mapping of prescription to medicines

4. PRESCRIPTION_MEDICINES
   - Primary key: id
   - Foreign keys:
     - prescription_id -> PRESCRIPTIONS.id
     - medicine_id -> MEDICINES.id
   - Purpose: compatibility table retained for existing workflows

5. PRESCRIPTION_STATUS_LOGS
   - Primary key: id
   - Foreign key: prescription_id -> PRESCRIPTIONS.id
   - Purpose: temporal history of status transitions

#### F. Procurement and Payables
1. PURCHASES
   - Primary key: id
   - Foreign keys:
     - supplier_id -> SUPPLIERS.id
     - created_by -> USERS.user_id
   - Purpose: purchase header, totals, payment status

2. PURCHASE_ITEMS
   - Primary key: id
   - Foreign keys:
     - purchase_id -> PURCHASES.id
     - medicine_id -> MEDICINES.id
   - Purpose: itemized purchase lines with batch and expiry fields

3. PURCHASE_PAYMENTS
   - Primary key: id
   - Foreign keys:
     - purchase_id -> PURCHASES.id
     - created_by -> USERS.user_id
   - Purpose: settlement and payment history

4. PURCHASE_RETURNS
   - Primary key: id
   - Foreign keys:
     - purchase_id -> PURCHASES.id
     - supplier_id -> SUPPLIERS.id
     - created_by -> USERS.user_id
   - Purpose: return header and credit tracking

5. PURCHASE_RETURN_ITEMS
   - Primary key: id
   - Foreign keys:
     - purchase_return_id -> PURCHASE_RETURNS.id
     - medicine_id -> MEDICINES.id
   - Purpose: returned line items and valuation

#### G. Cash and Expense Tracking
1. CASH_REGISTER_SESSIONS
   - Primary key: id
   - Unique: business_date
   - Foreign keys:
     - created_by -> USERS.user_id
     - closed_by -> USERS.user_id
   - Purpose: daily opening/closing reconciliation

2. CASH_MOVEMENTS
   - Primary key: id
   - Foreign key: created_by -> USERS.user_id
   - Purpose: manual in/out cash movements

3. EXPENSE_ENTRIES
   - Primary key: id
   - Foreign key: created_by -> USERS.user_id
   - Purpose: operating expense ledger

### 3. Relationship Summary (Cardinality)

1. One MEDICINE_CATEGORY to many MEDICINES.
2. One MEDICINE to many INVENTORY_LOGS, MEDICINE_BATCHES, SALES, RETURNS, PRESCRIPTION_ITEMS, and PURCHASE_ITEMS.
3. One PRESCRIPTION to many PRESCRIPTION_ITEMS and PRESCRIPTION_STATUS_LOGS.
4. One SUPPLIER to many PURCHASES and PURCHASE_RETURNS.
5. One PURCHASE to many PURCHASE_ITEMS and PURCHASE_PAYMENTS.
6. One PURCHASE_RETURN to many PURCHASE_RETURN_ITEMS.
7. One SALE to many RETURNS.
8. One USER to many ACTIVITY_LOGS and governance/transaction records (created_by or processed_by).
9. One STOCK_TRANSFER to many STOCK_TRANSFER_ITEMS.

### 4. Design Quality and Best-Practice Alignment

1. Normalization: transactional data is decomposed into header-line structures to reduce redundancy.
2. Referential integrity: foreign keys are used for critical relationships to prevent orphan records.
3. Auditability: operational entities include created_at and processor/creator references for traceability.
4. Performance: indexes exist on frequent filters and joins such as dates, status, invoice/payment fields, and FK columns.
5. Extensibility: branch, alert, backup, and permission entities provide forward-compatible design for scaling and governance.

### 5. High-Level ER View

```
USERS (1) --------< ACTIVITY_LOGS (M)
USERS (1) --------< PURCHASES / PURCHASE_PAYMENTS / PURCHASE_RETURNS / RETURNS / CASH_* / EXPENSES (M)

MEDICINE_CATEGORIES (1) ----< MEDICINES (M)
MEDICINES (1) --------------< INVENTORY_LOGS / MEDICINE_BATCHES / SALES / RETURNS / PURCHASE_ITEMS / PRESCRIPTION_ITEMS (M)

SUPPLIERS (1) --------------< PURCHASES (M) ----< PURCHASE_ITEMS (M)
PURCHASES (1) --------------< PURCHASE_PAYMENTS (M)
PURCHASES (1) --------------< PURCHASE_RETURNS (M) ----< PURCHASE_RETURN_ITEMS (M)

PRESCRIPTIONS (1) ----------< PRESCRIPTION_ITEMS (M)
PRESCRIPTIONS (1) ----------< PRESCRIPTION_STATUS_LOGS (M)

SALES (1) ------------------< RETURNS (M)
STOCK_TRANSFERS (1) --------< STOCK_TRANSFER_ITEMS (M)
```

This ER model supports operational consistency, clear ownership of transactional records, and reliable reporting across pharmacy, procurement, and governance domains.
