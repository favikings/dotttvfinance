# DOTT TV Finance & Accounting System — Product Requirements Document

**Version:** 1.0
**Status:** Draft for review
**Owner:** Favikings (ICT Head / Technical Lead, DOTT TV)
**Stack:** PHP 8.2, MySQL 8, Tailwind CSS, vanilla JS + Alpine.js, PWA

---

## 1. Purpose

DOTT TV currently manages finance through a paper ledger (recorded by the Accountant, Ifeoma) using a revolving cash-advance model: management periodically disburses a lump sum, expenses are recorded against it as they occur, and the running balance is topped up (added to, not reset) whenever it runs low. This system has no digital record, no approval controls, and no way for the General Manager or Chairman to review spending without physically reviewing the book.

This system replaces that process with a web application (installable as a PWA) that:

- Digitizes and reconciles fund movement in real time
- Enforces amount-based approval chains so no single person has unlimited spending authority
- Lets the Accountant backfill historical paper-book records without disrupting live controls
- Gives the General Manager and Chairman visibility and sign-off without needing to be in the office
- Gives the Super Admin control over system configuration — including approval thresholds — without needing code changes

---

## 2. Goals & Non-Goals

**Goals**
- Single source of truth for all money in and out of DOTT TV
- Configurable, enforced approval chain by amount tier
- Full audit trail — every record shows who did what, when
- Support the existing revolving-float / running-balance model as-is (not a monthly-reset model)
- Allow historical (paper-book) data entry without breaking live controls or making the historical data invisible to reports
- PWA: installable on desktop and mobile, fast, app-like transitions

**Non-Goals (v1)**
- Offline write access for financial transactions (see §8.2)
- Multi-currency support (flagged as a future consideration; schema will not block it, but v1 UI is Naira-only)
- Direct bank integration / automated reconciliation feeds (manual entry only in v1)
- Public-facing invoicing portal for clients (invoices are recorded and tracked, not sent from the system in v1)

---

## 3. Roles & Permission Model

Two separate, decoupled systems govern control:

1. **RBAC (module access)** — what a role can see and do on each screen.
2. **Approval Rules Engine (money movement)** — who must sign off on a specific transaction, based on amount. This is admin-configurable data, not hardcoded logic.

### 3.1 Roles

| Role | Description |
|---|---|
| Super Admin / Developer | System and technical administration. Full module access. Owns Settings (thresholds, categories, departments, fund accounts, user management). |
| Accountant | Finance operations — records transactions, requests fund top-ups, prepares reports. Approval authority limited to the lowest tier. |
| General Manager | Operational approval — reviews and approves mid-tier expenditure, flags concerns to Chairman. |
| Chairman | Final approval authority on high-value transactions. Full financial visibility (view-only across all modules by default). |

### 3.2 Permission matrix (module × action)

`V` = View, `C` = Create, `E` = Edit, `A` = Approve, `D` = Delete/Void

| Module | Super Admin | Accountant | GM | Chairman |
|---|:---:|:---:|:---:|:---:|
| Dashboard | V | V | V | V |
| Invoices | V C E D | V C E | V A | V |
| Expenses | V | V C E | V A | V A |
| Petty Cash | V | V C E | V | V |
| Fund Top-Ups | V | V C | V A | V A |
| Payments | V | V C | V A | V A |
| Payroll | V | V C E | V A | V |
| Reports | V | V | V | V |
| Settings (thresholds, roles, categories, departments) | V C E D | — | — | — |
| Audit Log | V | — | V (own approvals) | V |
| User Management | V C E D | — | — | — |

Notes:
- Delete is intentionally restricted almost everywhere. Financial records are voided/reversed with a reason, never hard-deleted, except by Super Admin for genuine data-entry errors — and even then the action is logged, not erased.
- Chairman has view access across all modules by default (matches "high-level oversight and financial visibility" from the original role definition) but does not create records.
- This table is the source of truth for `role_permissions` seed data — implement exactly this, don't infer additional access.

### 3.3 Approval rules engine (Super Admin configurable)

Rather than hardcoding thresholds in application logic, approval requirements live in a database table the Super Admin edits from **Settings → Approval Rules**.

**Default seed data:**

| Tier | Amount range | Required approval chain |
|---|---|---|
| 1 | Up to ₦50,000 | Accountant only |
| 2 | ₦50,001 – ₦500,000 | Accountant → GM |
| 3 | Above ₦500,000 | Accountant → GM → Chairman |

