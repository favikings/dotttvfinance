# DOTT TV Finance & Accounting System — UI Component & Layout Guide

**Version:** 1.0
**Purpose:** the canonical source of truth for every recurring UI pattern. When building any new screen, copy the markup below rather than composing Tailwind classes from scratch — drift happens when an agent re-improvises the same "card" or "button" slightly differently on every screen. This stops that.

---

## 0. Why screens have been drifting

Two likely causes, both fixable without a rebuild:

1. **No canonical markup existed to copy from.** Each screen's cards/buttons/badges were independently composed, so small inconsistencies (padding, border color, radius) accumulate across dozens of screens.
2. **No page-container convention.** Without a fixed max-width + centering wrapper around content, screens stretch edge-to-edge on wide monitors or sit flush against the sidebar with no breathing room — which reads as "not centered" even when nothing is technically broken.

Both are solved by treating everything below as fixed, copy-exactly markup — not a style guide to interpret, a literal template.

---

## 1. Page Shell & Centering (fixes "not centered")

Every content page (everything inside the sidebar layout) wraps its content in this container. This is the single biggest fix for the centering complaint:

```html
<div class="min-h-screen bg-surface">
  <main class="max-w-6xl mx-auto px-8 py-8">
    <!-- page content goes here -->
  </main>
</div>
```

- `max-w-6xl mx-auto` — content never stretches past a readable width, and centers itself in the remaining space next to the 260px sidebar, regardless of monitor width.
- `px-8 py-8` — consistent outer breathing room, matches the design system's `container-padding: 2rem` token.
- Narrower forms (login, a single expense entry form, settings edit forms) nest an additional constraint inside this:
  ```html
  <div class="max-w-md mx-auto"> <!-- for auth/narrow single-field forms -->
  <div class="max-w-2xl mx-auto"> <!-- for standard entity forms -->
  ```
- Wide data views (tables, the historical bulk-entry grid) skip the inner constraint and use the full `max-w-6xl` width.

---

## 1a. Auth Card (Login screen only)

The one screen that intentionally does NOT use the sidebar shell — full-canvas, centered, no navigation chrome pre-authentication.

```html
<div class="min-h-screen bg-surface-container flex items-center justify-center px-6">
  <div class="w-full max-w-[400px] bg-surface-container-lowest border border-outline-variant
              rounded-xl p-10 shadow-[0_4px_12px_rgba(0,0,0,0.04)]">

    <h1 class="text-2xl font-semibold tracking-tight text-on-surface mb-1.5">Welcome back</h1>
    <p class="text-sm text-on-surface-variant mb-7">Sign in to DOTT TV Finance</p>

    <form method="POST" action="/login">
      <label class="block text-sm font-medium text-on-surface mb-1.5" for="email">Email address</label>
      <input type="email" id="email" name="email" placeholder="ifeoma@dotttv.tv"
             class="w-full px-3.5 py-2.5 rounded border border-outline bg-surface-container-lowest
                    text-on-surface text-sm mb-5 focus:outline-none focus:ring-2
                    focus:ring-secondary-container focus:border-transparent">

      <label class="block text-sm font-medium text-on-surface mb-1.5" for="password">Password</label>
      <div class="relative mb-5" x-data="{ showPassword: false }">
        <input :type="showPassword ? 'text' : 'password'" id="password" name="password" placeholder="••••••••••"
               class="w-full px-3.5 py-2.5 pr-10 rounded border border-outline
                      bg-surface-container-lowest text-on-surface text-sm
                      focus:outline-none focus:ring-2 focus:ring-secondary-container
                      focus:border-transparent">
        <button type="button" x-on:click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-outline"
                :aria-label="showPassword ? 'Hide password' : 'Show password'">
          <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
          <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" width="18" height="18"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round">
            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.6 21.6 0 0 1 5.06-6.06M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a21.6 21.6 0 0 1-3.22 4.44M1 1l22 22"/>
            <path d="M14.12 14.12A3 3 0 1 1 9.88 9.88"/>
          </svg>
        </button>
      </div>

      <button type="submit"
              class="w-full py-3 rounded bg-primary text-on-primary text-sm font-semibold
                     hover:opacity-90 transition-opacity mb-5">
        Sign in
      </button>
    </form>

    <p class="text-xs text-outline text-center">Can't sign in? Contact your Super Admin.</p>
  </div>
</div>
```

