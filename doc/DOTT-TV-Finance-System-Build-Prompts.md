# DOTT TV Finance & Accounting System — Build Prompts

**Version:** 1.0
**Companion documents:** `DOTT-TV-Finance-System-PRD.md`, `schema.sql`, `DOTT-TV-Finance-System-Tech-Spec.md`, `DOTT-TV-Finance-System-Build-Order.md`, `design-system-dotttv.md`

---

## How to use this document (read this first)

Don't paste every prompt below into a fresh chat with full context each time — that burns tokens re-explaining the stack on every feature. Instead:

1. **Set up persistent project context once** — put the block in §1 into `.cursorrules` (Cursor) or your Codex CLI's system/project instructions file, and make sure all five companion documents are physically present in the repo (not just described) so the AI can read them directly via file reference instead of you pasting their contents into chat.
2. **Run prompts in order.** Each one assumes everything before it exists. Don't skip ahead.
3. **One prompt = one Cursor/Codex session**, ideally with a commit at the end before starting the next. Small, verifiable increments are both more token-efficient (less context to hold at once) and easier to debug when something's wrong.
4. Each prompt below is deliberately short — it references the companion docs by section number rather than restating them, and trusts the AI to read the actual file. If your tool doesn't have file access in-session, you'll need to paste the relevant section manually; the section references tell you exactly which one.
5. Acceptance criteria under each prompt double as your manual QA checklist — don't mark a feature done until they pass.

---

## 1. Persistent Project Context (put this in `.cursorrules` / system instructions — not a one-off prompt)

```
PROJECT: DOTT TV Finance & Accounting System

STACK: PHP 8.2, MySQL 8, PDO (prepared statements only, never string-concatenated SQL),
Tailwind CSS (standalone CLI, no Node runtime dependency in production), vanilla JS +
Alpine.js, PWA (manifest + service worker). No PHP framework — a small hand-rolled
front-controller + router per Tech Spec §2-4.

REFERENCE DOCS (read the relevant one before generating code for any feature):
- DOTT-TV-Finance-System-PRD.md — product behavior, roles, module list, business rules
- schema.sql — the actual database schema, already finalized, DO NOT modify table
  structure without being explicitly asked; this is the single source of truth for
  every table/column name and type
- DOTT-TV-Finance-System-Tech-Spec.md — architecture, folder structure, algorithms
  (approval engine, audit hash chain, balance calculation), security requirements
- DOTT-TV-Finance-System-Build-Order.md — the phase-by-phase task breakdown this
  prompt set follows
- design-system-dotttv.md — exact design tokens (colors, type scale, spacing,
  radius) for every UI screen. Use these tokens, never invent new colors or spacing.
- DOTT-TV-Finance-System-UI-Component-Guide.md — CANONICAL markup for every recurring
  UI pattern (page shell/centering, sidebar, cards, buttons, status badges, metric
  cards, forms, tables, empty states). For ANY screen with a UI component, copy the
  matching pattern from this file exactly rather than composing new Tailwind classes.
  This file exists specifically to stop visual drift across screens — treat it as
  fixed template, not a style suggestion to reinterpret.

NON-NEGOTIABLE RULES (apply to every prompt below, don't ask permission each time):
1. Every PDO query uses prepared statements with bound parameters. No exceptions.
2. Every controller action that reads/writes financial data starts with a
   Permission::require($user, $module, $action) call per Tech Spec §6.
3. Every state-changing action (create/edit/approve/reject/close/settings change)
   calls AuditLogger::record() in the SAME database transaction as the change,
   per Tech Spec §11. If the audit write fails, the whole transaction rolls back.
4. Balance is NEVER read from a manually-maintained column — always through the
   fund_balances view (schema.sql §11) or an equivalent date-filtered query.
5. Follow the folder structure in Tech Spec §4 exactly — app/ and storage/ outside
   the web root, only public_html/index.php as the web-reachable PHP entry point.
6. Use the design tokens from design-system-dotttv.md for every color, radius, and
   spacing value — no arbitrary Tailwind utility values invented on the fly.
7. PHP enums for status strings (ExpenseStatus, TopupStatus, etc.) mirroring the
   DB ENUM values — never bare string literals for status comparisons.
8. Resolved product decisions, don't re-litigate these: Tier 1 (Accountant-only,
   up to ₦50,000) auto-approves on submission with no separate click. Payment
   release requires no second approval chain beyond the expense's own approval —
   GM/Chairman's Payments "approve" is a lightweight release-details confirmation
   only. Fund top-ups use single-approver sign-off (either GM or Chairman, not
   both in sequence) — much lighter than the tiered expense chain.

After generating each feature, state which files you created/modified and which
of the feature's acceptance criteria (given in the prompt) you're confident are
met vs. still need manual verification.
```

---

## Phase 0 — Environment & Scaffolding

