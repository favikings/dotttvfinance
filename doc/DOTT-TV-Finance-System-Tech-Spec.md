# DOTT TV Finance & Accounting System — Technical Specification

**Version:** 1.0
**Status:** Draft for review
**Owner:** Favikings (ICT Head / Technical Lead, DOTT TV)
**Companion documents:** `DOTT-TV-Finance-System-PRD.md`, `schema.sql`

---

## 1. Purpose & Scope

This document specifies *how* the system described in the PRD gets built: application architecture, folder structure, request lifecycle, the concrete implementation of the approval rules engine and hash-chained audit log, PWA behavior, security posture, and deployment on cPanel shared hosting.

The PRD is the source of truth for *what* the system does and *why*. This document should never contradict it — if an implementation detail here forces a product decision, that decision gets reflected back into the PRD, not silently decided here.

---

## 2. Architecture Overview

No framework (Laravel, Symfony, etc.) is used. Given a solo builder on shared cPanel hosting, a framework adds deployment complexity (Composer autoloading quirks, `.htaccess` rewriting rules, artisan-style CLI dependence) without buying much at this scale. Instead: a small, deliberate **micro-framework** — a front controller, a router, and a thin MVC-ish layer — kept under one directory (`/app/core`) so it's fully understood rather than a black box.

**Request lifecycle:**

1. All requests hit `public/index.php` (the only PHP file inside the web root).
2. `Router` matches the request path + method against `routes.php`.
3. `Auth` middleware checks session validity; `Permission` middleware checks the route's required module/action against the logged-in user's role.
4. The matched controller method runs, talks to models (thin PDO wrappers, not a full ORM), and either renders a view (server-rendered HTML page) or returns JSON (for Alpine.js-driven interactions like approve/reject buttons).
5. Any state-changing action writes to `audit_log` before returning a response — never as an afterthought, never skippable by a controller forgetting to call it (see §13).

This keeps the mental model close to plain PHP-with-organization rather than framework magic, which matters for a solo builder maintaining this long-term.

---

## 3. Technology Stack

| Layer | Choice | Notes |
|---|---|---|
| Language | PHP 8.2 | Typed properties, enums, readonly props used where cPanel's PHP version allows |
| Database | MySQL 8.0+ | InnoDB, utf8mb4, per `schema.sql` |
| DB access | PDO with prepared statements | No raw string concatenation into SQL, ever — see §20 |
| CSS | Tailwind CSS | Compiled via the **standalone Tailwind CLI binary** (no Node.js required on the server — compile locally/in CI, commit the output CSS) |
| JS | Vanilla JS + Alpine.js (CDN or vendored, ~15KB) | Alpine handles reactive UI state and transitions (`x-show`, `x-transition`) without a build step |
| PDF export | `dompdf/dompdf` (Composer) | For Statement of Expenditure and other report exports |
| Email | PHPMailer via Zoho Mail SMTP | Reuses the Zoho Mail free-tier account already chosen for DOTT TV's infrastructure |
| PWA | Web App Manifest + Service Worker (vanilla, no Workbox) | App-shell caching only — see §19 |
| Dependency management | Composer | cPanel supports Composer via SSH or the "Setup PHP App" tool on most hosts |

---

## 4. Application Structure

**Deployment shape note**: this app is served at `dotttv.tv/dotttvfinance` — a subfolder of the main domain, not a dedicated subdomain. That changes how `app/`, `storage/`, and `.env` are protected (see below) but not the internal folder organization itself.