**How it works:**
- When an expense is created, the system looks up which tier the amount falls into and generates the required approval steps in order.
- Editing a tier's amount range or required-role chain in Settings takes effect immediately for new transactions — it never rewrites history on already-approved records.
- Each rule change is written to the audit log (old value, new value, changed by, timestamp) because threshold changes are themselves financially significant events.
- The Settings screen enforces basic sanity checks (ranges can't overlap, can't have gaps, upper tier must include all lower approvers — e.g. Tier 3 can't skip GM) but the actual numbers and the number of tiers are fully editable. Super Admin can add a Tier 4 later without a code deployment.

---

## 4. The Fund Model (critical — read before building)

This is **not** a monthly-reset budget system. It is a continuous revolving float:

- Management disburses a lump sum to the Accountant (`fund_topups`)
- Expenses are recorded against the fund, reducing the running balance (`expenses`)
- When the balance runs low, management tops it up — the new amount is **added** to whatever balance remains, not a fresh reset (confirmed in the existing paper ledger: a ₦500 balance plus a ₦500,000 top-up became ₦500,500)
- A "Statement of Expenditure" for any period (e.g. July 2026) is a **date-ranged slice** of one continuous ledger: Opening Balance = running balance at the start date, Funds Received = top-ups in range, Expenditure = approved expenses in range, Closing Balance = running balance at the end date

The system should support multiple named fund accounts (e.g. "Main Operating Float", a separate petty cash float) even though v1 may only actively use one, since the paper records show department-tagged spending that could later warrant separate floats.

---

## 5. Historical Data Import

Ifeoma needs to backfill months of paper-book records so GM/Chairman can review a complete picture, without those historical entries triggering live approval workflows (they've already happened).

**Design:**
- Every expense/top-up record has `is_historical` (boolean) and `source` (`live` / `backfilled`) fields.
- Historical entries can be entered with `status = approved` directly, skipping the approval chain, but still count in balance calculations and reports.
- A dedicated **Bulk Historical Entry** screen (distinct from the live expense form) optimized for fast repeated entry: date, document no. (optional — early paper entries had none), payee, description, department, amount, with Enter-key row advancement and department as a dropdown.
- Historical entries are visually flagged in reports and lists (e.g. a small "Backfilled" badge) so reviewers know which records were entered in real time vs. reconstructed from paper — this preserves audit integrity and matches the known gap where an entry was marked "Not entered on expense tracker" in the physical book.
- Document numbers are not unique/required for historical entries (the paper book shows entries before numbering started) but should be enforced as required + unique for all new live entries going forward.

---

## 6. Core Modules

### 6.1 Dashboard
Total revenue, total expenses, cash balance (per fund account), outstanding invoices, pending payments/approvals awaiting the logged-in user, monthly P&L snapshot, budget utilization by department.

### 6.2 Invoice Management
Invoice number (auto-generated, editable), date, client, description, department, amount, VAT/WHT fields, due date, payment status, payment date.

### 6.3 Expense Management
Expense number (auto), date, employee/vendor (payee), description, department, category, amount, supporting document upload, approval status (driven by the rules engine), payment status.

### 6.4 Petty Cash / Fund Management
Opening balance, cash received (top-ups), cash spent, current balance (all computed from the ledger, never manually overridden), petty-cash vouchers, reconciliation view.

### 6.5 Payments
Payment request, approval chain status, payment voucher generation, payment method, bank account, payment reference, supporting documents. Separated from expense creation — recording an expense and releasing payment for it are distinct steps with distinct approval gates.

### 6.6 Payroll
Employees, salaries, allowances, deductions, loans/advances, net salary calculation, payment status.

### 6.7 Reports
P&L, cash flow, balance sheet, revenue, expenses, receivables, payables, departmental expenses, budget vs. actual, tax reports, and the core **Statement of Expenditure** (opening balance, funds received, approved expenditure, outstanding liabilities, closing balance) matching the format already requested from Ifeoma.

**Fund Ledger** (added post-Phase-3-build, per direct feedback): a full chronological, all-departments transaction ledger — every expense and every fund top-up in one continuous list, ordered by date, with a running balance shown after each entry. This is distinct from the Statement of Expenditure (a period *summary*: opening/closing balance only) — the Ledger is the itemized detail behind that summary, closer in spirit to the original paper book's format. No department filter — this report exists specifically to show the whole picture in one place, departmental breakdowns are what the Departmental Expenses report is for.

All reports exportable as **PDF** (management review, formal submission) and **Excel/.xlsx** (Ifeoma's own reconciliation work, auditor handoff — an editable spreadsheet is more useful than a static PDF for that purpose).

### 6.8 Settings (Super Admin only)
Approval rules/thresholds, departments, expense categories, fund accounts, user management and role assignment, company details, audit log viewer.

---

## 7. Database Schema (entities)

Core tables: `users`, `roles`, `permissions`, `role_permissions`, `departments`, `expense_categories`, `fund_account`, `fund_topups`, `expenses`, `approval_rules`, `expense_approvals`, `payment_vouchers`, `invoices`, `payroll_runs`, `payroll_items`, `audit_log`, `settings` (key-value for general config), `push_subscriptions` (Web Push device registrations — see Tech Spec §15a).

Key design principles:
- `fund_account.current_balance` is a cached value recalculated inside the same DB transaction as any insert into `fund_topups` or `expenses` — never manually editable.
- `approval_rules` drives dynamic `expense_approvals` row generation — no hardcoded thresholds anywhere in application code.
- `audit_log` is append-only. No update/delete permission on this table for any role, including Super Admin, at the database level.
- Full column-level schema and migration SQL to follow as a separate `schema.sql` / migrations document once this PRD is approved.

---

## 8. Non-Functional Requirements

### 8.1 Stack
- Backend: PHP 8.2, MySQL 8, cPanel-compatible shared hosting (consistent with the Staff Hub portal decision)
- Frontend: Tailwind CSS, vanilla JS, Alpine.js for lightweight reactive UI state and transitions (`x-show`, `x-transition`) without introducing a build step or a heavier framework
- PWA: web app manifest, service worker for app-shell caching and installability

### 8.2 PWA & offline strategy
- Cache static assets (HTML shell, CSS, JS, icons) for fast load and installability
- Cache report data for offline **viewing** only
- Do **not** allow offline creation of expenses, approvals, or payments — financial write actions require a live connection to avoid conflicting or duplicated records when connectivity returns. This is a deliberate constraint, not an oversight.

### 8.3 Security & integrity
- Every financial state change (create, approve, reject, void) is logged to `audit_log` with before/after values
- Session-based auth with role checked server-side on every request — never trust client-side role display alone
- File uploads (receipts, vouchers) validated by type/size and stored outside the web root

### 8.4 Animation & UI
- Alpine transitions for panel/modal open-close, approval status changes, and dashboard number updates
- Tailwind's built-in transition utilities for hover/focus states
- Keep transitions under ~250ms — this is a finance tool used daily, not a marketing site; motion should feel responsive, not decorative

---

## 9. Build Phases

**Phase 1 — Foundation & Core Recording**
- Auth, roles, permissions
- Departments, categories, fund accounts, settings module (including approval rules)
- Expense entry (live + historical bulk import)
- Fund top-up recording
- Basic dashboard (balances, recent activity)
- Value delivered: Ifeoma can start recording live and backfill history; running balance is finally digital and accurate.

**Phase 2 — Approval Workflow & Payments**
- Dynamic approval chain generation from `approval_rules`
- GM/Chairman approval queues and actions
- Payment vouchers and payment release
- Audit log viewer
- Value delivered: spending controls are live — no more unlimited accountant authority; GM/Chairman can approve from anywhere.

**Phase 3 — Invoicing, Payroll & Reporting**
- Invoice management
- Payroll module
- Full report suite including the Statement of Expenditure export
- PWA polish (install prompts, offline report viewing, transitions)
- Value delivered: complete financial picture — management gets board-ready reports without asking Ifeoma to compile them manually.

---

## 10. Resolved Decisions

1. **Approval rules edit rights**: Chairman does **not** have edit rights on `approval_rules`. Threshold changes remain Super Admin-only, per the "system/technical administration" role definition. Chairman retains full view access to the current rules and to the audit log entries generated whenever a rule changes.
2. **Fund accounts**: Single float for now (`fund_account` table still supports multiple accounts at the schema level for future-proofing, but v1 will seed and use exactly one — "Main Operating Float").
3. **Rejected expense flow**: A rejected expense returns to the Accountant in a `rejected` state with the rejector's reason attached. From there the Accountant has two actions:
   - **Edit & resubmit** — the record is edited and re-enters the approval chain from Tier 1, with the full history (original submission, rejection reason, edits, resubmission) preserved in `expense_approvals` and `audit_log`. No new expense record is created; it's a status transition on the same record so the audit trail stays intact.
   - **Close** — the Accountant marks the expense `closed` with a closing note (e.g. "vendor cancelled," "duplicate entry"). Closed expenses are excluded from balance calculations and pending-approval queues but remain visible in reports and audit history, clearly labeled.
4. **Document retention**: Confirmed required for tax/audit compliance. Nigerian tax authorities (FIRS) generally expect financial records retained for a minimum of **6 years**. This will be implemented as a configurable Settings value (`document_retention_years`, default 6) rather than hardcoded, so Super Admin can adjust if DOTT TV's specific compliance obligations differ. Supporting documents are never auto-deleted by the system in v1 — retention is enforced by policy/process, not an automatic purge job, since accidentally destroying audit evidence is a far worse failure mode than storage costs.

---

## 11. Rejection & Resubmission Workflow

Because this is new logic beyond the original module list, it's worth spelling out as its own section:

- `expenses.status` includes `rejected` and `closed` as terminal-or-returnable states (see §12 schema for the full enum).
- When any approver in the chain rejects, all *pending* approval steps for that expense are cancelled (not deleted — kept as historical rows), the expense status becomes `rejected`, and the rejecting approver's comment is required, not optional.
- On edit-and-resubmit, the system creates a **new set** of `expense_approvals` rows (fresh Tier 1→N chain based on the current amount, which may have changed during editing) rather than reusing the old ones — this matters because editing the amount could move the expense into a different approval tier.
- The Accountant's dashboard should surface a distinct "Rejected — needs action" queue, separate from "Draft" and "Pending approval," so rejected items don't get lost.
- Closed expenses cannot be reopened by the Accountant alone — reopening (rare, e.g. a vendor situation changes) requires Super Admin intervention and is logged.

---

*Next document: `schema.sql` — full column-level migration script.*