### Prompt 0.1 — Project scaffolding

```
Set up the initial project structure per Tech Spec §4. Create:
- The full folder tree (public_html/, app/config, app/core, app/controllers,
  app/models, app/views, storage/uploads/{receipts,vouchers}, storage/logs)
- composer.json with the dependencies from Tech Spec §24
- .gitignore excluding vendor/, .env, storage/logs/*, storage/uploads/*
- .env.example with placeholder DB_HOST, DB_NAME, DB_USER, DB_PASS, SMTP_* vars,
  APP_ENV, APP_SECRET
- app/config/config.php that loads .env via vlucas/phpdotenv and exposes config
  as simple constants/array, nothing fancier
- app/core/Database.php: a PDO singleton, utf8mb4 charset, PDO::ERRMODE_EXCEPTION,
  emulated prepares OFF
- public_html/.htaccess rewriting all requests to index.php except real files
- public_html/index.php as the front controller entry point (router wiring can
  be minimal/stubbed for now — Prompt 0.2 builds the real router)

Don't write business logic yet. This is scaffolding only.
```

**Acceptance criteria:**
- [ ] Folder structure matches Tech Spec §4 exactly
- [ ] `.env` (created locally from `.env.example`, not committed) loads without errors
- [ ] A test script confirms `Database.php` connects successfully

### Prompt 0.2 — Router and base layout

```
Build app/core/Router.php: a minimal router matching request path + HTTP method
against routes defined in app/config/routes.php, dispatching to a
Controller@method string. Wire it into public_html/index.php.

Build app/views/layouts/app.php: the base HTML shell per design-system-dotttv.md's
"Deep Navigation / Light Content" pattern — fixed 260px sidebar using the primary
color token as background, fluid content area using the surface token. Load
compiled Tailwind CSS from public_html/assets/css/app.css and Inter font. Sidebar
nav items should be data-driven (an array of {label, icon, href, permission})
so adding a nav item later doesn't require touching layout HTML.

Set up Tailwind (standalone CLI) with the exact token mappings given in the
Persistent Project Context section — a source CSS file with the @theme block
and a build script/command documented in a comment at the top of that file.

Also create public_html/manifest.json (app name "DOTT TV Finance", theme color
from the primary token, display: standalone) and an empty public_html/sw.js
stub (real caching logic comes in Phase 3 per Build Order).
```

**Acceptance criteria:**
- [ ] Visiting any route renders the shell with sidebar + content area correctly styled per design tokens
- [ ] Tailwind compiles without errors and produces the expected utility classes
- [ ] `manifest.json` validates (Chrome DevTools > Application > Manifest shows no errors)

---

## Phase 1 — Foundation & Core Recording

### Prompt 1.1 — Auth & sessions

```
Implement authentication per Tech Spec §5. Build:
- app/core/Auth.php: login (password_verify against users.password_hash),
  logout, session creation with session_regenerate_id(true) on login,
  idle-timeout check reading from the settings table (key:
  session_idle_timeout_minutes, fall back to 30 if not set)
- Login rate limiting: 5 attempts per 15 minutes per IP+email combo (store
  attempts in a simple table or file-based counter, your choice, document
  which you picked)
- Every authenticated request re-checks users.status = 'active' from the DB,
  not just session data — a deactivated user is locked out on their very next
  request, not just next login
- app/views/auth/login.php: full-canvas centered card (no sidebar pre-auth),
  styled per design-system-dotttv.md
- AuthController with login/logout actions wired into the router

Read schema.sql's users table definition before writing any queries.
```

