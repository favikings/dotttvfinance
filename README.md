# DOTT TV Finance & Accounting System

Finance Tracker for DOTT TV. See `doc/` for the PRD, Tech Spec, Build Order, and design system — read the relevant one before touching any feature.

## Local development (XAMPP)

**Main URL: `http://localhost/dotttvfinance/`**

Production is a subfolder deploy — `dotttv.tv/dotttvfinance`, inside the main domain, not a dedicated subdomain (Deployment Handbook Part 2) — and local dev mirrors that shape exactly, so what you test here is what ships:

- The root `.htaccess` (the one alongside `app/`, `storage/`, `public_html/` — not `public_html/.htaccess`) transparently forwards every request into `public_html/`, so the URL never shows `/public_html/`. `http://localhost/dotttvfinance/public_html/...` still works directly too (handy for isolating whether a bug is in the forward or in the app), and both forms render identical pages with correctly-prefixed links either way.
- `app/`, `storage/`, `vendor/`, `.env`, `composer.json`/`.lock`, and `schema.sql` are blocked by that same root `.htaccess` — a scoped `Require all denied` for `.env`/`.sql`/`.md` files, plus explicit `mod_rewrite` `[F]` (Forbidden) rules for the `app/`, `storage/`, `vendor/` directories, evaluated *before* the catch-all forward. (A single blanket `Require all denied` for the whole directory doesn't work here — Apache evaluates access control before it runs per-directory rewrite rules, so it would 403 the legitimate forwarded traffic too, before the forward ever got a chance to run. Tested and confirmed locally.)
- The app is base-path aware: `app/config/config.php` computes a `BASE_PATH` constant per request (reconciling `SCRIPT_NAME`, the physical script path, against `REQUEST_URI`, what the browser actually asked for — they disagree once the forward is in play), `Router::dispatch()` strips it before matching routes, and every internal link/asset in views goes through the `url()` helper. Never a hardcoded leading-slash path.
- **Before trusting this in any environment**, confirm these three return `403`/`404` — and check the response body, not just the status — never actual file content: `/dotttvfinance/app/config/config.php`, `/dotttvfinance/.env`, `/dotttvfinance/storage/uploads/`. Test locally, then re-test on the live cPanel URL after every deploy — shared hosting can behave differently.

If you'd rather have the app live at a dedicated subdomain root exactly like the old approach, a vhost pointing `DocumentRoot` at `public_html/` plus a `/etc/hosts` entry is the way — ask and it can be set up, since that touches system config outside this repo. Note that's no longer what production does, though, so it'd diverge from the real deploy shape.

**Alternative for quick one-off checks:** PHP's built-in server, pointed at `public_html/` as the docroot (this serves from `/`, not the subfolder, so it's not representative of the real XAMPP setup above — just a fast sanity check):
```bash
php -S localhost:8099 -t public_html public_html/index.php
```

## Database

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS dotttv_finance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root dotttv_finance < schema.sql
```

Copy `.env.example` to `.env` and adjust `DB_*` / `SMTP_*` values — `.env` is gitignored and never committed.

**Local permissions gotcha**: Tech Spec §18 calls for `.env` locked down to `600` (owner-only) in production, which works there because on cPanel the web server process runs *as* the account owner. Locally, XAMPP's Apache workers run as a separate `daemon` OS user, not whoever owns the file — a `600` `.env` is silently unreadable by Apache (`Dotenv::safeLoad()` fails quietly, every `env()` call falls back to its default, and nothing obviously errors — it just behaves like `.env` doesn't exist). Use `644` locally instead; keep `600` in the production setup steps.

Same underlying mismatch hits `storage/` — anything the app writes at runtime (rate-limit counters, error logs, later: uploaded receipts) is written by the `daemon` user, but the folders are owned by whoever created them locally (you), mode `755` by default, which gives `daemon` no write access. Confirmed by testing: `RateLimiter` was silently failing to persist anything under real Apache — no error, no exception, just nothing written. Fixed locally with:
```bash
find storage -type d -exec chmod 777 {} \;
find storage -type f -exec chmod 666 {} \;
```
This is a local-XAMPP-only accommodation — don't carry `777`/`666` into the production setup steps (Deployment Handbook Part 2 step 6). On cPanel the web server runs as the account owner, so normal ownership already covers this; there the folders just need to be writable by that one user, no world-write needed.

**Local dev test user** (not seeded by `schema.sql` — inserted manually for testing auth, never commit real credentials): `dev@dotttv.tv` / `DevPassword123!` (super_admin). Re-create it after a fresh `schema.sql` import with:
```bash
HASH=$(php -r "echo password_hash('DevPassword123!', PASSWORD_BCRYPT, ['cost' => 12]);")
mysql -u root dotttv_finance -e "
INSERT INTO users (role_id, department_id, name, email, password_hash, status)
SELECT id, NULL, 'Dev Super Admin', 'dev@dotttv.tv', '${HASH}', 'active'
FROM roles WHERE name = 'super_admin'
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), status = 'active';
"
```

## Tailwind

Standalone CLI binary lives at `build/tailwind/tailwindcss` (gitignored, per-platform — re-download for other machines/architectures from the [Tailwind releases page](https://github.com/tailwindlabs/tailwindcss/releases)). Design tokens live in `build/tailwind/app.source.css`, pulled from `doc/design-system-dotttv.md`.

```bash
# one-off build
./build/tailwind/tailwindcss -i build/tailwind/app.source.css -o public_html/assets/css/app.css --minify

# watch during development
./build/tailwind/tailwindcss -i build/tailwind/app.source.css -o public_html/assets/css/app.css --watch
```

## Autoloading

No Composer classmap/PSR-4 for app code — `app/config/config.php` registers a small `spl_autoload_register` that resolves a class name to `app/core/{Class}.php`, `app/controllers/{Class}.php`, or `app/models/{Class}.php` by filename. No namespaces, no `composer dump-autoload` step needed when adding a new controller/model. Composer is only used for the vendored dependencies in `vendor/`.