```
dotttvfinance/                      → project root, deployed as one unit into
│                                       public_html/dotttvfinance/ on the server
├── .htaccess                       → denies direct access to this level, rewrites
│                                       all other requests into public_html/
│
├── public_html/                    → the actual front-controller + assets
│   ├── index.php                   → front controller (the ONLY PHP entry point)
│   ├── .htaccess                   → rewrite all requests to index.php
│   ├── manifest.json
│   ├── sw.js                       → service worker
│   └── assets/
│       ├── css/app.css             → compiled Tailwind output
│       ├── js/app.js
│       └── icons/                  → PWA icons, various sizes
│
├── app/                            → protected by the root .htaccess (see note below)
│   ├── config/
│   │   ├── config.php              → loads .env, defines constants
│   │   └── routes.php
│   ├── core/
│   │   ├── Database.php            → PDO singleton
│   │   ├── Router.php
│   │   ├── Auth.php                → session mgmt, login/logout
│   │   ├── Permission.php          → RBAC guard (see §8)
│   │   ├── ApprovalEngine.php      → tier lookup + chain generation (see §9)
│   │   ├── AuditLogger.php         → hash-chain writer (see §13)
│   │   ├── Validator.php
│   │   └── View.php                → minimal template renderer (plain PHP includes, no template DSL)
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── ExpenseController.php
│   │   ├── FundTopupController.php
│   │   ├── PaymentController.php
│   │   ├── InvoiceController.php
│   │   ├── PayrollController.php
│   │   ├── ReportController.php
│   │   ├── SettingsController.php
│   │   └── AuditLogController.php
│   ├── models/
│   │   ├── User.php, Expense.php, ExpenseApproval.php, FundTopup.php,
│   │   │   ApprovalRule.php, FundAccount.php, Invoice.php,
│   │   │   PayrollRun.php, AuditLog.php, Setting.php
│   └── views/
│       ├── layouts/app.php
│       ├── dashboard/, expenses/, fund_topups/, payments/,
│       │   invoices/, payroll/, reports/, settings/, audit_log/
│
├── storage/                        → protected by the root .htaccess (see note below)
│   ├── uploads/
│   │   ├── receipts/
│   │   └── vouchers/
│   └── logs/                       → PHP error logs, not audit_log (that's in the DB)
│
├── vendor/                         → Composer packages
├── composer.json
└── .env                            → DB credentials, SMTP credentials, app secrets (never committed)
```

**On `app/`, `storage/`, and `.env` protection — read this carefully, it's a real trade-off, not a formality.**

With a dedicated subdomain (the original plan), these three would sit physically outside any web-servable document root — Apache never serves that directory at all, so no `.htaccess` rule, correct or misconfigured, changes whether they're reachable. That's a *structural* guarantee.

With this subfolder deployment, `app/` and `storage/` are inside a web-servable tree. What keeps them unreachable is the root `.htaccess`'s `Require all denied` rule combined with the rewrite that forwards all other requests into `public_html/`. That's a **config-correctness guarantee, not a structural impossibility** — it depends on that `.htaccess` file being present, uncorrupted, and actually working on whatever Apache config the host runs, rather than on physical placement. This is standard practice for subfolder deployments and is fine to ship with, but it means the verification step below isn't optional busywork — it's the thing actually standing between this app and its most sensitive files being reachable by URL.

**Before trusting this in any environment** (local XAMPP `htdocs` subfolder or live cPanel), confirm these three URLs all return 403/404, never actual file content:
- `.../dotttvfinance/app/config/config.php`
- `.../dotttvfinance/.env`
- `.../dotttvfinance/storage/uploads/`

Test locally first, then re-test the same three URLs against the live cPanel URL after every deploy that touches the root `.htaccess` — a shared-hosting Apache config can behave differently from a local setup, so a local pass doesn't guarantee a production pass. This check is also in the Deployment Handbook's Part 2 (initial setup) and Part 7 (post-deploy checklist) for the same reason — it's worth repeating rather than assuming it still holds.

---

## 5. Authentication & Session Management

- Passwords hashed with `password_hash()` (bcrypt, cost 12) — never anything reversible or a weaker algorithm.
- PHP native sessions, `session.cookie_httponly=1`, `session.cookie_secure=1` (HTTPS only), `session.use_strict_mode=1`.
- Session regenerates its ID on login (`session_regenerate_id(true)`) to prevent session fixation.
- Idle timeout: 30 minutes for this class of app (financial data), configurable via `settings` table, not hardcoded.
- Every request re-validates the session against the `users` table (checks `status = 'active'`) — a deactivated user is locked out immediately, not just at their next login.
- No "remember me" persistent cookies in v1 — re-authentication on session expiry is a deliberate friction point for a finance tool.