**Acceptance criteria:**
- [ ] Valid login succeeds and creates a session; invalid login fails with a generic error (don't reveal whether the email or password was wrong)
- [ ] 6th failed attempt within 15 minutes is blocked with a clear message
- [ ] Deactivating a user in the DB mid-session locks them out on their next request

### Prompt 1.2 — RBAC

```
Implement RBAC per Tech Spec §6. Build:
- app/core/Permission.php with a static check($user, $module, $action) returning
  bool, and require($user, $module, $action) that throws a 403 exception if false
- On login, load the user's full permission set (join role_permissions +
  permissions for their role_id) into $_SESSION['permissions'] as a flat array
  of "module.action" strings
- A simple test route/page that displays the logged-in user's role and full
  permission list, so we can manually verify each of the 4 roles sees the
  correct set (delete this test page before Phase 1 is marked done, or gate it
  to super_admin only)

Read schema.sql's role_permissions seed data (already populated by schema.sql's
INSERT...SELECT block) — don't regenerate or duplicate that seed logic in PHP.
```

**Acceptance criteria:**
- [ ] Logging in as each of the 4 seeded roles shows the correct permission set matching PRD §3.2's matrix exactly
- [ ] `Permission::require()` correctly throws/blocks when called with a module.action the role doesn't have

### Prompt 1.3 — Settings module

```
Build the Settings module (Super Admin only, per PRD §3.2 — guard every action
with Permission::require($user, 'settings', <action>)):
- Departments CRUD (simple name field)
- Expense categories CRUD (simple name field)
- Fund account view (read-only display — schema.sql seeds a single "Main
  Operating Float" row, v1 doesn't need a create-new-fund-account UI per the
  resolved single-float decision, PRD §10.2)
- Approval rules editor: list/edit tiers (tier_order, min_amount, max_amount,
  required_roles as an ordered multi-select of role names). Enforce the sanity
  checks from PRD §3.3: no gaps or overlaps between tiers, and each tier's
  required_roles must be a superset in order of all lower tiers' required roles
  (e.g. tier 3 can't skip gm if tier 2 requires it)
- User management CRUD + role assignment
- EVERY create/edit/delete action in this module calls AuditLogger::record()
  with before/after JSON — this module is the highest-trust surface in the
  whole app, don't skip this on any action even ones that feel minor

Style every screen per design-system-dotttv.md: white cards (surface-container-
lowest), rounded-lg, the reusable status-badge and metric-card components if
relevant to a given screen.
```

**Acceptance criteria:**
- [ ] All 5 sub-screens work and are gated to Super Admin only (test as another role — should get a 403, not just a hidden nav item)
- [ ] Editing an approval rule threshold is visible in the audit log immediately after, with correct before/after values
- [ ] Approval rule sanity checks correctly reject an invalid edit (e.g. a gap between tiers) with a clear error message

### Prompt 1.4 — Expense entry + approval chain generation

```
Build the live expense entry flow and the approval engine per Tech Spec §7:
- app/core/ApprovalEngine.php: generateChain($expense) implementing the exact
  algorithm in Tech Spec §7 steps 1-5, including Tier 1 auto-approval (resolved
  decision — no confirmation needed, build it as auto-approve)
- Live expense form: date, document_no (required + unique, validate at the
  application layer since schema.sql doesn't enforce uniqueness only for live
  entries), payee, description, department (dropdown), category (dropdown),
  amount, fund_account (single option in v1), file upload for supporting doc
- File upload handling per Tech Spec §12: validate MIME type (PDF/JPG/PNG only),
  max 10MB, store in storage/uploads/receipts/ with a generated UUID filename,
  never the original filename
- ExpenseController: create action runs the form, saves the expense as 'draft'
  then immediately calls generateChain() to submit it (v1 doesn't need a
  separate "save draft, submit later" step unless you want one — if in doubt,
  submit immediately on save)
- Status badges (reusable component) reflecting the full enum from schema.sql:
  draft, pending_gm, pending_chairman, approved, rejected, closed, paid — pill-
  shaped per design-system-dotttv.md, soft-tinted color per status meaning
  (e.g. approved = success-tinted, rejected = error-tinted, pending = neutral
  or warning-tinted)

Read schema.sql's expenses and expense_approvals table definitions carefully
before writing this — the cycle_number and resubmission_count columns matter
here even though resubmission itself is built in Phase 2.
```

**Acceptance criteria:**
- [ ] Submitting a ₦40,000 expense auto-approves immediately (Tier 1)
- [ ] Submitting a ₦300,000 expense lands in status `pending_gm`
- [ ] Submitting a ₦600,000 expense lands in status `pending_gm` (will move to `pending_chairman` after GM approves — built in Phase 2, but the initial chain generation for this tier should be correct now)
- [ ] File upload rejects a 15MB file and a .exe file with clear error messages
- [ ] Uploaded file is not accessible via a direct guessable URL

### Prompt 1.5 — Historical bulk entry

```
Build the Bulk Historical Entry screen per Tech Spec §13 and PRD §5 — this is a
DISTINCT screen from Prompt 1.4's live expense form, not a reused component:
- Table-style repeating-row input: date, document_no (optional, no uniqueness
  enforcement here), payee, description, department (dropdown), amount
- Enter key in the amount field (last field in a row) creates a new row and
  focuses its first field, via Alpine.js x-on:keydown.enter — no page reload
  between rows
- On save (can be per-row or a "Save all" batch action, your choice — document
  which), each row inserts directly with is_historical=1, source='backfilled',
  status='approved' — bypassing ApprovalEngine entirely, per Tech Spec §13
- Build the equivalent for fund_topups: a simpler historical top-up entry form
  (date, amount, reference, note), also inserting directly as is_historical=1,
  status='approved'

Both should visually flag entries as "Backfilled" (a small badge) anywhere they
later appear in lists/reports — implement the badge now even though most report
screens come in Phase 3, so it's consistent from the start rather than added
retroactively.
```

**Acceptance criteria:**
- [ ] Entering 10 rows of historical expenses via keyboard-only (no mouse) works smoothly, Enter key advances correctly
- [ ] Historical entries do NOT appear in any GM/Chairman approval queue
- [ ] Historical entries correctly reduce the fund_balances view's current_balance immediately on save

### Prompt 1.6 — Dashboard v1

```
Build the Phase 1 dashboard per Build Order Phase 1 and PRD §6.1:
- Current balance card querying the fund_balances view (never a stored number)
- Recent activity feed: latest expenses + top-ups combined, most recent first
- Basic KPI metric cards using the design system's display-lg / label-sm
  pattern — build this as a genuinely reusable component now (props: label,
  value, trend), since it gets reused in Phase 3's reporting screens too

Keep this dashboard simple — pending-approval counts and P&L snapshots are
Phase 2/3 additions per the Build Order, don't build ahead of schedule here.
```

**Acceptance criteria:**
- [ ] Balance shown matches a manual SUM(topups) - SUM(approved/historical expenses) calculation you check by hand against the seeded test data
- [ ] Recent activity feed correctly interleaves expenses and top-ups by date
- [ ] Metric card component is genuinely reusable (no hardcoded values, takes props)

---

## Phase 2 — Approval Workflow & Payments

### Prompt 2.1 — Approval queues

```
Build GM and Chairman approval queues:
- A queue view filtered to expenses where status = 'pending_gm' (for GM users)
  or 'pending_chairman' (for Chairman users) — respect Permission checks, a
  user should only ever see their own tier's queue
- Approve/reject actions via Alpine-driven AJAX (JSON endpoints, not full page
  reloads) per Tech Spec §16 — clicking Approve updates the expense_approvals
  row (action, approver_id, acted_at) and advances expenses.status to the next
  tier or to 'approved' if this was the last tier
- Reject requires a comment (validate server-side, not just a client-side
  required attribute) — on reject, cancel all other 'pending' rows in the same
  cycle_number, set expenses.status = 'rejected', rejected_reason populated
- AuditLogger::record() on every approve/reject action

Read Tech Spec §7's "On approval action" and "On rejection" subsections before
implementing this — the exact state transitions are specified there.
```

**Acceptance criteria:**
- [ ] A GM sees only expenses genuinely awaiting them, never a Chairman-tier or already-decided expense
- [ ] Approving the last tier in a chain correctly sets status to 'approved', not stuck in an intermediate state
- [ ] Rejecting without a comment is blocked with a clear inline error, not a silent failure

### Prompt 2.2 — Rejection, edit, resubmit, close flow

```
Build the Accountant-facing side of the rejection flow per PRD §11:
- A "Rejected — needs action" queue, separate from Draft and Pending, for the
  Accountant's own rejected expenses
- Edit-and-resubmit: opens the rejected expense in an editable form
  (pre-filled), on save increments expenses.resubmission_count and calls
  ApprovalEngine::generateChain() again — this creates a FRESH set of
  expense_approvals rows at the new cycle_number, per Tech Spec §7's
  "On edit-and-resubmit" subsection. The old cycle's rows must remain visible
  in the expense's history, not deleted or hidden
- Close: marks the expense 'closed' with a required closed_reason, excluded
  from fund_balances and from any approval queue but still visible in reports/
  audit history with a clear "Closed" badge
- An expense detail view showing the FULL history across all cycles — this is
  the screen that proves the audit trail design actually works, so make sure
  it queries by expense_id across all cycle_numbers, not just the latest
```

**Acceptance criteria:**
- [ ] A rejected ₦550,000 expense, edited down to ₦45,000, correctly resubmits into Tier 1 (auto-approves) rather than staying in the old ₦550,000 tier's chain
- [ ] The expense detail view shows both the original rejected cycle and the new resubmitted cycle, clearly distinguished
- [ ] Closing an expense removes it from all approval queues and from the balance calculation immediately

### Prompt 2.3 — Fund top-up approval

```
Build the fund top-up approval flow per Tech Spec §8 and schema.sql's
fund_topups.status column:
- Top-up request form (Accountant): amount, date, reference, note — inserts
  with status='pending'
- A single approval action available to both GM and Chairman roles (whoever
  gets there first clears it — no sequencing, no second sign-off required)
- On approval: status='approved', approved_by, approved_at set
- On rejection: status='rejected', rejected_reason required
- Confirm the fund_balances view is already correctly filtering to
  status='approved' OR is_historical=1 (it should be, per schema.sql §11 —
  this prompt is about the UI/workflow, not the view itself)
```

**Acceptance criteria:**
- [ ] A pending top-up does NOT affect the displayed balance until approved
- [ ] Either a GM or a Chairman (test both) can independently clear a pending top-up — approving as one role doesn't require the other role's action too

### Prompt 2.4 — Payments

```
Build the Payments module per Tech Spec §9 (Option A — resolved: expense
approval alone authorizes release, no second approval chain):
- For any expense with status='approved', an Accountant can create a
  payment_vouchers row: payment_method, bank_account, payment_reference,
  supporting_doc_path (optional additional doc), paid_by, paid_at
- On voucher creation, expenses.status transitions to 'paid'
- voucher_no auto-generated, unique
- A simple list/detail view of payment vouchers, filterable by date/department
```

**Acceptance criteria:**
- [ ] Only approved expenses can have a voucher created against them (attempting on a pending or rejected expense should be blocked, not just hidden from the UI)
- [ ] Creating a voucher correctly flips the expense to 'paid' and this reflects immediately in any expense list/dashboard

### Prompt 2.5 — Audit log viewer + hash chain verification

```
Build the audit log viewer and the hash chain implementation per Tech Spec §11:
- app/core/AuditLogger.php: record($userId, $action, $entityType, $entityId,
  $before, $after) — fetches the most recent row's row_hash (or the 64-zero
  genesis string if none exists), computes this row's row_hash per the exact
  formula in Tech Spec §11, inserts within the SAME transaction as the calling
  code's state change
- Audit log viewer screen: filterable by entity_type, date range, user — Super
  Admin sees everything, GM/Chairman see per PRD §3.2 (GM: own approvals only;
  Chairman: full view)
- scripts/verify_audit_log.php: a standalone CLI script (not a web route) that
  walks the entire audit_log table in id order, recomputes each row_hash from
  its stored fields + the previous row's row_hash, and reports any row where
  the recomputed hash doesn't match what's stored — this is the tamper
  detection mechanism, make sure it actually catches a manually-edited row
  when you test it

Go back through every controller built in Prompts 1.1-2.4 and confirm each
state-changing action actually calls AuditLogger::record() — this prompt is
also a coverage-completion pass, not just new-feature work.
```

**Acceptance criteria:**
- [ ] Manually editing one field in one audit_log row directly via phpMyAdmin/MySQL client, then running the verification script, correctly flags that row and everything after it
- [ ] The audit log viewer correctly restricts GM to their own approval actions only, per the permission matrix
- [ ] Spot-check 5 different state-changing actions across different modules (settings edit, expense approval, top-up rejection, payment voucher creation, user deactivation) and confirm all 5 produced an audit_log row

### Prompt 2.6 — Notifications

```
Build email notifications per Tech Spec §15, using PHPMailer + Zoho Mail SMTP
(credentials from .env, never hardcoded):
- Triggered on: expense enters a user's approval queue, expense rejected
  (to the Accountant), top-up approved/rejected (to the requesting Accountant)
- Keep templates plain and functional — a subject line, the key facts (amount,
  payee/reference, link to the item), no elaborate HTML email design needed
- Wrap sending in a try/catch that logs failures to storage/logs/ without
  breaking the underlying action if email fails (e.g. an expense should still
  successfully move to pending_gm even if the notification email fails to
  send — email is a nice-to-have side effect, not a blocking dependency)
```

**Acceptance criteria:**
- [ ] Submitting a ₦300,000 expense sends an email to the correct GM
- [ ] A deliberately broken SMTP config (wrong password) doesn't prevent the underlying expense/top-up action from completing — check storage/logs/ shows the failure instead

### Prompt 2.7 — Web Push Notifications

```
Build Web Push per Tech Spec §15a, as a SECOND channel alongside Prompt 2.6's
email notifications — not a replacement. Same trigger events (expense enters
approval queue, expense rejected, top-up approved/rejected).

1. composer require minishlink/web-push. Generate a VAPID key pair, add
   VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY to .env.example (placeholders) and to
   your local .env (real values).

2. push_subscriptions table already exists in schema.sql §10a — build:
   - POST /push/subscribe endpoint: accepts {endpoint, keys: {p256dh, auth}}
     from the logged-in user's session, upserts a row keyed on endpoint
   - app/core/PushNotifier.php: send($userId, $title, $body, $url) — fetches
     all subscriptions for that user, sends via minishlink/web-push, deletes
     the row on a 410/404 response (expired subscription), logs other failures
     to storage/logs/ without throwing (same non-blocking discipline as email)

3. Client-side: read UI Component Guide §9c for the exact opt-in banner markup.
   Implement enablePushNotifications() (requests Notification permission on
   click, subscribes via pushManager.subscribe with the VAPID public key,
   POSTs the subscription to /push/subscribe) and dismissNotificationBanner()
   (stores a dismissal timestamp in localStorage, re-offer after 14 days —
   this is a UI-preference nicety, localStorage is fine here specifically,
   don't treat it as a pattern to reuse for anything financial).

4. Add a push event listener to public_html/sw.js (self.registration.showNotification)
   and a notificationclick listener that focuses an existing tab or opens the
   notification's url — additive to the existing app-shell caching logic in
   that file, don't disturb it.

5. On iOS specifically: check matchMedia('(display-mode: standalone)') and
   swap the banner copy per §9c's iOS variant if the app isn't installed to
   the home screen, since Web Push silently doesn't work there otherwise.

6. Wire PushNotifier::send() into the same trigger points Prompt 2.6 already
   added for email — same events, parallel call, not a replacement.

A push notification is a nudge with a deep link only — it must NEVER carry an
action button that approves/rejects directly. Tapping it opens the app to the
relevant expense, where the normal Permission::require()-gated flow takes over.
```

**Acceptance criteria:**
- [ ] Clicking "Enable" on the opt-in banner successfully creates a push_subscriptions row after granting browser permission
- [ ] Submitting a ₦300,000 expense triggers both an email AND a push notification to the correct GM
- [ ] Clicking a push notification opens the app directly to that expense, not just the homepage
- [ ] Manually revoking notification permission in the browser, then triggering another push, results in the stale subscription row being deleted (test by checking the 410 response handling, not just "no crash")
- [ ] On iOS in a non-installed Safari tab, the banner shows the "install first" copy instead of attempting a subscription that would silently fail

---

## Phase 3 — Invoicing, Payroll & Reporting

### Prompt 3.1 — Invoicing

```
Build the Invoice module per PRD §6.2 and the permission matrix (Accountant:
create/edit, GM: approve, Chairman: view, Super Admin: full):
- Invoice CRUD: invoice_no (auto-generated, editable), date, client,
  description, department, amount, vat_amount, wht_amount, due_date,
  payment_status, payment_date
- GM approval action (simpler than expenses — this is revenue recognition,
  not a spend-control gate, so a single approval step is sufficient, no
  multi-tier chain needed here)
- List view with payment_status filter (unpaid/partial/paid) and overdue
  highlighting (due_date passed and still unpaid)
```

**Acceptance criteria:**
- [ ] Invoice CRUD respects the permission matrix exactly per role
- [ ] Overdue unpaid invoices are visually distinguished in the list view

### Prompt 3.2 — Payroll

```
Build the Payroll module per PRD §6.6:
- payroll_runs: create a run for a period_month/period_year (enforce the
  schema's UNIQUE(period_month, period_year) constraint with a clear error if
  a run already exists for that period)
- payroll_items: employee_name, department, basic_salary, allowances,
  deductions, loan_deduction — net_salary is a GENERATED column in the DB
  (schema.sql), don't calculate it in PHP, just read it back after insert
- Payroll run approval (GM per matrix) and payment_status tracking per item
```

**Acceptance criteria:**
- [ ] Attempting to create a second run for the same month/year is blocked with a clear message, not a raw DB constraint error
- [ ] net_salary displayed matches the DB's generated column calculation exactly

### Prompt 3.3 — Reports & PDF export

```
Build the reporting suite per PRD §6.7 and Tech Spec §10, §14:
- Statement of Expenditure: date-range selector, opening balance (fund_balances
  calculation as of period_start - 1 day), funds received in range, approved
  expenditure in range, outstanding liabilities (approved-but-unpaid expenses),
  closing balance — this is the PRD's core report, get this one right first
- P&L, cash flow, departmental expenses, budget vs. actual, receivables,
  payables — build these using the same underlying date-filtered query pattern
  as the Statement of Expenditure for consistency
- PDF export via dompdf for every report, styled with the Horizon Finance
  design tokens (font, colors) — not dompdf's default styling
- "Backfilled" badges visible wherever historical data feeds into a report
  total, so a reviewer can tell at a glance which figures include
  reconstructed paper-book data
```

**Acceptance criteria:**
- [ ] A Statement of Expenditure for a test month matches a hand calculation you verify independently
- [ ] PDF export renders correctly (not default Times New Roman dompdf styling) and is genuinely readable when printed
- [ ] Every report correctly flags historical-data contribution, not just the expense list screens

### Prompt 3.3a — Fund Ledger report + Excel export (added post-build, per direct feedback)

```
Two additions to the reporting suite per Tech Spec §14a and §14b:

1. Fund Ledger report: a new report, NOT filtered by department (deliberately —
   this is the one report that shows everything in one place). Full chronological
   list of every approved/paid expense and every approved top-up, interleaved by
   date, with a running balance column computed via the SQL window function
   given in Tech Spec §14a — don't recalculate the running balance in a PHP loop,
   use the window function so the balance math has one source of truth same as
   everywhere else in the app. Date-range selector like every other report.
   backfilled_badge() on historical rows, same as elsewhere.

2. composer require phpoffice/phpspreadsheet. Add an "Export Excel" action
   alongside every existing "Export PDF" action (Statement of Expenditure, P&L,
   cash flow, departmental expenses, budget vs actual, receivables, payables,
   and the new Fund Ledger) — both formats consume the SAME underlying data-fetch
   method per report, never two separately-derived versions of the same numbers.
   Basic formatting: bold header row, currency format on amount columns,
   auto-sized columns, report title + date range above the table.

Fix the $companyName warning from Prompt R.5 first if it hasn't been fixed yet —
the new Excel renderers will hit the same settings-fetching code path.
```

**Acceptance criteria:**
- [ ] Fund Ledger shows expenses from every department in one list, not filtered to any single one
- [ ] Running balance shown per row matches a hand-recalculation for at least 5 consecutive rows you check manually
- [ ] Excel export opens cleanly in actual spreadsheet software (not just "downloads without erroring") for at least 3 different reports, with currency formatting visibly applied
- [ ] PDF and Excel exports of the same report show identical totals — since both come from the same data-fetch, a mismatch would mean something is actually querying differently between the two, not just a formatting difference

### Prompt 3.4 — PWA polish

```
Finish the PWA implementation per Tech Spec §17:
- public_html/sw.js: cache-first strategy for the app shell (HTML shell,
  compiled CSS/JS, icons, fonts) with a version-based cache name that changes
  on each deploy to bust stale caches
- The fetch handler intercepts ONLY GET requests for static assets — every
  POST/PUT/DELETE to a financial endpoint passes through untouched, so it
  fails loudly with a clear "you're offline" UI state if there's no
  connection, rather than silently queuing
- Report data may be cached for offline viewing with a visible "last synced"
  timestamp — this is the one exception to write-only-when-online, since
  viewing a stale report offline is safe, submitting a stale write isn't
- Install prompts (the standard beforeinstallprompt handling) on both desktop
  and mobile

Test this by actually going offline (DevTools > Network > Offline) and
confirming: static pages still load from cache, but clicking "Approve" on an
expense shows a clear offline error rather than doing nothing or erroring
silently.
```

**Acceptance criteria:**
- [ ] App installs cleanly as a PWA on desktop Chrome and on a mobile browser
- [ ] Going offline and clicking any write action produces a clear, immediate "you're offline" message
- [ ] Going offline and viewing a previously-loaded report still works, with a visible "last synced" indicator

---

## Remediation — Retrofit Phase 1 screens to the UI Component Guide

Run this before continuing to Phase 2 if Phase 1 screens don't currently follow `DOTT-TV-Finance-System-UI-Component-Guide.md` (e.g. inconsistent spacing, content not centered, ad-hoc card/button/badge markup that differs screen to screen).

### Prompt R.1 — UI conformance retrofit

```
Read DOTT-TV-Finance-System-UI-Component-Guide.md in full before touching any file.

Go through every view built in Phase 1 (auth/login, dashboard, settings screens,
expense entry, historical bulk entry, fund top-up form) and bring each one into
conformance with the UI Component Guide:

1. Wrap every page's content in the Page Shell pattern from §1 (max-w-6xl mx-auto
   for standard pages, max-w-md mx-auto nested inside for the login page, max-w-2xl
   for single-entity forms like expense entry) — this is the fix for inconsistent
   centering.
2. Replace any hand-rolled card markup with the exact Card pattern from §3.
3. Replace any hand-rolled button markup with the exact Button patterns from §4.
4. Replace any inline status-string rendering with the status_badge() partial
   from §5 — build it as a real shared PHP function if it doesn't exist yet,
   don't inline the color-mapping logic per screen.
5. Replace any hand-rolled dashboard KPI markup with the Metric Card pattern
   from §6, built as one reusable partial, not per-widget markup.
6. Check every color and spacing value against §10's rules — flag (don't
   silently "fix" without listing) any raw hex values or off-scale spacing
   you find, since some of those might be intentional and worth confirming
   with me before changing.

Report back: which screens you changed, what specifically was off before
(e.g. "expense form had no max-width wrapper, content stretched full width"),
and any §10 violations you flagged but didn't change.
```

**Acceptance criteria:**
- [ ] Every Phase 1 screen visually matches the others — same card style, same button style, same badge style, no screen looks like it was built by a different person
- [ ] Content is centered with consistent margins on a wide monitor, not stretched edge-to-edge or flush against the sidebar
- [ ] The status badge and metric card are each a single shared component used everywhere, not reimplemented per screen

### Prompt R.2 — Login screen to canonical Auth Card

```
Read DOTT-TV-Finance-System-UI-Component-Guide.md §1a (Auth Card) before touching
anything.

Replace the current login screen (app/views/auth/login.php) with the exact markup
in §1a — full-canvas centered card, no sidebar, matching structure and copy shown.

Explicitly do NOT add: a "keep me signed in" checkbox, Google/GitHub/SSO buttons,
a "Register here" link, or a working "Forgot password?" link — §1a's "Deliberately
excluded" note explains why each of these doesn't belong in this app yet. If you
think one of them should exist, flag it back to me rather than adding it.

Wire the password visibility toggle (the eye icon) via Alpine.js x-data="{ showPassword: false }"
on the form wrapper, toggling the password input's type attribute between 'password'
and 'text'.
```

**Acceptance criteria:**
- [ ] Login screen visually matches §1a exactly — centered card, no sidebar, correct copy
- [ ] None of the four excluded elements were added
- [ ] Password visibility toggle works via Alpine, no page reload


### Prompt R.3 — Self-mailing audit verification script

```
Update scripts/verify_audit_log.php per Deployment Handbook Part 6: instead of
relying on cPanel's default cron-output email, the script should look up the
Super Admin's email directly from the users table (WHERE role = super_admin,
status = 'active' — handle the case of multiple Super Admins by emailing all
of them, not just the first) and send its result via the same PHPMailer/Zoho
SMTP setup already built in Prompt 2.6, rather than a second mail-sending path.

The email should clearly state: pass/fail, and if failed, which audit_log row
id broke the hash chain first. Keep it plain text, consistent with the other
notification templates from Prompt 2.6.

Test by running the script manually via SSH/CLI and confirming the email
arrives at the actual Super Admin address, not just that the script exits
without errors.
```

**Acceptance criteria:**
- [ ] Running the script manually sends a real email to the Super Admin's actual address (not the cPanel account's generic contact email)
- [ ] A deliberately tampered audit_log row (edited directly via phpMyAdmin) causes the email to report failure with the specific row id
- [ ] Multiple Super Admin users (if more than one exists) all receive the email, not just one


