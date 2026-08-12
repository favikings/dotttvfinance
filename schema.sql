-- =====================================================================
-- DOTT TV Finance & Accounting System — Database Schema
-- MySQL 8.0+ | InnoDB | utf8mb4
-- Companion to: DOTT-TV-Finance-System-PRD.md
-- v1.2 — balance is a computed view (not a cached+triggered column),
-- resubmission cycles are explicit, audit_log is tamper-evident via
-- hash chain, and fund top-ups use a single-approver sign-off rather
-- than a tiered chain (money in carries less risk than money out).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- 1. ROLES & PERMISSIONS (RBAC — module/screen access)
-- =====================================================================

CREATE TABLE roles (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50) NOT NULL UNIQUE,   -- super_admin | accountant | gm | chairman
    description     VARCHAR(255) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module          VARCHAR(50) NOT NULL,          -- expenses | invoices | payroll | settings | ...
    action          VARCHAR(20) NOT NULL,          -- view | create | edit | approve | delete
    description     VARCHAR(255) NULL,
    UNIQUE KEY uq_module_action (module, action)
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id         INT UNSIGNED NOT NULL,
    permission_id   INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_role_permission (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 2. USERS, DEPARTMENTS, CATEGORIES
-- =====================================================================

CREATE TABLE departments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL UNIQUE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE expense_categories (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL UNIQUE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id         INT UNSIGNED NOT NULL,
    department_id   INT UNSIGNED NULL,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login_at   DATETIME NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 3. FUND ACCOUNT & TOP-UPS (revolving float — see PRD §4)
--
-- REVISED: no cached current_balance column here anymore. Balance is
-- computed live via the fund_balances VIEW at the bottom of this file.
-- At DOTT TV's transaction volume (single float, a few hundred entries
-- a month) a live SUM is instant, and it removes an entire class of
-- drift bugs that come from maintaining the same number in two places
-- (a trigger AND app code) — if either has a bug, you get two sources
-- of truth instead of one, which is worse than neither being cached.
-- =====================================================================

CREATE TABLE fund_account (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(100) NOT NULL,          -- 'Main Operating Float' (single float in v1)
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- TOP-UP APPROVAL — resolved as a simpler single-approver sign-off,
-- deliberately lighter than the expense chain: money coming IN to the
-- float doesn't carry the same misuse risk as money going OUT, so it
-- doesn't need a tiered Accountant->GM->Chairman gate. Either a GM or
-- a Chairman (both hold fund_topups.approve per the PRD §3.2 matrix)
-- can approve — whoever gets to it first — not both in sequence.
CREATE TABLE fund_topups (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fund_account_id     INT UNSIGNED NOT NULL,
    amount              DECIMAL(14,2) NOT NULL,
    date                DATE NOT NULL,
    requested_by        INT UNSIGNED NOT NULL,          -- Accountant
    status              ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    approved_by         INT UNSIGNED NULL,               -- whichever GM/Chairman actioned it; NULL until approved
    approved_at         DATETIME NULL,
    rejected_reason     TEXT NULL,
    reference           VARCHAR(100) NULL,
    note                TEXT NULL,
    is_historical       TINYINT(1) NOT NULL DEFAULT 0,   -- historical entries are inserted with status='approved' directly
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_topup_fund FOREIGN KEY (fund_account_id) REFERENCES fund_account(id),
    CONSTRAINT fk_topup_requested_by FOREIGN KEY (requested_by) REFERENCES users(id),
    CONSTRAINT fk_topup_approved_by FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_topup_date (date),
    INDEX idx_topup_status (status)
) ENGINE=InnoDB;

-- =====================================================================
-- 4. APPROVAL RULES ENGINE (Super Admin configurable — see PRD §3.3)
-- =====================================================================

CREATE TABLE approval_rules (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tier_order          INT UNSIGNED NOT NULL,           -- 1, 2, 3... determines sequence
    min_amount          DECIMAL(14,2) NOT NULL,
    max_amount          DECIMAL(14,2) NULL,              -- NULL = open-ended (highest tier, "above X")
    required_roles      JSON NOT NULL,                   -- e.g. ["accountant","gm","chairman"], ordered
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tier_order (tier_order)
) ENGINE=InnoDB;

-- =====================================================================
-- 5. EXPENSES + APPROVAL CHAIN
--
-- REVISED: expenses now tracks resubmission_count. expense_approvals
-- now tracks cycle_number, so tier rows from different resubmission
-- cycles never collide — a rejected-then-resubmitted expense's second
-- Tier 2 row is a distinct record from its first, both queryable and
-- both preserved for audit history.
-- =====================================================================

CREATE TABLE expenses (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_no          VARCHAR(30) NULL UNIQUE,         -- required+unique for live entries; nullable for early historical
    date                DATE NOT NULL,
    document_no         VARCHAR(30) NULL,                -- optional for historical (paper book had none pre-numbering)
    payee               VARCHAR(150) NOT NULL,
    description         TEXT NOT NULL,
    department_id       INT UNSIGNED NOT NULL,
    category_id         INT UNSIGNED NULL,
    amount              DECIMAL(14,2) NOT NULL,
    fund_account_id     INT UNSIGNED NOT NULL,
    supporting_doc_path VARCHAR(255) NULL,               -- stored outside web root
    status              ENUM(
                            'draft',
                            'pending_gm',
                            'pending_chairman',
                            'approved',
                            'rejected',
                            'closed',
                            'paid'
                        ) NOT NULL DEFAULT 'draft',
    resubmission_count  INT UNSIGNED NOT NULL DEFAULT 0, -- 0 = original submission, increments on each edit-resubmit
    is_historical       TINYINT(1) NOT NULL DEFAULT 0,
    source              ENUM('live','backfilled') NOT NULL DEFAULT 'live',
    rejected_reason     TEXT NULL,
    closed_reason       TEXT NULL,
    created_by          INT UNSIGNED NOT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_expense_department FOREIGN KEY (department_id) REFERENCES departments(id),
    CONSTRAINT fk_expense_category FOREIGN KEY (category_id) REFERENCES expense_categories(id),
    CONSTRAINT fk_expense_fund FOREIGN KEY (fund_account_id) REFERENCES fund_account(id),
    CONSTRAINT fk_expense_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_expense_date (date),
    INDEX idx_expense_status (status),
    INDEX idx_expense_department (department_id)
) ENGINE=InnoDB;

-- One row per required approval step, per resubmission cycle, for a
-- given expense. On rejection, remaining 'pending' rows in that cycle
-- become 'cancelled' (kept, not deleted). On edit-and-resubmit, the
-- app increments expenses.resubmission_count and inserts a FRESH set
-- of rows at cycle_number = the new count — never reuses old rows,
-- since an edited amount may move the expense into a different tier.
CREATE TABLE expense_approvals (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_id          INT UNSIGNED NOT NULL,
    cycle_number        INT UNSIGNED NOT NULL DEFAULT 0, -- matches expenses.resubmission_count at time of chain creation
    approval_rule_id    INT UNSIGNED NULL,               -- which rule generated this chain (nullable for historical)
    tier_order          INT UNSIGNED NOT NULL,
    required_role_id    INT UNSIGNED NOT NULL,
    approver_id         INT UNSIGNED NULL,               -- filled in once actioned
    action              ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    comment             TEXT NULL,
    acted_at            DATETIME NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ea_expense FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
    CONSTRAINT fk_ea_rule FOREIGN KEY (approval_rule_id) REFERENCES approval_rules(id),
    CONSTRAINT fk_ea_required_role FOREIGN KEY (required_role_id) REFERENCES roles(id),
    CONSTRAINT fk_ea_approver FOREIGN KEY (approver_id) REFERENCES users(id),
    UNIQUE KEY uq_expense_cycle_tier (expense_id, cycle_number, tier_order),
    INDEX idx_ea_expense (expense_id),
    INDEX idx_ea_status (action)
) ENGINE=InnoDB;

-- =====================================================================
-- 6. PAYMENTS
-- =====================================================================

CREATE TABLE payment_vouchers (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_id          INT UNSIGNED NOT NULL UNIQUE,     -- one voucher per approved expense
    voucher_no          VARCHAR(30) NOT NULL UNIQUE,
    payment_method      ENUM('cash','bank_transfer','cheque','pos') NOT NULL,
    bank_account        VARCHAR(100) NULL,
    payment_reference   VARCHAR(100) NULL,
    supporting_doc_path VARCHAR(255) NULL,
    paid_by             INT UNSIGNED NOT NULL,
    paid_at             DATETIME NOT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pv_expense FOREIGN KEY (expense_id) REFERENCES expenses(id),
    CONSTRAINT fk_pv_paid_by FOREIGN KEY (paid_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 7. INVOICES (revenue side)
-- =====================================================================

CREATE TABLE invoices (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_no          VARCHAR(30) NOT NULL UNIQUE,
    date                DATE NOT NULL,
    client              VARCHAR(150) NOT NULL,
    description         TEXT NULL,
    department_id       INT UNSIGNED NULL,
    amount              DECIMAL(14,2) NOT NULL,
    vat_amount          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    wht_amount          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    due_date            DATE NULL,
    payment_status      ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
    payment_date        DATE NULL,
    created_by          INT UNSIGNED NOT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoice_department FOREIGN KEY (department_id) REFERENCES departments(id),
    CONSTRAINT fk_invoice_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_invoice_date (date),
    INDEX idx_invoice_status (payment_status)
) ENGINE=InnoDB;

-- =====================================================================
-- 8. PAYROLL
-- =====================================================================

CREATE TABLE payroll_runs (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_month        TINYINT UNSIGNED NOT NULL,
    period_year         SMALLINT UNSIGNED NOT NULL,
    status              ENUM('draft','approved','paid') NOT NULL DEFAULT 'draft',
    created_by          INT UNSIGNED NOT NULL,
    approved_by         INT UNSIGNED NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_period (period_month, period_year),
    CONSTRAINT fk_payroll_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_payroll_approved_by FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE payroll_items (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payroll_run_id      INT UNSIGNED NOT NULL,
    employee_name       VARCHAR(150) NOT NULL,           -- v1: free text; can FK to a future `employees` table
    department_id       INT UNSIGNED NULL,
    basic_salary        DECIMAL(14,2) NOT NULL,
    allowances          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    deductions          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    loan_deduction      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    net_salary          DECIMAL(14,2) GENERATED ALWAYS AS
                             (basic_salary + allowances - deductions - loan_deduction) STORED,
    payment_status      ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pi_run FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
    CONSTRAINT fk_pi_department FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 9. AUDIT LOG (append-only, tamper-evident — see PRD §8.3)
--
-- REVISED: rather than relying solely on revoking UPDATE/DELETE grants
-- (fragile on budget cPanel hosting, which often provides exactly one
-- MySQL user with full privileges and no way to create a restricted
-- one), each row carries prev_hash + row_hash forming a hash chain.
-- The app computes:
--   row_hash = SHA2(CONCAT_WS('|', user_id, action, entity_type,
--              entity_id, before_json, after_json, created_at_iso,
--              prev_hash), 256)
-- using the previous row's row_hash as this row's prev_hash (the very
-- first row uses a genesis string of 64 zeros). If anyone edits a row
-- directly in the database — bypassing the app entirely — every
-- row_hash after that point stops matching on recomputation, which a
-- simple verification script (walk the table, recompute, compare) will
-- catch. This works regardless of what DB privileges the host allows.
-- =====================================================================

CREATE TABLE audit_log (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NULL,
    action              VARCHAR(50) NOT NULL,       -- create | approve | reject | close | edit | login | settings_change
    entity_type         VARCHAR(50) NOT NULL,       -- expenses | fund_topups | approval_rules | users | ...
    entity_id           INT UNSIGNED NULL,
    before_json         JSON NULL,
    after_json          JSON NULL,
    ip_address          VARCHAR(45) NULL,
    prev_hash           CHAR(64) NULL,               -- row_hash of the previous audit_log row (NULL only for the very first row)
    row_hash            CHAR(64) NOT NULL,            -- SHA-256 of this row's data + prev_hash, computed by the app before insert
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_date (created_at)
) ENGINE=InnoDB;

-- Still worth doing IF your cPanel plan supports a second, restricted
-- MySQL user (some do): REVOKE UPDATE, DELETE ON dott_finance.audit_log
-- FROM 'app_user'@'%'; — the hash chain and the privilege revocation
-- are complementary, not either/or. Use whichever your hosting allows;
-- the hash chain works everywhere.

-- =====================================================================
-- 10. SETTINGS (key-value, Super Admin configurable — see PRD §6.8)
-- =====================================================================

CREATE TABLE settings (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key`               VARCHAR(100) NOT NULL UNIQUE,
    `value`             TEXT NULL,
    description         VARCHAR(255) NULL,
    updated_by          INT UNSIGNED NULL,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 11. FUND BALANCE VIEW (replaces cached column + triggers)
--
-- Balance = all top-ups, minus expenses that have actually drawn down
-- the float (status approved/paid, OR historical — already spent in
-- the real world before this system existed). Draft/pending/rejected/
-- closed expenses never reduce the balance.
-- =====================================================================

CREATE VIEW fund_balances AS
SELECT
    fa.id AS fund_account_id,
    fa.name AS fund_account_name,
    COALESCE(t.total_topups, 0.00) AS total_topups,
    COALESCE(e.total_spent, 0.00) AS total_spent,
    COALESCE(t.total_topups, 0.00) - COALESCE(e.total_spent, 0.00) AS current_balance
FROM fund_account fa
LEFT JOIN (
    SELECT fund_account_id, SUM(amount) AS total_topups
    FROM fund_topups
    WHERE status = 'approved' OR is_historical = 1
    GROUP BY fund_account_id
) t ON t.fund_account_id = fa.id
LEFT JOIN (
    SELECT fund_account_id, SUM(amount) AS total_spent
    FROM expenses
    WHERE status IN ('approved','paid') OR is_historical = 1
    GROUP BY fund_account_id
) e ON e.fund_account_id = fa.id;

-- Usage: SELECT current_balance FROM fund_balances WHERE fund_account_id = 1;
-- For a date-ranged Statement of Expenditure (PRD §4), filter the two
-- inner subqueries by date range instead — opening balance is simply
-- this same calculation run with an end date of (period_start - 1 day).

-- =====================================================================
-- 12. SEED DATA
-- =====================================================================

INSERT INTO roles (name, description) VALUES
    ('super_admin', 'System and technical administration'),
    ('accountant', 'Finance operations, records, reconciliation, reports'),
    ('gm', 'Operational approval, review and flagging'),
    ('chairman', 'High-level approval, oversight and financial visibility');

INSERT INTO fund_account (name, is_active) VALUES
    ('Main Operating Float', 1);

-- Default three-tier approval chain (PRD §3.3) — editable by Super Admin
-- via Settings, never hardcoded in application code.
INSERT INTO approval_rules (tier_order, min_amount, max_amount, required_roles, is_active) VALUES
    (1, 0.00,       50000.00,  JSON_ARRAY('accountant'), 1),
    (2, 50000.01,   500000.00, JSON_ARRAY('accountant','gm'), 1),
    (3, 500000.01,  NULL,      JSON_ARRAY('accountant','gm','chairman'), 1);

INSERT INTO settings (`key`, `value`, description) VALUES
    ('company_name', 'DOTT TV', 'Displayed on reports and vouchers'),
    ('currency_code', 'NGN', 'v1 is Naira-only; schema allows future multi-currency'),
    ('document_retention_years', '6', 'Minimum retention period for supporting documents (FIRS guidance — confirm with DOTT TV''s auditor if different)'),
    ('fiscal_year_start_month', '1', 'Month (1-12) the fiscal year begins, used for P&L period boundaries');

-- Full permission set — module x action, matching PRD §3.2 matrix exactly.
INSERT INTO permissions (module, action) VALUES
    ('dashboard','view'),
    ('invoices','view'), ('invoices','create'), ('invoices','edit'), ('invoices','approve'), ('invoices','delete'),
    ('expenses','view'), ('expenses','create'), ('expenses','edit'), ('expenses','approve'),
    ('petty_cash','view'), ('petty_cash','create'), ('petty_cash','edit'),
    ('fund_topups','view'), ('fund_topups','create'), ('fund_topups','approve'),
    ('payments','view'), ('payments','create'), ('payments','approve'),
    ('payroll','view'), ('payroll','create'), ('payroll','edit'), ('payroll','approve'),
    ('reports','view'),
    ('settings','view'), ('settings','create'), ('settings','edit'), ('settings','delete'),
    ('audit_log','view'),
    ('users','view'), ('users','create'), ('users','edit'), ('users','delete');

-- role_permissions — generated directly from the PRD §3.2 matrix.
-- One INSERT...SELECT joining a literal (role, module, action) list
-- against the roles and permissions tables, so nothing is hand-typed
-- as raw IDs and the mapping is easy to audit against the PRD table.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM (
    -- Dashboard
    SELECT 'super_admin' AS role_name, 'dashboard' AS module, 'view' AS action
    UNION ALL SELECT 'accountant','dashboard','view'
    UNION ALL SELECT 'gm','dashboard','view'
    UNION ALL SELECT 'chairman','dashboard','view'
    -- Invoices
    UNION ALL SELECT 'super_admin','invoices','view'
    UNION ALL SELECT 'super_admin','invoices','create'
    UNION ALL SELECT 'super_admin','invoices','edit'
    UNION ALL SELECT 'super_admin','invoices','delete'
    UNION ALL SELECT 'accountant','invoices','view'
    UNION ALL SELECT 'accountant','invoices','create'
    UNION ALL SELECT 'accountant','invoices','edit'
    UNION ALL SELECT 'gm','invoices','view'
    UNION ALL SELECT 'gm','invoices','approve'
    UNION ALL SELECT 'chairman','invoices','view'
    -- Expenses
    UNION ALL SELECT 'super_admin','expenses','view'
    UNION ALL SELECT 'accountant','expenses','view'
    UNION ALL SELECT 'accountant','expenses','create'
    UNION ALL SELECT 'accountant','expenses','edit'
    UNION ALL SELECT 'gm','expenses','view'
    UNION ALL SELECT 'gm','expenses','approve'
    UNION ALL SELECT 'chairman','expenses','view'
    UNION ALL SELECT 'chairman','expenses','approve'
    -- Petty Cash
    UNION ALL SELECT 'super_admin','petty_cash','view'
    UNION ALL SELECT 'accountant','petty_cash','view'
    UNION ALL SELECT 'accountant','petty_cash','create'
    UNION ALL SELECT 'accountant','petty_cash','edit'
    UNION ALL SELECT 'gm','petty_cash','view'
    UNION ALL SELECT 'chairman','petty_cash','view'
    -- Fund Top-Ups
    UNION ALL SELECT 'super_admin','fund_topups','view'
    UNION ALL SELECT 'accountant','fund_topups','view'
    UNION ALL SELECT 'accountant','fund_topups','create'
    UNION ALL SELECT 'gm','fund_topups','view'
    UNION ALL SELECT 'gm','fund_topups','approve'
    UNION ALL SELECT 'chairman','fund_topups','view'
    UNION ALL SELECT 'chairman','fund_topups','approve'
    -- Payments
    UNION ALL SELECT 'super_admin','payments','view'
    UNION ALL SELECT 'accountant','payments','view'
    UNION ALL SELECT 'accountant','payments','create'
    UNION ALL SELECT 'gm','payments','view'
    UNION ALL SELECT 'gm','payments','approve'
    UNION ALL SELECT 'chairman','payments','view'
    UNION ALL SELECT 'chairman','payments','approve'
    -- Payroll
    UNION ALL SELECT 'super_admin','payroll','view'
    UNION ALL SELECT 'accountant','payroll','view'
    UNION ALL SELECT 'accountant','payroll','create'
    UNION ALL SELECT 'accountant','payroll','edit'
    UNION ALL SELECT 'gm','payroll','view'
    UNION ALL SELECT 'gm','payroll','approve'
    UNION ALL SELECT 'chairman','payroll','view'
    -- Reports
    UNION ALL SELECT 'super_admin','reports','view'
    UNION ALL SELECT 'accountant','reports','view'
    UNION ALL SELECT 'gm','reports','view'
    UNION ALL SELECT 'chairman','reports','view'
    -- Settings (Super Admin only)
    UNION ALL SELECT 'super_admin','settings','view'
    UNION ALL SELECT 'super_admin','settings','create'
    UNION ALL SELECT 'super_admin','settings','edit'
    UNION ALL SELECT 'super_admin','settings','delete'
    -- Audit Log
    UNION ALL SELECT 'super_admin','audit_log','view'
    UNION ALL SELECT 'gm','audit_log','view'
    UNION ALL SELECT 'chairman','audit_log','view'
    -- User Management (Super Admin only)
    UNION ALL SELECT 'super_admin','users','view'
    UNION ALL SELECT 'super_admin','users','create'
    UNION ALL SELECT 'super_admin','users','edit'
    UNION ALL SELECT 'super_admin','users','delete'
) AS matrix
JOIN roles r ON r.name = matrix.role_name
JOIN permissions p ON p.module = matrix.module AND p.action = matrix.action;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- End of schema.
-- =====================================================================