**Requires `x-cloak` support globally**: add this once to `public_html/assets/css/app.css` (or the Tailwind source file) if it's not already there — without it, the hidden SVG flashes visible for a split second before Alpine finishes initializing on page load:
```css
[x-cloak] { display: none !important; }
```

**Deliberately excluded, don't add these back without a real decision behind them:**
- "Keep me signed in" checkbox — contradicts Tech Spec §5 (no persistent sessions; re-auth on session expiry is intentional friction for a finance tool)
- Google/GitHub/SSO buttons — out of scope, DOTT TV's 4 users are provisioned directly by the Super Admin, not self-service or federated
- "Register here" — no public registration exists or should exist for this app
- "Forgot password?" as a working link — no password-reset flow is speced yet; the current copy ("Contact your Super Admin") is the actual v1 recovery path. If self-service reset is wanted later, that's a real feature (email token flow) to scope properly, not a UI-only addition.

---


```html
<div class="flex min-h-screen bg-surface">
  <aside class="fixed w-[260px] h-screen bg-primary text-on-primary flex flex-col">
    <!-- logo/brand area -->
    <div class="px-6 py-6">
      <span class="text-lg font-semibold">DOTT TV Finance</span>
    </div>
    <!-- nav items -->
    <nav class="flex-1 px-3 space-y-1">
      <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded text-sm font-medium
                          bg-white/10 border-l-2 border-secondary-container">
        <!-- active state: soft white overlay + left bar, per design system -->
        Dashboard
      </a>
      <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded text-sm font-medium
                          text-on-primary/70 hover:bg-white/5 transition-colors">
        Expenses
      </a>
    </nav>
  </aside>

  <div class="flex-1 ml-[260px]">
    <!-- Page Shell from §1 goes here -->
  </div>
</div>
```

Active nav state = `bg-white/10` + left border, exactly per design-system-dotttv.md's "10-15% opacity overlay + vertical bar" spec — never a solid background swap, which reads flat against the navy.

---

## 2. Mobile Shell — Top Bar, Drawer & Bottom Tabs (< 768px)

Below `md`, the 260px sidebar in `app/views/layouts/app.php` is hidden and replaced by three fixed pieces. This is the ONLY mobile layout pattern — copy it, don't improvise per-screen variants.

1. **Sticky top bar** — `md:hidden sticky top-0 z-30 bg-primary text-on-primary px-4 py-3`, app name left, a single hamburger button (`#menu` sprite icon) right that sets `drawerOpen = true` on the root `x-data="{ drawerOpen: false }"`.
2. **Right-side drawer** — `md:hidden fixed inset-0 z-40 bg-black/40`, closes on outside tap (`@click="drawerOpen = false"`); the panel is `absolute inset-y-0 right-0 w-72 max-w-[85vw] bg-primary text-on-primary flex flex-col` with the `#x` close button, the same data-driven nav loop as the sidebar (identical active styling: `bg-white/15 text-on-primary border-l-2 border-secondary`), and the user/logout row. The desktop sidebar markup itself stays untouched — the drawer reuses the `$navItems`/`$activeHref` computed in the layout.
3. **Bottom tab bar** — `md:hidden fixed bottom-0 inset-x-0 z-30 bg-primary text-on-primary flex justify-around border-t border-white/10 pt-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))]` (the `env()` keeps tabs clear of the iOS home indicator). Exactly **three** tabs: Dashboard, Expenses, Fund Top-Ups (filtered from `$navItems` by `href`). Active tab = `text-on-primary border-t-2 border-secondary`; inactive = `text-on-primary/80`.

Rules that go with it:
- `main` mobile padding is `p-4` per design-system's 1rem reflow, plus `pb-24 md:pb-8` so the fixed bottom bar never covers the last table row.
- Every screen's table container uses the §8 two-div pattern — outer `overflow-hidden` for the card look, inner `overflow-x-auto table-scroll` around the `<table>` — without it the table's min-content width forces the whole page wider than the viewport on phones, and a single `overflow-hidden` clips the columns unreachably.
- Metric-card grids must be `grid-cols-1 sm:grid-cols-2 md:grid-cols-N`, never `grid-cols-2` at the phone breakpoint — a long naira value overflows a 2-up phone column.

---

## 3. Card

```html
<div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6
            shadow-[0_4px_12px_rgba(0,0,0,0.04)]">
  <!-- card content -->
</div>
```

Never a heavier shadow than this. Never a different radius than `rounded-lg` for a standard card.