### Prompt R.4 — Resolve audit log foreign-key IDs to names

```
Read DOTT-TV-Finance-System-UI-Component-Guide.md §8b again — it's been updated
with a resolve_audit_value() helper and audit_diff_view()'s signature has changed
to accept the PDO connection as its first argument.

Add resolve_audit_value() alongside audit_diff_view() (same file/location the
latter already lives in). Update every call site of audit_diff_view() to pass
$db as the new first argument.

Test against: an approval action (approver_id resolves to the approving user's
actual name), a department edit in Settings (department_id resolves to the
department name), and a role/approval-rule change (role_id resolves to the
role name). Confirm a value that ISN'T a known foreign key (e.g. an amount or
a free-text description) still displays as-is, unaffected.

Also confirm: if a referenced record was since deleted (e.g. a department that
no longer exists), the diff falls back to showing the raw id rather than
erroring or showing a blank value.
```

**Acceptance criteria:**
- [ ] `approved_by`, `created_by`, `requested_by` and similar `*_by` fields show the actual person's name, not a bare number
- [ ] `department_id`, `category_id`, `fund_account_id`, `role_id` all resolve to their real names
- [ ] Non-FK fields (amounts, dates, free text) are unaffected — still shown as plain values
- [ ] A deleted-record edge case falls back gracefully to the raw id, no error