### 5a. Self-Service Password Change

All four roles (Super Admin included — no reason to exclude the role that manages everyone else's accounts from managing their own password) can change their own password from an "Account" menu, without needing Super Admin intervention.

**This is NOT an RBAC-gated action.** It doesn't need a `permissions`/`role_permissions` entry — `Permission::require()` checks access to *other* people's/company data by module, whereas this operates only on the logged-in user's own record. The only check needed is "is this the currently authenticated user's own account," not a module.action lookup.

**Validation, in order:**
1. Current password must verify (`password_verify()`) before anything else — this is the actual security control, since it confirms the person at the keyboard actually knows the existing password, not just that they have an active session (someone briefly at an unlocked, unattended device shouldn't be able to lock the real owner out).
2. New password: minimum 8 characters (no policy previously existed anywhere in the spec — resolving that gap now with a sane baseline, not an elaborate complexity requirement that just encourages sticky notes).
3. New password + confirmation field must match.

**On success:**
- `session_regenerate_id(true)` again, same as login — cheap extra safety.
- Audit log entry: `action = 'password_changed'`, `entity_type = 'users'`, `entity_id = <own id>`. **`before_json`/`after_json` are NULL or contain only a timestamp — the actual password, old or new, is NEVER written to `audit_log` in any form**, hashed or otherwise. The point of the log entry is proving *when* a change happened, not what changed to.
- Current session stays valid (no forced re-login) — regenerating the session id is sufficient; this isn't solving "an attacker has an active session," it's solving "someone doesn't know the real password."
- Known accepted limitation: other active sessions for the same user (e.g. logged in on a second device) are NOT force-invalidated, since there's no server-side session store to reach into — only PHP native file-based sessions per this section's existing design. Not worth adding a full session-tracking table for a 4-person app; flagging it here so it's a documented trade-off, not a silent gap.

---

## 6. Authorization (RBAC) Implementation

`Permission::check($userId, $module, $action)` is called at the top of every controller method that touches financial data — never left to the view layer to decide what to hide, since a hidden button is not a security control.

```php
// Example: guarding an approve action
Permission::require($user, 'expenses', 'approve'); // throws 403 if not permitted
```

Permissions for the logged-in user are loaded once per session (cached in `$_SESSION['permissions']` as a flat array of `"module.action"` strings) at login and refreshed on role change — not re-queried from the DB on every request, since role changes are rare and this keeps hot paths fast without adding a caching layer like Redis (unnecessary at this scale and unavailable on most budget cPanel plans anyway).

---

## 7. Approval Rules Engine — Expense Chain Generation

Core algorithm, run whenever an expense moves from `draft` to submitted:

1. Query `approval_rules` for the single active row where `amount BETWEEN min_amount AND (max_amount OR amount)` — i.e. the bracket the expense amount falls into.
2. Decode `required_roles` JSON (e.g. `["accountant","gm","chairman"]`).
3. For each role in order, insert one `expense_approvals` row: `expense_id`, `cycle_number = expenses.resubmission_count`, `approval_rule_id`, `tier_order`, `required_role_id`, `action = 'pending'`.
4. **Tier 1 auto-approval**: since the Accountant is both the creator and the sole required approver at the lowest tier, that row is inserted as already `action = 'approved'`, `approver_id = creator`, `acted_at = now()` — requiring the Accountant to click "approve" on their own submission would be a redundant extra click, not a real control. This is an implementation decision worth flagging explicitly (see §27) since it's not spelled out verbatim in the PRD.
5. `expenses.status` is set based on the next *pending* tier: `pending_gm` if the next required role is GM, `pending_chairman` if it's Chairman. If the only tier is Accountant, status goes straight to `approved`.

**On approval action** (GM or Chairman approves their tier):
- Update that `expense_approvals` row: `action = 'approved'`, `approver_id`, `acted_at`.
- If there's a next tier, move `expenses.status` to the corresponding `pending_*` value.
- If that was the last tier, `expenses.status = 'approved'`.
- Write to `audit_log`.

**On rejection:**
- The acting row gets `action = 'rejected'`, comment required (not optional — enforced at the Validator layer, not just the DB).
- All other `pending` rows in the same cycle become `cancelled`.
- `expenses.status = 'rejected'`.

**On edit-and-resubmit** (PRD §11):
- `expenses.resubmission_count` increments.
- Step 1–5 above runs again with the (possibly edited) amount — which may land in a different tier than the original submission, generating a fresh chain at the new `cycle_number`. Old rows are never touched or deleted.

**On close:**
- `expenses.status = 'closed'`, `closed_reason` required. No further chain activity.

---

## 8. Fund Top-Up Approval

Deliberately lighter than the expense chain, per the design decision that money coming *in* to the float carries less misuse risk than money going *out*:

1. Accountant submits a top-up request → `fund_topups.status = 'pending'`.
2. Either a GM or a Chairman (both hold `fund_topups.approve`) can clear it — single sign-off, whoever actions it first. No sequencing, no second approver required.
3. On approval: `status = 'approved'`, `approved_by`, `approved_at` set. The `fund_balances` view (§10) picks it up immediately since it filters on `status = 'approved'`.
4. On rejection: `status = 'rejected'`, `rejected_reason` required.

---

## 9. Payment Release

**Assumption** (resolved — see §25): an expense reaching `status = 'approved'` is itself sufficient authorization to release payment. The Payments module's GM/Chairman "Approve" permission (PRD §3.2) is interpreted in v1 as approving the *release details* (payment method, bank account, reference) rather than a second full financial approval chain — since re-litigating an already-approved expense amount at payment time would be redundant, and the real control point (should this money be spent at all) already happened during expense approval. This is a deliberate choice for DOTT TV's current team size and stage, not an oversight — revisit if the team grows or an auditor requires stricter segregation of duties between approving and releasing.

---

## 10. Fund Balance Calculation

All balance reads go through the `fund_balances` VIEW (schema §11) — never a manually maintained number, never computed differently in two places. For the Statement of Expenditure (date-ranged), the same subquery pattern is reused with a `WHERE date BETWEEN :start AND :end` filter, so "balance as of any date" and "movement within any period" both come from one consistent calculation path rather than diverging report-writing logic.

```php
// ReportModel::openingBalance($fundAccountId, $periodStart)
// = fund_balances calculation run with an end-date filter of (periodStart - 1 day)
```

---

## 11. Audit Log — Hash Chain Implementation

Every state-changing action calls `AuditLogger::record()`, which:

1. Fetches the `row_hash` of the most recent `audit_log` row (or a genesis string of 64 zeros if this is the first row ever).
2. Computes `row_hash = hash('sha256', implode('|', [$userId, $action, $entityType, $entityId, $beforeJson, $afterJson, $createdAtIso, $prevHash]))`.
3. Inserts the row with both `prev_hash` and the newly computed `row_hash`.

**Verification script** (`scripts/verify_audit_log.php`, run manually or via monthly cron): walks the table in order, recomputes each row's hash from its stored fields and the previous row's hash, and flags any mismatch. A mismatch means a row was altered outside the application — the exact tamper-evidence property this design is for, and it works regardless of what MySQL user privileges the cPanel host allows (see schema.sql §9 comments).

`AuditLogger::record()` is called from within the **same DB transaction** as the state change it's logging — if the audit write fails, the state change rolls back too. A financial action that isn't logged is treated as an action that didn't happen.

---

## 12. File Uploads (Receipts / Vouchers)

- Stored in `/storage/uploads/` — outside the web root, unreachable by direct URL.
- Validated on upload: MIME type allowlist (PDF, JPG, PNG only), max file size (10MB), filename sanitized and replaced with a generated UUID to prevent path traversal or overwrite attacks.
- Served only through an authenticated download controller (`FileController::download($id)`) that re-checks the requesting user's permission on the parent expense/invoice before streaming the file — never a direct static file URL.

---

## 13. Historical Data Import Tooling

The **Bulk Historical Entry** screen (PRD §5) is a dedicated controller/view, not a repurposed version of the live expense form:

- Table-style input with keyboard-driven row advancement (Enter key moves to the next row's first field via Alpine.js `x-on:keydown.enter`).
- Department is a `<select>`, not free text, to keep department-tagged reporting clean even on backfilled data.
- Document number is optional here (unique constraint is only enforced for `source = 'live'` entries at the application validation layer, since the DB column itself is nullable and non-unique-enforced to accommodate this).
- On save, each row inserts directly with `is_historical = 1`, `source = 'backfilled'`, `status = 'approved'` — skipping `ApprovalEngine` entirely, since these are records of things that already happened, not requests awaiting approval.

---

## 14. Reporting & PDF/Excel Export

- Reports render as HTML first (fast to build, easy to debug), with an "Export PDF" action that passes the same rendered view through `dompdf` server-side, and a separate "Export Excel" action (§14a) — same underlying data, two independent renderers, not one output converted into the other.
- The Statement of Expenditure export specifically pulls: opening balance (§10), funds received in range, approved expenditure in range, outstanding liabilities (unpaid approved expenses), closing balance — matching the format already requested from Ifeoma in the PRD.
- Historical/backfilled entries are visually flagged in every report (a small badge) so GM/Chairman always know which figures were entered in real time vs. reconstructed from the paper book.

### 14a. Fund Ledger — all-departments itemized transaction history with running balance

Added post-Phase-3-build per direct feedback (PRD §6.7). Unlike every other report, this one is a single continuous list, not a summary — every approved/paid expense and every approved top-up, interleaved chronologically, with the balance recalculated after each row. Compute the running balance in SQL via a window function (MySQL 8 supports this — no need for an app-layer loop recalculating row by row, which is slower and a place for off-by-one bugs to creep in):

```sql
SELECT
    ledger.*,
    SUM(amount_in - amount_out) OVER (ORDER BY date, created_at ROWS UNBOUNDED PRECEDING) AS running_balance
FROM (
    SELECT date, created_at, 'topup' AS type, NULL AS document_no, NULL AS payee,
           NULL AS department_id, reference AS description, amount AS amount_in, 0 AS amount_out, is_historical
    FROM fund_topups
    WHERE (status = 'approved' OR is_historical = 1) AND date BETWEEN :start AND :end

    UNION ALL

    SELECT date, created_at, 'expense' AS type, document_no, payee,
           department_id, description, 0 AS amount_in, amount AS amount_out, is_historical
    FROM expenses
    WHERE (status IN ('approved','paid') OR is_historical = 1) AND date BETWEEN :start AND :end
) AS ledger
ORDER BY date, created_at;
```

No `department_id` filter on this query — that's the point of this report. `backfilled_badge()` (UI Component Guide §5a) applies per row same as everywhere else historical data appears.

### 14b. Excel Export

**Library**: `phpoffice/phpspreadsheet` (Composer, pure PHP, no external binary or Node dependency — heavier install than `dompdf` but still a clean shared-hosting fit).

Every report that has a PDF export gets a matching Excel export, built from the same underlying query result — never re-derive the numbers separately for each format, that's the same "two sources of truth" risk already avoided elsewhere (fund balance, audit log). One data-fetching method per report, two renderer functions (`renderPdf($data)`, `renderExcel($data)`) consuming the same array.

Basic formatting worth doing (not just a raw data dump): bold header row, currency number format on amount columns, auto-sized columns, the report title + date range in a merged cell above the table. Doesn't need to match the PDF's exact visual design — it's a working document for Ifeoma/an auditor, not a presentation artifact.

---

## 15. Notifications

Email via PHPMailer + Zoho Mail SMTP (already selected for DOTT TV's infrastructure), triggered on:
- An expense enters a user's approval queue (`pending_gm` → email to GM; `pending_chairman` → email to Chairman).
- An expense is rejected (email to the Accountant).
- A fund top-up is approved/rejected (email to the requesting Accountant).

**Revised**: Web Push (§15a) is added as a second, parallel channel for the same trigger events — not a replacement for email. Email remains the reliable fallback (works even if push permission was never granted or a subscription has gone stale); push adds immediacy for GM/Chairman who may not check email promptly, which directly serves the PRD's goal of approval "from anywhere."

---

## 15a. Web Push Notifications

**Library**: `minishlink/web-push` (Composer, pure PHP, implements the Web Push protocol + VAPID — no Node.js dependency, consistent with the rest of the stack).

**One-time setup**: generate a VAPID key pair (`vendor/bin/web-push-vapid-keys` or the library's key-gen helper), store both in `.env` as `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` — same treatment as every other secret per §19, never committed.

**Subscription flow** (client-side):
1. On login (not on PWA install — see the permission-timing note below), check `Notification.permission`. If `'default'` (never asked), show the dismissible in-app banner from UI Component Guide's Notification Opt-In pattern rather than requesting permission automatically.
2. On the banner's "Enable" button click (a real user gesture — required for the browser to honor the request without flagging it as abusive): call `Notification.requestPermission()`, then on `'granted'`, register via `navigator.serviceWorker.ready` → `pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: VAPID_PUBLIC_KEY })`.
3. POST the resulting subscription object (`endpoint`, `keys.p256dh`, `keys.auth`) to a new endpoint (`POST /push/subscribe`), which upserts a row in `push_subscriptions` (schema.sql §10a) keyed to the logged-in user and that specific `endpoint`.
4. If the user dismisses the banner instead of enabling, don't re-show it every login — store the dismissal (a simple `localStorage` flag is fine here specifically, since it's a UI-preference nicety, not financial data; re-offer it once after e.g. 14 days, not every session).

**On the permission-timing question directly**: there is no browser API to auto-trigger the permission prompt "on PWA install" — `Notification.requestPermission()` requires a user gesture in practice (and unsolicited calls on page load are actively penalized by Chrome's abusive-permission-request heuristics, which can get a site's ability to prompt suppressed entirely). The `appinstalled` event can be listened for to know installation happened, but the actual permission ask still needs to wait for the user to click something. iOS Safari has an additional hard requirement: Web Push only works at all if the PWA was added to the home screen first (iOS 16.4+) — a push subscription attempt in a regular Safari tab silently fails on iOS, so the opt-in banner copy should account for this (e.g. "Install the app first" messaging if `matchMedia('(display-mode: standalone)')` is false on iOS).

**Sending a push** (server-side, `PushNotifier::send($userId, $title, $body, $url)`):
1. Fetch all `push_subscriptions` rows for `$userId`.
2. For each, call the library's send method with the VAPID keys and the payload (title, body, and a `url` field the service worker uses to focus/open the right page on click).
3. On a `410 Gone` or `404` response (subscription expired/revoked), delete that `push_subscriptions` row — don't retry it again, it's dead.
4. Same try/catch-and-log discipline as email (§15's existing pattern): a push failure never blocks the underlying expense/top-up action, and failures log to `storage/logs/`, not to the user as an error.

**Service worker** (`public_html/sw.js`): add a `push` event listener that calls `self.registration.showNotification(title, { body, data: { url } })`, and a `notificationclick` listener that focuses an existing app tab if one's open, or opens `data.url` in a new one otherwise. This is additive to the app-shell caching logic already in §17 — same file, separate event listeners, no conflict.

**What a push notification does NOT do**: it's a nudge with a deep link, not an action button that approves/rejects directly from the notification. Tapping it opens the app to the relevant expense, where the normal `Permission::require()`-gated approve/reject flow takes over — a push payload is never trusted as an authorization surface.

---

## 16. Frontend Architecture

- **Tailwind**: compiled via the standalone CLI (`npx @tailwindcss/cli` locally or in a lightweight CI step, or the platform-specific standalone binary if avoiding Node entirely) — output committed to `public_html/assets/css/app.css`. Never the Play CDN script in production (it ships the full JIT compiler to the browser, which is a performance and reliability anti-pattern for a real app).
- **Alpine.js**: loaded via CDN `<script defer src="...alpinejs...">` for v1 simplicity; can be vendored locally later if offline-shell reliability becomes a concern.
- **Transitions**: `x-transition` for modal/panel open-close, approval status badge changes, and dashboard number updates, kept under ~250ms per the PRD's UI guidance — motion should feel responsive, not decorative, for a tool used daily.
- **AJAX interactions** (approve/reject buttons, live search/filter on tables) hit JSON-returning controller endpoints and update the DOM via Alpine state — full page reloads only for navigation between modules, not for in-page actions.

---

## 17. PWA Implementation

- `manifest.json`: app name, icons (multiple sizes), `display: standalone`, theme color matching DOTT TV's brand palette.
- `sw.js` service worker caches the app shell only — HTML shell, compiled CSS/JS, icons, fonts — using a cache-first strategy with a version-based cache name (bump on deploy to bust stale caches).
- Report *data* may be cached for offline viewing (a "last synced" timestamp shown to the user), but the service worker explicitly does **not** intercept or queue any `POST`/`PUT`/`DELETE` request to a financial endpoint — those always require a live network connection. This is enforced by scoping the service worker's fetch handler to only intercept `GET` requests for static assets, letting all mutating requests pass through untouched (and fail loudly, with a clear "you're offline" message, rather than silently queuing).

---

## 18. Security Checklist

- [ ] All DB queries use PDO prepared statements — no string-concatenated SQL anywhere in the codebase.
- [ ] All output escaped with `htmlspecialchars()` (or equivalent) before rendering — no raw user input echoed into HTML.
- [ ] CSRF token on every state-changing form/AJAX call, validated server-side.
- [ ] Role checked server-side on every request (§6) — client-side role display is cosmetic only, never trusted.
- [ ] File uploads validated by type/size, stored outside web root, served through an authenticated controller (§12).
- [ ] HTTPS enforced (redirect HTTP → HTTPS at `.htaccess` level).
- [ ] Security headers set: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Content-Security-Policy` scoped to needed CDN origins only.
- [ ] Login rate-limited (e.g. 5 attempts per 15 minutes per IP+email combo) to slow brute-force attempts.
- [ ] `.env` file permissions locked down (600) and confirmed unreachable via direct URL as a defense-in-depth check even though it's outside the web root.

---

## 19. Environment & Configuration

- `.env` (via `vlucas/phpdotenv`) holds: DB credentials, SMTP credentials, VAPID key pair (§15a), app secret key, environment flag (`production`/`development`).
- `.env` is gitignored; a `.env.example` with placeholder values is committed for setup reference.
- `app/config/config.php` loads `.env` and defines any derived constants — the only file that reads environment variables directly, so credentials access is centralized and auditable.

---

## 20. Deployment (cPanel)

- Served at `dotttv.tv/dotttvfinance` — a subfolder of the account's main domain, deployed as one unit into `public_html/dotttvfinance/`. This keeps the app isolated from the Staff Hub portal (a separate subfolder or subdomain of its own) without needing a dedicated subdomain for this project.
- `app/`, `storage/`, and `.env` are **not** physically outside the web root in this layout — see §4's note on this. They're protected by the root `.htaccess`'s deny rule, which is a config-correctness guarantee, not a structural one. The three-URL verification check in §4 applies here too, on every deploy that touches the root `.htaccess`.
- Composer dependencies installed via cPanel's "Setup PHP App" tool if it exposes Composer, or via SSH if available on the plan; if neither is available, `vendor/` is built locally (or in CI) and uploaded as part of the deploy — see `DOTT-TV-Finance-System-Deployment-Handbook.md` for the actual GitHub Actions workflows (SSH+rsync or FTP/SFTP, depending on what the hosting plan supports).
- Database: dedicated MySQL database + user created through cPanel's MySQL Databases tool, credentials placed in `.env`, `schema.sql` run once via phpMyAdmin or the `mysql` CLI if SSH access exists.
- A cron job (cPanel's Cron Jobs tool) runs `scripts/verify_audit_log.php` monthly and emails the result to the Super Admin — a scheduled tamper check rather than something that only gets run if someone remembers to.

---

## 21. Error Handling & Logging

- PHP errors/exceptions logged to `storage/logs/` (outside web root), never displayed raw to users in production (`display_errors = Off`).
- A generic, friendly error page shown to users on unhandled exceptions, with a reference ID they can quote — the actual stack trace only in the log file.
- Validation errors (e.g. a rejected expense missing a reason) are handled distinctly from system errors — surfaced inline on the form, not as a generic error page.

---

## 22. Testing Approach

Given a solo builder and a small, well-defined user base, full test-driven development isn't proportionate — but the money-math functions are exactly where a silent bug is most costly, so:

- **Unit tests** (PHPUnit) for: `ApprovalEngine` tier-lookup logic, balance calculation queries, the audit log hash chain (compute + verify round-trip).
- **Manual test checklist** (maintained as a living checklist, not in this document) for full user flows per role: Accountant submitting/editing/resubmitting an expense, GM/Chairman approving/rejecting, Super Admin editing thresholds mid-stream and confirming it doesn't affect already-approved records.
- Before each Phase's rollout (per PRD §9), the manual checklist is run against a staging copy of the database, never production data.

---

## 23. Coding Conventions

- PSR-12 formatting.
- Models: thin — data access and basic validation only, no business logic (that lives in `core/` services like `ApprovalEngine`).
- Controllers: orchestration only — call a permission check, call a model/service, render a view or return JSON. No inline SQL in controllers.
- No magic numbers for status strings — PHP enums (`ExpenseStatus::Approved`) mirroring the DB `ENUM` values, so typos in status strings become caught-at-compile-time errors instead of silent runtime bugs.

---

## 24. Dependencies (Composer)

| Package | Purpose |
|---|---|
| `vlucas/phpdotenv` | `.env` loading |
| `dompdf/dompdf` | PDF export for reports |
| `phpoffice/phpspreadsheet` | Excel export for reports (§14b) |
| `phpmailer/phpmailer` | SMTP email via Zoho Mail |
| `minishlink/web-push` | Web Push notifications (§15a) |
| `phpunit/phpunit` (dev only) | Unit tests |

Kept deliberately minimal — every dependency is a thing that can break on a shared-hosting Composer install, so each addition should earn its place.

**Non-Composer frontend dependencies** (CDN `<script>` tags, not installed via Composer):

| Dependency | Purpose | Notes |
|---|---|---|
| Alpine.js | Reactive UI state, transitions | Already specified in §3 |
| SweetAlert2 | Confirmation modals, toast notifications | UI Component Guide §9b — replaces all raw `alert()`/`confirm()` browser dialogs |

Icons (Lucide) are **not** a runtime dependency at all — they're a self-hosted static SVG sprite file built once (UI Component Guide §9a), not a library loaded on every request.

---

## 25. Open Items Carried Forward

1. **Payment release authorization — resolved**: expense approval alone authorizes payment release. GM/Chairman's "approve" on Payments confirms release details (bank account, reference) rather than re-running a second full approval chain. Chosen deliberately for DOTT TV's current scale (small trusted team, early-stage) — revisit as a candidate control if the team grows, payment volume increases, or an auditor specifically requires segregation of duties between approving spend and releasing funds.
2. **Tier 1 auto-approval — resolved**: the Accountant's own submission at the lowest tier (≤₦50,000, per the seeded default) is treated as automatically approved rather than requiring a separate self-approval click. Confirmed as intended — the Accountant's act of recording the expense at that tier is itself the sign-off, since requiring them to click "approve" on their own submission wouldn't add a real control.
3. **Notification scope** (§15): email-only in v1. Revisit if the team wants approval reminders/escalations (e.g. "pending 48 hours, nudge again") — that's a small cron job addition, not a redesign, but wasn't asked for yet.

---

*Next document, on request: page-by-page wireframe/UI notes, or a build-order checklist mapping directly to the Phase 1–3 breakdown in the PRD.*