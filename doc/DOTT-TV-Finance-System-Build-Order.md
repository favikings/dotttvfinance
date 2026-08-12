# DOTT TV Finance & Accounting System — Build Order

**Version:** 1.0
**Status:** Ready for execution
**Owner:** Favikings (ICT Head / Technical Lead, DOTT TV)
**Companion documents:** `DOTT-TV-Finance-System-PRD.md`, `schema.sql`, `DOTT-TV-Finance-System-Tech-Spec.md`, `design-system-dotttv.md`

---

## How to use this document

This is a sequential, checkbox-driven task list — hand a phase's section directly to Cursor/Codex as a working brief, or work through it yourself. Tasks within a phase are ordered so each one unblocks the next (e.g. auth before permissions, permissions before approval queues). Don't skip ahead across phases — each phase ends with a "value delivered" gate that should actually be true before starting the next.

---

## Phase 0 — Environment & Scaffolding

Not called out as its own phase in the PRD, but everything in Phase 1 depends on it existing first.

- [ ] Initialize git repo, `.gitignore` (`vendor/`, `.env`, `storage/logs/*`, `storage/uploads/*`)
- [ ] `composer init`, add dependencies from Tech Spec §24 (`vlucas/phpdotenv`, `dompdf/dompdf`, `phpmailer/phpmailer`, `phpunit/phpunit` dev)
- [ ] Create folder structure exactly per Tech Spec §4 (`public_html/`, `app/`, `storage/` — confirm `app/` and `storage/` sit outside the subdomain's web root)
- [ ] `.env.example` with placeholder DB/SMTP/secret keys; real `.env` created locally, never committed
- [ ] Create MySQL database + user via cPanel, run `schema.sql` in full, confirm all 17 tables + `fund_balances` view + seed data landed correctly
- [ ] `Database.php` PDO singleton, confirm a test query works against the seeded `roles` table
- [ ] Set up Tailwind (standalone CLI) with the Horizon Finance design tokens — see **Design System Integration** below
- [ ] Build the base app shell layout (`views/layouts/app.php`): fixed 260px navy sidebar (`primary` token) + fluid light-canvas content area (`surface` token), per the design system's "Deep Navigation / Light Content" pattern
- [ ] `manifest.json` + placeholder icons + empty `sw.js` (app-shell caching wired up properly in Phase 3 per Tech Spec §17, but the files should exist from day one so installability can be tested early)
- [ ] Confirm HTTPS + `.htaccess` front-controller rewrite works on the actual subdomain before writing any business logic

### Design System Integration

Pull the design tokens from `design-system-dotttv.md`'s frontmatter directly into your Tailwind config (v4 CSS-first `@theme` block, or a `tailwind.config.js` `theme.extend` if staying on v3 — either works with the standalone CLI):

```css
@theme {
  --color-surface: #faf8ff;
  --color-surface-dim: #dbd9e0;
  --color-surface-container-lowest: #ffffff;
  --color-surface-container-low: #f4f3f9;
  --color-surface-container: #efedf3;
  --color-surface-container-high: #e9e7ee;
  --color-surface-container-highest: #e3e1e8;
  --color-on-surface: #1a1b20;
  --color-on-surface-variant: #444650;
  --color-outline: #757682;
  --color-outline-variant: #c5c6d2;

  --color-primary: #000d35;
  --color-on-primary: #ffffff;
  --color-primary-container: #001f63;
  --color-on-primary-container: #7489d1;

  --color-secondary: #006782;
  --color-on-secondary: #ffffff;
  --color-secondary-container: #11cbfd;
  --color-on-secondary-container: #005268;

  --color-error: #ba1a1a;
  --color-on-error: #ffffff;
  --color-error-container: #ffdad6;
  --color-on-error-container: #93000a;

  --font-sans: 'Inter', sans-serif;
  --radius-sm: 0.25rem;
  --radius-DEFAULT: 0.5rem;
  --radius-md: 0.75rem;
  --radius-lg: 1rem;
  --radius-xl: 1.5rem;
  --radius-full: 9999px;

  --spacing-sidebar-width: 260px;
  --spacing-card-padding: 1.5rem;
  --spacing-grid-gutter: 1.25rem;
}
```

- Load Inter via self-hosted `@font-face` or a Google Fonts `<link>` — the design system specifies it exclusively, no fallback stack substitutions.
- **Sidebar** (per design system §Layout): fixed 260px, `primary` background, active nav item = soft-tinted overlay (10–15% white opacity) + left vertical bar, 16px icons.
- **Cards**: white (`surface-container-lowest`), `rounded-lg` (1rem), 1px `outline-variant` border, soft ambient shadow only (0px 4px 12px, 4% opacity) — no heavy drop shadows anywhere per the "Tonal Layering" elevation model.
- **Status badges**: pill-shaped (`rounded-full`), soft-tinted background + darker text of the same hue — used for expense status (`pending_gm`, `approved`, `rejected`, etc.) throughout every module from Phase 1 onward, so build this as one reusable component now rather than re-implementing per screen later.
- **Dashboard metric cards**: `display-lg` (32px/600) for the headline number, `label-sm` (11px/600) for the trend indicator — reusable component built once in Phase 1, reused for every KPI across dashboard and reports.
- **Mobile reflow**: below 768px, sidebar collapses to a bottom-tab bar or hamburger overlay per the design system — verify this early since retrofitting responsive nav after 20 screens exist is far more painful than building it responsive from screen one.

---

## Phase 1 — Foundation & Core Recording

*Matches PRD §9 Phase 1. Value delivered: Ifeoma can start recording live and backfill history; running balance is finally digital and accurate.*

**Auth & sessions**
- [ ] Login page (navy sidebar shell not shown pre-auth — full-canvas centered card instead)
- [ ] `Auth.php`: session creation, `password_hash`/`password_verify`, session regeneration on login, idle timeout from `settings`
- [ ] Login rate limiting (Tech Spec §18)
- [ ] Logout, session validation on every request (checks `users.status = 'active'`)

**RBAC**
- [ ] `Permission.php` guard + session-cached permission list (Tech Spec §6)
- [ ] Confirm the seeded `role_permissions` rows (schema.sql §12) correctly gate access — manually test all 4 roles against at least one module each

**Settings module (Super Admin only)**
- [ ] Departments CRUD
- [ ] Expense categories CRUD
- [ ] Fund account view (single float, per resolved decision)
- [ ] Approval rules editor — tier ranges + required roles, with the sanity checks from PRD §3.3 (no gaps, no overlaps, upper tiers include all lower approvers)
- [ ] User management CRUD + role assignment
- [ ] Every Settings change writes to `audit_log` (old value, new value, who, when) — test this before moving on, since it's a core trust guarantee of the whole system

**Expense entry**
- [ ] Live expense form: date, document_no (required+unique for live), payee, description, department, category, amount, fund account, file upload
- [ ] `ApprovalEngine::generateChain()` — tier lookup, chain generation, Tier 1 auto-approval (Tech Spec §7 — confirmed: build as auto-approve, no flag needed)
- [ ] Bulk Historical Entry screen (Tech Spec §13): keyboard-driven row entry, department dropdown, optional document_no, inserts directly as `is_historical=1, source='backfilled', status='approved'`
- [ ] Status badges reflect PRD §12 enum correctly (draft, pending_gm, pending_chairman, approved, rejected, closed, paid)

**Fund top-ups**
- [ ] Top-up request form (Accountant)
- [ ] Historical top-up bulk entry (same pattern as expenses — `is_historical=1, status='approved'` directly)

**Dashboard v1**
- [ ] Current balance card (queries `fund_balances` view — never a manually stored number)
- [ ] Recent activity feed (latest expenses + top-ups)
- [ ] Basic KPI cards using the metric-card component from Phase 0

**Phase 1 test gate**
- [ ] Ifeoma can log in, enter a live expense, see it auto-approve at Tier 1, and see the balance update correctly
- [ ] Ifeoma can bulk-backfill a week of historical paper-book entries and see them flagged "Backfilled" in the activity feed
- [ ] Super Admin can edit an approval threshold in Settings and see it logged in `audit_log`

---

## Phase 2 — Approval Workflow & Payments

*Matches PRD §9 Phase 2. Value delivered: spending controls are live — no more unlimited accountant authority; GM/Chairman can approve from anywhere.*

**Approval queues**
- [ ] GM approval queue (`pending_gm` expenses awaiting them)
- [ ] Chairman approval queue (`pending_chairman` expenses awaiting them)
- [ ] Approve/reject actions (AJAX via Alpine, per Tech Spec §16) with required rejection comment
- [ ] Rejected-expense flow: Accountant's "Rejected — needs action" queue, edit-and-resubmit (new `cycle_number`, re-runs `ApprovalEngine`), or close-with-reason (PRD §11)

**Fund top-up approval**
- [ ] GM/Chairman single-approver sign-off screen (schema.sql §3 — either role clears it, no sequencing)
- [ ] Confirm `fund_balances` view only counts `status='approved'` top-ups (already true in schema — verify in UI)

**Payments**
- [ ] Payment voucher creation for `approved` expenses (Option A confirmed: expense approval is sufficient authorization — see Tech Spec §25)
- [ ] Payment method/bank account/reference capture
- [ ] Expense status transitions to `paid`

**Audit log**
- [ ] Audit log viewer (Super Admin full access; GM/Chairman see relevant entries per PRD §3.2)
- [ ] `AuditLogger::record()` hash chain implementation (Tech Spec §11) — confirm every state change in Phase 1 + 2 actually calls it, not just the ones built during this phase
- [ ] `scripts/verify_audit_log.php` verification script + cron job wiring (Tech Spec §20)

**Notifications**
- [ ] Zoho Mail SMTP config via PHPMailer
- [ ] Email triggers: expense enters approval queue, expense rejected, top-up approved/rejected (Tech Spec §15)

**Phase 2 test gate**
- [ ] A ₦300,000 expense correctly routes to GM only; a ₦600,000 expense correctly routes to GM then Chairman
- [ ] Rejecting an expense mid-chain cancels remaining pending rows and returns it to the Accountant with the reason visible
- [ ] Editing and resubmitting a rejected expense generates a fresh approval chain, and the old rejected chain is still visible in history
- [ ] Running the audit verification script against a deliberately tampered test row (edit one directly in the DB) correctly flags the break

---

## Phase 3 — Invoicing, Payroll & Reporting

*Matches PRD §9 Phase 3. Value delivered: complete financial picture — management gets board-ready reports without asking Ifeoma to compile them manually.*

**Invoicing**
- [ ] Invoice CRUD (invoice_no, client, department, amount, VAT/WHT, due date, payment status)
- [ ] Invoice approval (GM per matrix)

**Payroll**
- [ ] Payroll run creation (period_month/year)
- [ ] Payroll items (employee, salary, allowances, deductions, loans) — `net_salary` is a generated column, confirm it computes correctly
- [ ] Payroll approval + payment status

**Reports**
- [ ] Statement of Expenditure (opening balance, funds received, approved expenditure, outstanding liabilities, closing balance) — the PRD's core deliverable, built against the `fund_balances` date-range pattern (Tech Spec §10)
- [ ] P&L, cash flow, departmental expenses, budget vs. actual, receivables, payables
- [ ] PDF export via dompdf for all reports, styled consistently with the Horizon Finance design tokens (not default dompdf styling)
- [ ] "Backfilled" badges visible on any historical data feeding into a report

**PWA polish**
- [ ] Service worker: proper app-shell cache-first strategy, cache-busting on deploy (Tech Spec §17)
- [ ] Confirm mutating requests (POST/PUT/DELETE) are never intercepted or queued offline — test by going offline and confirming expense submission fails loudly rather than silently queuing
- [ ] Offline report viewing (cached read-only data + "last synced" timestamp)
- [ ] Install prompts on desktop + mobile

**Phase 3 test gate**
- [ ] A full month's Statement of Expenditure matches what Ifeoma would produce by hand from the paper book, for a month you can cross-check
- [ ] The app installs cleanly as a PWA on both a desktop browser and a phone
- [ ] Going offline mid-session shows a clear "you're offline" state on any write action, never a silent failure

---

## Cross-cutting checks (every phase, not just at the end)

- [ ] Every new controller action that changes financial data has a `Permission::require()` guard and an `AuditLogger::record()` call — check this on every PR to yourself, it's the easiest thing to forget under deadline pressure
- [ ] Every new screen is checked at mobile width (<768px) before being marked done, not retrofitted later
- [ ] No raw SQL string concatenation anywhere — grep the codebase periodically for `"SELECT` or `"INSERT` string patterns as a smell test
- [ ] Design system components (status badge, metric card, sidebar nav item) are reused, not reimplemented per screen — if you find yourself writing a second version of a badge, stop and extract the shared component instead

---

## All open items resolved

Both design questions carried forward from the Tech Spec are now settled:
- **Payment release authorization**: Option A — expense approval alone authorizes payment release, no second gate.
- **Tier 1 auto-approval**: confirmed — Accountant's submission at the lowest tier auto-approves, no separate click.

No open decisions remain blocking Phase 1. `ApprovalEngine::generateChain()` can be built exactly per Tech Spec §7 without a placeholder or flag for later reversal.