### Prompt R.5 — Fix undefined $companyName in PDF report layout

```
app/views/reports/pdf/layout.php references $companyName but it's undefined
(PHP warning on line 65). Find wherever this view is rendered (likely
ReportController's PDF export action) and confirm it fetches the company_name
value from the settings table (schema.sql seeds this as 'DOTT TV') and passes
it into the view — check whatever pattern is already used elsewhere for reading
settings values (Settings module from Prompt 1.3 already has this logic, reuse
it rather than writing a second way to read settings).

Grep app/views/reports/pdf/ for any other variables used but not obviously
passed in, in case this is a broader pattern issue rather than a one-off — fix
all instances found, not just $companyName.
```

**Acceptance criteria:**
- [ ] No PHP warnings when generating any PDF report
- [ ] Company name renders correctly on the PDF header


### Prompt 4.1 — Security & coverage audit

```
Do a full pass against Tech Spec §18's security checklist and the Build
Order's "Cross-cutting checks" section:
- Grep the entire codebase for any raw SQL string concatenation (patterns
  like "SELECT " . or "INSERT INTO " .) — flag every match for manual review
- Confirm every controller action that touches financial data has a
  Permission::require() call — list any that don't
- Confirm every state-changing action has an AuditLogger::record() call —
  list any that don't
- Check security headers are set (X-Content-Type-Options, X-Frame-Options,
  Content-Security-Policy) at the front controller or .htaccess level
- Confirm .env file permissions and that it's genuinely unreachable via direct
  URL on the actual deployed subdomain, not just theoretically outside the web
  root

Produce a short markdown report of findings — don't silently fix things you're
not confident about, flag them for review instead.
```

**Acceptance criteria:**
- [ ] Zero raw SQL string concatenation matches
- [ ] Zero controller actions missing a permission check
- [ ] Zero state-changing actions missing an audit log call