---

## 4. Buttons

```html
<!-- Primary action -->
<button class="bg-secondary-container text-on-secondary-container font-medium text-sm
               px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
  Save Expense
</button>

<!-- Secondary / ghost -->
<button class="border border-outline text-on-surface font-medium text-sm
               px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
  Cancel
</button>

<!-- Destructive -->
<button class="bg-error text-on-error font-medium text-sm
               px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
  Reject
</button>
```

---

## 5. Status Badge

One component, reused for every status across every module (expenses, invoices, top-ups, payroll):

```php
<?php
// app/views/partials/status_badge.php
// Usage: <?= status_badge('pending_gm') ?>
function status_badge(string $status): string {
    $map = [
        'draft'            => ['bg-surface-container-high text-on-surface-variant', 'Draft'],
        'pending_gm'       => ['bg-secondary-container/30 text-on-secondary-container', 'Pending GM'],
        'pending_chairman' => ['bg-secondary-container/30 text-on-secondary-container', 'Pending Chairman'],
        'approved'         => ['bg-emerald-100 text-emerald-700', 'Approved'],
        'rejected'         => ['bg-error-container text-on-error-container', 'Rejected'],
        'closed'           => ['bg-surface-container-high text-on-surface-variant', 'Closed'],
        'paid'             => ['bg-emerald-100 text-emerald-700', 'Paid'],
    ];
    [$classes, $label] = $map[$status] ?? ['bg-surface-container text-on-surface-variant', ucfirst($status)];
    return "<span class=\"inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {$classes}\">{$label}</span>";
}
```

Always pill-shaped (`rounded-full`), always soft-tinted, never a solid saturated background — that's the design system's "high-trust, non-aggressive signaling" requirement.

---

## 5a. Backfilled / Historical Badge

Required wherever historical (`is_historical = 1`) data appears — expense lists, top-up lists, and every Phase 3 report total that includes backfilled figures (PRD §5, §14). One shared helper, called with the row's actual flag — never hardcoded to always show or always hide:

```php
if (!function_exists('backfilled_badge')) {
    function backfilled_badge(bool $isHistorical): string {
        if (!$isHistorical) {
            return '';
        }
        return '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-surface-container-high text-on-surface-variant">Backfilled</span>';
    }
}
```

Usage: `<?= backfilled_badge($expense['is_historical']) ?>` next to (or instead of) the status badge on any row where historical data may appear — never a separate ad-hoc "historical" label invented per screen.

---

## 6. Metric Card (Dashboard KPIs)

```html
<div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6">
  <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">
    Current Balance
  </p>
  <p class="text-[32px] font-semibold leading-tight tracking-tight text-on-surface">
    ₦1,240,500
  </p>
  <p class="text-[11px] font-semibold text-emerald-600 mt-2">
    +12% this month
  </p>
</div>
```

Build this as one real reusable PHP partial (`metric_card($label, $value, $trend)`), not copy-pasted markup per dashboard widget — the Build Order already calls this out, worth double-checking it actually happened as a single component.

---

## 7. Form Input + Form Row

```html
<div class="mb-4">
  <label class="block text-sm font-medium text-on-surface mb-1.5" for="amount">
    Amount
  </label>
  <input type="number" id="amount" name="amount"
         class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest
                text-on-surface focus:outline-none focus:ring-2 focus:ring-secondary-container
                focus:border-transparent">
</div>
```

Two-column layout for related fields (e.g. date + department side by side):

```html
<div class="grid grid-cols-2 gap-4 mb-4">
  <div><!-- form input --></div>
  <div><!-- form input --></div>
</div>
```

---

## 8. Table

**Two-div wrapper — copy exactly, never put `overflow-hidden` directly on the same div that wraps the table.** Splitting the wrapper is what makes the table horizontally scrollable on narrow viewports:

