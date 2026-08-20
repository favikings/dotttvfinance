# DOTT TV Finance & Accounting System

## Stack

PHP 8.2, MySQL 8, PDO (prepared statements only, never string-concatenated SQL),
Tailwind CSS (standalone CLI, no Node runtime dependency in production), vanilla JS +
Alpine.js, PWA (manifest + service worker). No PHP framework — a small hand-rolled
front-controller + router per Tech Spec §2-4.

## Reference docs (read the relevant one before generating code for any feature)

- `DOTT-TV-Finance-System-PRD.md` — product behavior, roles, module list, business rules
- `schema.sql` — the actual database schema, already finalized, DO NOT modify table
  structure without being explicitly asked; this is the single source of truth for
  every table/column name and type
- `DOTT-TV-Finance-System-Tech-Spec.md` — architecture, folder structure, algorithms
  (approval engine, audit hash chain, balance calculation), security requirements
- `DOTT-TV-Finance-System-Build-Order.md` — the phase-by-phase task breakdown this
  project follows
- `design-system-dotttv.md` — exact design tokens (colors, type scale, spacing,
  radius) for every UI screen. Use these tokens, never invent new colors or spacing.
- `doc/DOTT-TV-Finance-System-UI-Component-Guide.md` — CANONICAL markup for every
  recurring UI pattern (page shell/centering, sidebar, cards, buttons, status badges,
  metric cards, forms, tables, empty states). For ANY screen with a UI component, copy
  the matching pattern from this file exactly rather than composing new Tailwind
  classes. This file exists specifically to stop visual drift across screens — treat
  it as a fixed template, not a style suggestion to reinterpret. Every new screen
  starts from its Page Shell (§1), no exceptions, even a "quick" admin screen.
- `DOTT-TV-Finance-System-Build-Prompts.md` — the prompt-by-prompt build log this
  project is being built from; useful for the intent/acceptance-criteria behind a
  given feature.
- `DOTT-TV-Finance-System-Deployment-Handbook.md` — deployment/ops procedures.

## Non-negotiable rules (apply to every task, don't ask permission each time)

1. Every PDO query uses prepared statements with bound parameters. No exceptions.
2. Every controller action that reads/writes financial data starts with a
   `Permission::require($user, $module, $action)` call per Tech Spec §6.
3. Every state-changing action (create/edit/approve/reject/close/settings change)
   calls `AuditLogger::record()` in the SAME database transaction as the change,
   per Tech Spec §11. If the audit write fails, the whole transaction rolls back.
4. Balance is NEVER read from a manually-maintained column — always through the
   `fund_balances` view (schema.sql §11) or an equivalent date-filtered query.
5. Follow the folder structure in Tech Spec §4 exactly — `app/` and `storage/` outside
   the web root, only `public_html/index.php` as the web-reachable PHP entry point.
6. Use the design tokens from `design-system-dotttv.md` for every color, radius, and
   spacing value — no arbitrary Tailwind utility values invented on the fly.
7. PHP enums for status strings (`ExpenseStatus`, `TopupStatus`, etc.) mirroring the
   DB ENUM values — never bare string literals for status comparisons.
8. Resolved product decisions, don't re-litigate these: Tier 1 (Accountant-only,
   up to ₦50,000) auto-approves on submission with no separate click. Payment
   release requires no second approval chain beyond the expense's own approval —
   GM/Chairman's Payments "approve" is a lightweight release-details confirmation
   only. Fund top-ups use single-approver sign-off (either GM or Chairman, not
   both in sequence) — much lighter than the tiered expense chain.

## UI conformance rules (from the UI Component Guide)

- Every content page wraps its content in the Page Shell (`max-w-6xl mx-auto px-8
  py-8`, nested `max-w-md mx-auto` for narrow auth/single-field forms, `max-w-2xl
  mx-auto` for standard entity forms). This is the fix for inconsistent centering —
  never skip it.
- Cards, buttons, the status badge, the metric card, form inputs, tables, and empty
  states all have one canonical markup pattern in the guide — copy it exactly, don't
  re-improvise a slightly different version per screen.
- Status strings are always rendered through the shared `status_badge()` partial
  (`app/views/partials/status_badge.php`) — never inline color-mapping logic per
  screen.
- Dashboard/report KPIs are always rendered through a shared `metric_card()` partial
  — never copy-pasted markup per widget.
- Never a raw hex value or arbitrary Tailwind color (`text-[#123456]`, `bg-blue-500`)
  — only the named tokens from `design-system-dotttv.md`'s `@theme` block.
- Never a spacing value outside the 8px rhythm (`p-3`, `p-4`, `p-6`, `p-8` — not
  `p-5`, `p-7`).
- If a screen needs a pattern not covered in the guide, build it once, add it to the
  guide, then reuse it everywhere it recurs — never invent a one-off variant.

After generating each feature, state which files you created/modified and which
of the feature's acceptance criteria (given in the Build Prompts doc) you're
confident are met vs. still need manual verification.