- The **OUTER div** (`bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden`) supplies the card look, the rounded corners, and the vertical clipping. `overflow-hidden` stays here ONLY — it must not sit on the scroll div, or it blocks scrolling in every direction.
- The **INNER div** (`overflow-x-auto table-scroll`) wraps just the `<table>` and is what actually enables horizontal scroll. `table-scroll` drives the edge-fade affordance and horizontal overscroll containment (see §8c).
- If a header row sits inside the card (e.g. the dashboard's "Recent activity" title), keep it OUTSIDE the inner scroll div so it stays put while the columns scroll beneath it.
- **The `<table>` itself carries an explicit `min-w-…` class — this is what makes the scroll happen at all.** A `w-full` table in `table-layout: auto` will happily compress its columns to fit the wrapper, and if the columns compress there is no overflow and `overflow-x-auto` has nothing to scroll. The min-width stops the compression: the table renders at `max(container width, min-width)`, so whenever the container is narrower than the minimum the table overflows the inner div and that overflow becomes the scroll. On desktop the container is wider than the minimum, `width: 100%` wins, and the table still renders full-width with no scrollbar. Choose the value by column count — `min-w-[640px]` for a standard 4–6 column table, roughly +80px per additional column, wider for input grids (the 7-column Bulk Historical Entry Table in §8a uses `min-w-[900px]`). When in doubt, count the `<th>`s and size the minimum to the sum of their comfortable widths.
- **Cells holding text that shouldn't wrap** (names, payees, dates, amounts, references, status labels) carry `whitespace-nowrap`. A cell whose text wraps keeps shrinking its column, which silently reduces the table's content width and fights the min-width above. `whitespace-nowrap` forces that column to hold its content width.

```html
<div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
  <div class="overflow-x-auto table-scroll">
    <table class="w-full text-sm min-w-[640px]">
      <thead class="bg-surface-container-low border-b border-outline-variant">
        <tr>
          <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Date</th>
          <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Payee</th>
          <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Amount</th>
          <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-outline-variant">
        <tr class="hover:bg-surface-container-low transition-colors">
          <td class="px-4 py-3 text-on-surface whitespace-nowrap">10/06/2026</td>
          <td class="px-4 py-3 text-on-surface whitespace-nowrap">Chika</td>
          <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap">₦23,700</td>
          <td class="px-4 py-3"><!-- status_badge() here --></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
```

Never a raw `overflow-hidden` on a div that directly contains a `<table>` — that clips columns with no way to reach them.

---

## 8a. Bulk Historical Entry Table (Prompt 1.5)

The full working Alpine.js data structure — this was previously left as a described behavior ("Enter key advances rows") without actual working code, which is why the Add Row button broke. This is the real, complete pattern; copy it exactly rather than reimplementing the row-management logic.

```html
<div x-data="historicalEntryForm()" class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
  <div class="overflow-x-auto table-scroll">
    <table class="w-full text-sm min-w-[900px]">
      <thead class="bg-surface-container-low border-b border-outline-variant">
        <tr>
          <th class="text-left px-3 py-3 font-medium text-on-surface-variant">Date</th>
          <th class="text-left px-3 py-3 font-medium text-on-surface-variant">Doc No.</th>
          <th class="text-left px-3 py-3 font-medium text-on-surface-variant">Payee</th>
          <th class="text-left px-3 py-3 font-medium text-on-surface-variant">Description</th>
          <th class="text-left px-3 py-3 font-medium text-on-surface-variant">Department</th>
          <th class="text-right px-3 py-3 font-medium text-on-surface-variant">Amount</th>
          <th class="px-3 py-3 w-10"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-outline-variant" x-ref="tableBody">
        <template x-for="(row, index) in rows" :key="row.id">
          <tr>
            <td class="px-2 py-2"><input type="date" x-model="row.date"
                class="w-full px-2 py-1.5 rounded border border-outline text-sm"></td>
            <td class="px-2 py-2"><input type="text" x-model="row.document_no"
                class="w-full px-2 py-1.5 rounded border border-outline text-sm"></td>
            <td class="px-2 py-2"><input type="text" x-model="row.payee"
                class="w-full px-2 py-1.5 rounded border border-outline text-sm"></td>
            <td class="px-2 py-2"><input type="text" x-model="row.description"
                class="w-full px-2 py-1.5 rounded border border-outline text-sm"></td>
            <td class="px-2 py-2">
              <select x-model="row.department_id" class="w-full px-2 py-1.5 rounded border border-outline text-sm">
                <option value="">Select...</option>
                <!-- department options rendered server-side, injected once via PHP foreach -->
              </select>
            </td>
            <td class="px-2 py-2">
              <input type="number" x-model="row.amount"
                     x-on:keydown.enter.prevent="addRow(index)"
                     class="w-full px-2 py-1.5 rounded border border-outline text-sm text-right">
            </td>
            <td class="px-2 py-2 text-center">
              <button type="button" x-on:click="removeRow(index)"
                      x-show="rows.length > 1" class="text-outline hover:text-error">
                <svg class="w-4 h-4" stroke="currentColor" fill="none"><use href="/assets/icons/sprite.svg#trash-2"></use></svg>
              </button>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>

  <div class="p-4 border-t border-outline-variant flex justify-between">
    <button type="button" x-on:click="addRow()"
            class="border border-outline text-on-surface text-sm font-medium px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
      + Add Row
    </button>
    <button type="button" x-on:click="saveAll()"
            class="bg-primary text-on-primary text-sm font-semibold px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
      Save All
    </button>
  </div>
</div>

<script>
function historicalEntryForm() {
  let nextId = 1;
  return {
    rows: [{ id: nextId++, date: '', document_no: '', payee: '', description: '', department_id: '', amount: '' }],
    addRow(afterIndex = null) {
      this.rows.push({ id: nextId++, date: '', document_no: '', payee: '', description: '', department_id: '', amount: '' });
      this.$nextTick(() => {
        const lastRow = this.$refs.tableBody.querySelector('tr:last-child input');
        if (lastRow) lastRow.focus();
      });
    },
    removeRow(index) {
      if (this.rows.length > 1) this.rows.splice(index, 1);
    },
    async saveAll() {
      // POST this.rows to the bulk-entry endpoint; each row inserts server-side
      // with is_historical=1, source='backfilled', status='approved' per Tech Spec §13
    }
  };
}
</script>
```

**The two bugs this fixes, both worth checking in whatever's currently built:**
- Every button has an explicit `type="button"` — without it, a button inside a `<form>` defaults to `type="submit"` and reloads the page instead of running the click handler. This is almost certainly what broke Add Row.
- `x-data="historicalEntryForm()"` wraps the *entire* table AND both buttons in one scope — if Add Row currently sits outside whatever element has `x-data`, Alpine has no idea what `addRow()` is and the click silently does nothing.

---

## 8c. Scrollable-Table Edge Fade (the "swipe to see more" affordance)

A table that's just clipped looks identical to one that's merely narrow — so without a hint, users on phones never discover the horizontal scroll at all. Every `table-scroll` container therefore gets a soft gradient fade on the trailing edge whenever there is more content to scroll to.

This is implemented ONCE in shared code, not per-screen markup:
- **CSS** in `build/tailwind/app.source.css`: `.table-scroll` sets `overscroll-behavior-x: contain` (horizontal swipes on the table never fight the page's vertical scroll) and `-webkit-overflow-scrolling: touch`; `.table-scroll.has-more-right` / `.has-more-left` apply a `mask-image` linear gradient that fades the edge.
- **JS** in `public_html/assets/js/app.js`: `initTableScrollFades()` measures every `.table-scroll` element and toggles `has-more-right` while `scrollLeft` hasn't reached the end, and `has-more-left` once the user has scrolled away from the start. It re-measures on scroll, resize, and (via a `MutationObserver`) whenever Alpine re-renders an `x-show`/`x-for` table.

Desktop is unaffected: at widths where the table fits its container there is no overflow, `has-more-right` stays off, and the table renders fully visible with no fade and no scrollbar — `overflow-x-auto` only does anything when content is actually wider than its container.

---

## 8b. Audit Log Diff View

The audit log currently dumps raw `before_json`/`after_json` in the "View details" modal — technically correct, but unreadable for GM/Chairman. Replace it with a field-level diff that only shows what actually changed, formatted as old → new, with foreign-key IDs resolved to actual names.

```php
if (!function_exists('resolve_audit_value')) {
    function resolve_audit_value(PDO $db, string $key, $value): string {
        static $cache = [];

        if ($value === null || $value === '') {
            return '<span class="text-outline italic">—</span>';
        }

        // Fixed internal whitelist only — table/column names here are never derived
        // from user input, so string interpolation into SQL below is safe. Only the
        // id VALUE is a bound parameter.
        $fkMap = [
            'department_id'    => ['departments', 'name'],
            'category_id'      => ['expense_categories', 'name'],
            'fund_account_id'  => ['fund_account', 'name'],
            'role_id'          => ['roles', 'name'],
            'required_role_id' => ['roles', 'name'],
        ];

        $isUserRef = str_ends_with($key, '_by') || $key === 'approver_id';

        if ($isUserRef) {
            [$table, $column] = ['users', 'name'];
        } elseif (isset($fkMap[$key])) {
            [$table, $column] = $fkMap[$key];
        } else {
            return htmlspecialchars((string) $value); // not a known FK — show as-is
        }

        $cacheKey = "{$table}:{$value}";
        if (!array_key_exists($cacheKey, $cache)) {
            $stmt = $db->prepare("SELECT {$column} FROM {$table} WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $value]);
            $cache[$cacheKey] = $stmt->fetchColumn() ?: null;
        }

        $resolved = $cache[$cacheKey];
        return $resolved
            ? htmlspecialchars($resolved) . ' <span class="text-outline text-xs">(#' . htmlspecialchars((string) $value) . ')</span>'
            : htmlspecialchars((string) $value); // fallback if the referenced record was deleted or id not found
    }
}

if (!function_exists('audit_diff_view')) {
    function audit_diff_view(PDO $db, ?string $beforeJson, ?string $afterJson): string {
        $before = $beforeJson ? json_decode($beforeJson, true) : [];
        $after  = $afterJson  ? json_decode($afterJson, true)  : [];
        $keys = array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? [])));

        $rows = '';
        foreach ($keys as $key) {
            $oldVal = $before[$key] ?? null;
            $newVal = $after[$key] ?? null;
            if ($oldVal === $newVal) continue; // only show fields that actually changed

            $label = ucwords(str_replace('_', ' ', $key));
            $oldDisplay = resolve_audit_value($db, $key, $oldVal);
            $newDisplay = resolve_audit_value($db, $key, $newVal);

            $rows .= <<<HTML
            <div class="flex items-center justify-between py-2.5 border-b border-outline-variant last:border-0">
              <span class="text-sm font-medium text-on-surface-variant">{$label}</span>
              <div class="flex items-center gap-2 text-sm">
                <span class="bg-error-container text-on-error-container px-2 py-0.5 rounded line-through">{$oldDisplay}</span>
                <svg class="w-3.5 h-3.5 text-outline" stroke="currentColor" fill="none"><use href="/assets/icons/sprite.svg#arrow-right"></use></svg>
                <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded font-medium">{$newDisplay}</span>
              </div>
            </div>
            HTML;
        }

        if ($rows === '') {
            return '<p class="text-sm text-on-surface-variant italic">No field changes recorded.</p>';
        }
        return '<div class="divide-y divide-outline-variant">' . $rows . '</div>';
    }
}
```

`resolve_audit_value()` only fires a DB lookup for keys it recognizes as a foreign key (per-request cached, so the same id looked up twice in one page render only hits the DB once) — everything else (amounts, dates, free-text fields) passes straight through unchanged.

**In the "View details" modal**, show `audit_diff_view()` as the primary content. Keep the raw JSON available too — audit records are compliance-sensitive, so exact values should never be fully hidden — but demoted to a collapsed, optional toggle beneath the diff:

```html
<div x-data="{ showRaw: false }">
  <?= audit_diff_view($db, $row['before_json'], $row['after_json']) ?>

  <button type="button" x-on:click="showRaw = !showRaw"
          class="text-xs text-outline mt-3 hover:text-on-surface">
    <span x-show="!showRaw">Show raw JSON</span>
    <span x-show="showRaw" x-cloak>Hide raw JSON</span>
  </button>
  <pre x-show="showRaw" x-cloak class="mt-2 p-3 bg-surface-container rounded text-xs overflow-x-auto"><?= htmlspecialchars(json_encode(['before' => json_decode($row['before_json']), 'after' => json_decode($row['after_json'])], JSON_PRETTY_PRINT)) ?></pre>
</div>
```

---

## 9. Empty State

```html
<div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-12 text-center">
  <p class="text-sm font-medium text-on-surface mb-1">No expenses yet</p>
  <p class="text-sm text-on-surface-variant">Record your first expense to see it here.</p>
</div>
```

Per the design system's writing guidance — an empty state is an invitation to act, not an apology. Never "Oops, nothing here!"

---

## 9a. Icons

**Icon set:** Lucide (MIT licensed, thin 2px stroke, rounded caps — matches the eye/eye-slash icons already used in the Auth Card, and fits the "authoritative yet accessible" tone of Horizon Finance far better than a filled/playful icon set).

**Delivery method:** one self-hosted SVG sprite file, not a CDN icon font. Reasons this matters for this stack specifically: no Node/build-step dependency added, works offline once cached (the sprite is a static asset the service worker's app-shell cache list should include per Tech Spec §17), and no external request at runtime that could fail or get blocked.

**Setup (one-time):** pull the needed icons' raw SVG paths from Lucide's source, combine them into `public_html/assets/icons/sprite.svg` using `<symbol id="icon-name">...</symbol>` per icon, wrapped in one `<svg>` root with `display: none` (or hidden via CSS) since it's referenced, not rendered directly.

**Usage in any view:**
```html
<svg class="w-4 h-4" stroke="currentColor" fill="none">
  <use href="/assets/icons/sprite.svg#icon-name"></use>
</svg>
```
`currentColor` means the icon inherits whatever text color class is on it or a parent — no separate color prop needed.

**Module → icon mapping** (Lucide names, for sidebar nav and section headers):

| Module | Icon |
|---|---|
| Dashboard | `layout-dashboard` |
| Invoices | `file-text` |
| Expenses | `receipt` |
| Petty Cash | `wallet` |
| Fund Top-Ups | `arrow-up-circle` |
| Payments | `banknote` |
| Payroll | `users` |
| Reports | `bar-chart-3` |
| Settings | `settings` |
| Audit Log | `history` |
| User Management | `user-cog` |
| Logout | `log-out` |
| Edit | `pencil` |
| Delete/void | `trash-2` |
| Approve/confirm | `check` |
| Reject/close | `x` |
| Warning (destructive confirm) | `triangle-alert` |
| Export/download | `download` |
| Diff arrow (audit log) | `arrow-right` |
| Notifications | `bell` |

If a screen needs an icon not on this list, add it to the sprite and to this table — never a one-off inline SVG pasted directly into a view, that's the same drift problem as an un-cataloged card or button variant.

---

## 9b. Alerts, Confirmations & Toasts

**Library: SweetAlert2**, loaded via CDN (`<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>`) in the base layout. No build step, promise-based (pairs naturally with the Alpine-driven AJAX approve/reject flow in Tech Spec §16), and covers both confirmation modals and toast notifications — one dependency instead of two.

**Themed to match Horizon Finance** — never use SweetAlert2's default styling unmodified. Add this once, near where Alpine/SweetAlert2 are loaded:

```js
const dottAlert = Swal.mixin({
  customClass: {
    popup: 'rounded-xl border border-outline-variant',
    confirmButton: 'bg-primary text-on-primary text-sm font-semibold px-4 py-2.5 rounded mx-1',
    cancelButton: 'border border-outline text-on-surface text-sm font-medium px-4 py-2.5 rounded mx-1',
    denyButton: 'bg-error text-on-error text-sm font-semibold px-4 py-2.5 rounded mx-1',
  },
  buttonsStyling: false, // forces SweetAlert2 to use the classes above instead of its own inline styles
});
```

**Destructive/high-stakes confirmation** (reject an expense, close an expense, delete a category, deactivate a user — anything PRD marks as requiring a reason):
```js
const { value: comment } = await dottAlert.fire({
  title: 'Reject this expense?',
  input: 'textarea',
  inputPlaceholder: 'Reason for rejection (required)',
  inputValidator: (value) => !value && 'A reason is required',
  showCancelButton: true,
  confirmButtonText: 'Reject',
  cancelButtonText: 'Cancel',
});
```

**Success/error toast** (after any state-changing action completes):
```js
const dottToast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
  customClass: { popup: 'rounded-lg' },
});

dottToast.fire({ icon: 'success', title: 'Expense submitted' });
dottToast.fire({ icon: 'error', title: 'Could not save — check required fields' });
```

Never use a raw `alert()`/`confirm()` browser dialog anywhere in the app — every confirmation and every action-result notification goes through `dottAlert`/`dottToast` for visual consistency.

---

## 9c. Notification Opt-In Banner

Shown once per session on login if `Notification.permission === 'default'` and the user hasn't dismissed it recently (see Tech Spec §15a for the dismissal/re-offer logic). Dismissible, never a blocking modal — a finance tool shouldn't force a decision before letting someone get to work.

```html
<div x-data="{ visible: true }" x-show="visible" x-cloak
     class="bg-secondary-container/15 border border-secondary-container rounded-lg p-4 mb-6 flex items-center justify-between">
  <div class="flex items-center gap-3">
    <svg class="w-5 h-5 text-secondary" stroke="currentColor" fill="none"><use href="/assets/icons/sprite.svg#bell"></use></svg>
    <p class="text-sm text-on-surface">Get notified the moment an expense needs your approval.</p>
  </div>
  <div class="flex items-center gap-2">
    <button type="button" x-on:click="enablePushNotifications(); visible = false"
            class="bg-primary text-on-primary text-sm font-semibold px-4 py-2 rounded hover:opacity-90 transition-opacity">
      Enable
    </button>
    <button type="button" x-on:click="visible = false; dismissNotificationBanner()"
            class="text-sm text-on-surface-variant px-3 py-2 hover:text-on-surface">
      Not now
    </button>
  </div>
</div>
```

`enablePushNotifications()` and `dismissNotificationBanner()` are the two JS functions implementing the actual permission request and dismissal-tracking logic from Tech Spec §15a — this markup is just the trigger UI, the real subscription flow lives in shared JS, not inline here.

**On iOS**, if `matchMedia('(display-mode: standalone)').matches` is false, swap the banner copy to prompt installing the app first (Web Push doesn't work in a regular Safari tab on iOS — see Tech Spec §15a):
```html
<p class="text-sm text-on-surface">Install this app to your home screen to get approval notifications on iPhone.</p>
```

Add `bell` to the icon sprite (§9a) if it isn't already there.

---

## 9d. PWA Install Banner (Tech Spec §17)

Shown only when the browser fires `beforeinstallprompt` (Chrome desktop + Chrome Android) and the user hasn't dismissed it in the last 14 days. Dismissible, never a blocking modal. The standard pattern: the handler `preventDefault()`s the event and stashes it, and `prompt()` is only called from the Install button's click (a real user gesture). Rendered once in the app layout via `install_banner()` — same trigger-markup approach as §9c, real logic in `installBannerState()` in `app.js`.

```html
<div x-data="installBannerState()" x-show="visible" x-cloak
     class="bg-secondary-container/15 border border-secondary-container rounded-lg p-4 mb-6 flex items-center justify-between">
  <div class="flex items-center gap-3">
    <svg class="w-5 h-5 text-secondary" stroke="currentColor" fill="none"><use href="/assets/icons/sprite.svg#download"></use></svg>
    <p class="text-sm text-on-surface">Install DOTT TV Finance for a faster, offline-capable experience.</p>
  </div>
  <div class="flex items-center gap-2">
    <button type="button" x-on:click="install()"
            class="bg-primary text-on-primary text-sm font-semibold px-4 py-2 rounded hover:opacity-90 transition-opacity">
      Install
    </button>
    <button type="button" x-on:click="dismiss()"
            class="text-sm text-on-surface-variant px-3 py-2 hover:text-on-surface">
      Not now
    </button>
  </div>
</div>
```

`installBannerState()` handles the capture/dismiss/re-offer bookkeeping; `appinstalled` hides the banner for good. iOS Safari never fires `beforeinstallprompt` — the §9c banner already swaps to an "install to home screen" nudge there for push, which covers iOS installability.

## 9e. "Last Synced" Indicator (Tech Spec §17)

Report pages are the one offline-cacheable *data* surface (viewing a stale report offline is safe; submitting a stale write isn't), so every report screen shows how current its numbers are. Pure client-side via `syncIndicator()` in `app.js` — it records the last successful page load in `localStorage` and re-renders on `online`/`offline` events. Green dot + "Synced &lt;time&gt;" when online, error-colored dot + "Last synced &lt;time&gt;" when serving a cached copy offline.

```html
<div x-data="syncIndicator()"
     class="inline-flex items-center gap-1.5 text-label-sm text-on-surface-variant">
  <span class="inline-block w-1.5 h-1.5 rounded-full" :class="offline ? 'bg-error' : 'bg-success'"></span>
  <span x-text="label"></span>
</div>
```

Emit it on every report screen with `<?= sync_indicator() ?>` directly under the `date_range_filter()` partial.

---

## 10. Non-negotiable rules for every screen

- Never a raw hex value or arbitrary Tailwind color (`text-[#123456]`, `bg-blue-500`) — only the named tokens from `design-system-dotttv.md`'s `@theme` block.
- Never a spacing value outside the 8px rhythm (`p-3`, `p-4`, `p-6`, `p-8` — not `p-5`, `p-7`).
- Every new screen starts from the Page Shell (§1) — no exceptions, even a "quick" admin screen.
- If a screen needs a pattern not covered above, build it once, add it to this document, then use it everywhere it recurs — never invent a one-off variant.