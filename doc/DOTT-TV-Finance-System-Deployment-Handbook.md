# DOTT TV Finance & Accounting System — Deployment Handbook

**Version:** 1.0
**Owner:** Favikings (ICT Head / Technical Lead, DOTT TV)

---

## Before you start: check what your cPanel plan actually gives you

Everything below branches on one fact you need to confirm first: **does your cPanel hosting plan include SSH access?** Log into cPanel and check the "Advanced" section for an "SSH Access" icon. This determines which deployment method you use.

- **SSH available** → use **Method A** (Git + SSH). Faster, cleaner, supports running Composer on the server.
- **SSH not available** (common on budget shared plans) → use **Method B** (FTP/SFTP deploy via GitHub Actions). Works everywhere, slightly less elegant.

Both methods are documented in full below. Pick one — don't try to run both.

---

## Part 1 — GitHub Repository Setup

1. Create a **private** repository on GitHub (e.g. `dott-tv-finance-system`). Private is correct here — this codebase touches financial logic and, eventually, real transaction data structure; there's no reason for it to be public.
2. Initialize locally and push the structure from `DOTT-TV-Finance-System-Build-Prompts.md` Prompt 0.1 as your first commit.
3. Branching strategy — keep it simple for a solo builder:
   - `main` — always deployable, this is what gets pushed to production
   - `dev` (optional) — if you want a buffer branch to merge into `main` only when a feature is genuinely done. If working solo and shipping frequently, you may skip this and commit straight to `main` behind good manual testing discipline; your call.
4. `.gitignore` (already specified in Prompt 0.1, repeated here for reference):
   ```
   /vendor/
   .env
   /storage/logs/*
   /storage/uploads/*
   !/storage/logs/.gitkeep
   !/storage/uploads/.gitkeep
   ```
   Add empty `.gitkeep` files in `storage/logs/` and `storage/uploads/{receipts,vouchers}/` so the folder structure itself is tracked even though its contents aren't.
5. **Never commit `.env`.** Only `.env.example` with placeholder values goes into git. Real credentials exist in exactly two places: your local machine and the production server — never in GitHub, not even in a private repo, not even briefly in a commit you plan to remove later (removing a commit doesn't remove it from git history without a force-push and history rewrite, which is a bigger headache than just not committing it).

---

## Part 2 — First-Time Server Setup (do this once, manually, before any automated deploy)

**Deployment shape note**: this app is accessed as `dotttv.tv/dotttvfinance` — a subfolder of the main domain, not a dedicated subdomain. That changes this section from the original subdomain-based plan; everything else in this handbook (Methods A/B, secrets, migrations, cron, rollback) is unaffected.

1. **Create the target folder**: no subdomain creation needed. The entire project — `app/`, `storage/`, `vendor/`, `public_html/`, `.env`, and the root `.htaccess` — deploys as one unit into `public_html/dotttvfinance/` on the cPanel account's main domain.
2. **Create the database**: cPanel → MySQL Databases → create a new database and a new database user with full privileges on that database only (not a shared/global user if your plan allows creating scoped ones).
3. **Run the schema**: cPanel → phpMyAdmin → select the new database → Import → upload `schema.sql`. Confirm all 18 tables, the `fund_balances` view, and the seed data landed (spot-check `SELECT * FROM roles;` returns the 4 seeded roles).
4. **Directory layout on the server**:
   ```
   /home/yourcpanelusername/
   └── public_html/                       ← main domain's document root (dotttv.tv)
       ├── (your existing main site files)
       └── dotttvfinance/                 ← this project, deployed here as one unit
           ├── .htaccess                  ← root .htaccess: denies direct access, forwards into public_html/
           ├── app/                       ← protected by .htaccess, NOT physically outside the web root
           ├── storage/                   ← same — protected by .htaccess, not physical isolation
           ├── vendor/
           ├── .env                       ← created manually here, once, never overwritten by deploy
           └── public_html/               ← the actual front-controller + assets
               ├── index.php
               ├── assets/
               └── ...
   ```
   **This is a materially different security guarantee than a subdomain layout.** With a dedicated subdomain, `app/`, `storage/`, and `.env` sit physically outside any web-servable document root — no `.htaccess` rule, correct or not, changes whether they're reachable, because Apache never serves that directory at all. With this subfolder layout, those same folders are *inside* a web-servable tree, and what keeps them unreachable is the root `.htaccess`'s `Require all denied` rule plus the rewrite that forwards all other requests into `public_html/`. That's a **config-correctness guarantee, not a structural impossibility** — it depends on the `.htaccess` rule being present and working, not on physical placement. Standard practice for subfolder deployments, but worth being clear-eyed about the difference (see the verification step in step 7 below — don't skip it).
5. **Create `.env` on the server manually**, once, with real production credentials (DB, SMTP, app secret). This file is never part of any deploy — both methods below explicitly exclude it.
6. **Create the storage folders** (`storage/uploads/receipts`, `storage/uploads/vouchers`, `storage/logs`) with write permissions for the web server user (typically the cPanel account's own user — verify with your host if uploads fail with permission errors later).
7. **Verify the `.htaccess` protection actually works** before trusting it — confirm these three URLs return 403/404 and never actual file content:
   - `dotttv.tv/dotttvfinance/app/config/config.php`
   - `dotttv.tv/dotttvfinance/.env`
   - `dotttv.tv/dotttvfinance/storage/uploads/`
   Test locally against your XAMPP `htdocs` subfolder setup first, then re-test these same three URLs against the live cPanel URL after your first deploy — shared hosting Apache configs can behave differently from a local setup, so a local pass doesn't guarantee a production pass.

---

## Part 3 — Method A: GitHub Actions + SSH (if your plan has SSH access)

### 3.1 Generate a deploy key

On your local machine:
```bash
ssh-keygen -t ed25519 -f dott_finance_deploy_key -N ""
```
This produces `dott_finance_deploy_key` (private) and `dott_finance_deploy_key.pub` (public).

### 3.2 Authorize the public key on cPanel

cPanel → SSH Access → Manage SSH Keys → Import Key → paste the contents of `dott_finance_deploy_key.pub`. Authorize it.

### 3.3 Add secrets to GitHub

Repo → Settings → Secrets and variables → Actions → New repository secret. Add:
- `SSH_PRIVATE_KEY` — full contents of `dott_finance_deploy_key` (the private key)
- `SSH_HOST` — your server's hostname or IP
- `SSH_USER` — your cPanel username
- `SSH_PORT` — usually 22, but some hosts use a custom SSH port, check cPanel's SSH Access page for the exact value

### 3.4 GitHub Actions workflow

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to cPanel

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install Composer dependencies
        run: composer install --no-dev --optimize-autoloader --working-dir=.

      - name: Build Tailwind CSS
        run: |
          curl -sLo tailwindcss https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-linux-x64
          chmod +x tailwindcss
          ./tailwindcss -i ./resources/app.css -o ./public_html/assets/css/app.css --minify

      - name: Deploy via rsync over SSH
        env:
          SSH_PRIVATE_KEY: ${{ secrets.SSH_PRIVATE_KEY }}
        run: |
          mkdir -p ~/.ssh
          echo "$SSH_PRIVATE_KEY" > ~/.ssh/deploy_key
          chmod 600 ~/.ssh/deploy_key
          ssh-keyscan -p ${{ secrets.SSH_PORT }} ${{ secrets.SSH_HOST }} >> ~/.ssh/known_hosts
          rsync -avz --delete \
            --exclude='.env' \
            --exclude='.git' \
            --exclude='storage/uploads' \
            --exclude='storage/logs' \
            -e "ssh -i ~/.ssh/deploy_key -p ${{ secrets.SSH_PORT }}" \
            ./ ${{ secrets.SSH_USER }}@${{ secrets.SSH_HOST }}:~/public_html/dotttvfinance/
```

**Why Composer and Tailwind run in CI, not on the server**: this avoids depending on the shared host having Composer or Node available at all — the built `vendor/` and compiled CSS get shipped as part of the deploy artifact. This is the more robust choice for budget cPanel hosting where you can't always be sure what's installed.

**Why `--exclude='.env'` and the storage excludes matter**: without them, `rsync --delete` would wipe your production `.env` and any receipts/vouchers/logs that only exist on the server, replacing them with nothing (since those paths are gitignored and don't exist in the CI checkout). This is the single most common way to accidentally destroy production data during a "simple" deploy — double-check these excludes are in place before your first real deploy.

**One deploy step, not two**: with the subdomain layout this used to be a two-step deploy (app files to one directory, `public_html/` contents to a separate document root). With the subfolder layout, everything — including the project's own `public_html/` subfolder and its root `.htaccess` — lands in one place (`public_html/dotttvfinance/` on the server), since the `.htaccess` handles routing requests into the right spot rather than the directory structure doing it physically.

### 3.5 First deploy and verification

- Push to `main`, watch the Actions tab for the workflow run.
- On success, visit `dotttv.tv/dotttvfinance` and confirm the login page loads styled correctly (not `dotttv.tv/dotttvfinance/public_html/` — if the URL needs `/public_html/` to work, the root `.htaccess` rewrite isn't deployed or isn't working).
- Run the three protected-path checks from Part 2, step 7, against the live URL.
- SSH in manually once and confirm `~/public_html/dotttvfinance/.env` still has your real values (untouched) and `~/public_html/dotttvfinance/vendor/` exists.

---

## Part 4 — Method B: GitHub Actions + FTP/SFTP (if no SSH access)

### 4.1 Get FTP/SFTP credentials

cPanel → FTP Accounts → create a dedicated deploy account scoped to `public_html/dotttvfinance/` if your host allows scoping (safer than using your main account credentials for automated deploys).

### 4.2 Add secrets to GitHub

- `FTP_SERVER` — your server hostname
- `FTP_USERNAME`
- `FTP_PASSWORD`
- Use SFTP (port 22) over plain FTP if your host supports it — plain FTP sends credentials unencrypted.

### 4.3 GitHub Actions workflow

```yaml
name: Deploy to cPanel via FTP

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install Composer dependencies
        run: composer install --no-dev --optimize-autoloader --working-dir=.

      - name: Build Tailwind CSS
        run: |
          curl -sLo tailwindcss https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-linux-x64
          chmod +x tailwindcss
          ./tailwindcss -i ./resources/app.css -o ./public_html/assets/css/app.css --minify

      - name: Deploy the whole project to the dotttvfinance subfolder
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          protocol: ftps
          local-dir: ./
          server-dir: /public_html/dotttvfinance/
          exclude: |
            **/.git*
            **/.env
            **/storage/uploads/**
            **/storage/logs/**
```

**One deploy step, not two**: with the subfolder layout, everything — the root `.htaccess`, `app/`, `storage/`, `vendor/`, and the project's own `public_html/` subfolder — lands together in `public_html/dotttvfinance/` on the server. The root `.htaccess`'s rewrite rule handles routing requests into the right spot, so there's no separate "sync public assets to document root" step like a subdomain layout needs.

Same exclusion logic and reasoning as Method A applies here — never let an automated deploy touch `.env`, uploaded receipts, or logs.

---

## Part 5 — Database Migrations Going Forward

`schema.sql` is your Phase 0 baseline — it doesn't run again after initial setup. For every schema change from here on:

1. Create a new file: `migrations/0002_description_of_change.sql` (sequential numbering, starting after `schema.sql` as `0001`).
2. Write only the incremental change (e.g. `ALTER TABLE expenses ADD COLUMN ...`), never a full re-dump of the schema.
3. Apply it to production manually via phpMyAdmin, or via SSH + `mysql` CLI if you have Method A's SSH access — **never** as part of the automated GitHub Actions deploy. Schema changes touching financial tables deserve a human actually watching them run against production, not a fire-and-forget CI step, given what's at stake if something goes wrong.
4. Keep a `migrations/applied.log` (plain text, one line per file run + date) in the repo so you always know what's actually been applied to production versus what's sitting unapplied in the repo.

---

## Part 6 — Cron Jobs

cPanel → Cron Jobs. Set up:

- **Audit log verification** (Tech Spec §11, §20): monthly, running `php ~/public_html/dotttvfinance/scripts/verify_audit_log.php`, self-mailing its result via the app's own PHPMailer/Zoho SMTP setup rather than relying on cPanel's default cron-output mailing (see note below). Example cron expression: `0 6 1 * *` (6am on the 1st of each month).
- Session/rate-limit cleanup if you implemented file-based rate limiting rather than a DB table in Build Prompt 1.1 — a daily cleanup of stale attempt-tracking files.

**Why self-mail instead of relying on cPanel's cron-output email**: by default, cPanel emails a cron job's stdout to the cPanel account's Contact Information address — which may not be the same inbox as the Super Admin's actual login email in the `users` table. If they differ, the monthly report silently goes to the wrong place with no error to alert you. `verify_audit_log.php` should look up the Super Admin's email from the `users` table itself and send via the same PHPMailer/Zoho setup Build Prompt 2.6 already built for notifications — one mail-sending path for the whole app, not two.

**How to actually test this** (cron jobs are easy to configure wrong in ways that only surface a month later):

1. **Test the script itself first, independent of cron**, via SSH:
   ```bash
   cd ~/public_html/dotttvfinance
   php scripts/verify_audit_log.php
   ```
   Confirm it completes without PHP errors and the email actually arrives at the Super Admin's real address. Fix any script issues here before touching cron scheduling at all.

2. **Set a temporary near-future cron schedule** instead of waiting for the real monthly run — e.g. if it's currently 14:32, use `34 14 * * *` to fire in ~2 minutes. Confirm the job actually executed (cPanel's Cron Jobs page or its logs) and the email arrived.

3. **Switch the schedule back to `0 6 1 * *`** once confirmed working — don't leave a minute-level test schedule running in production.

4. **Re-test the tamper-detection itself against the production database** — manually edit one `audit_log` row via phpMyAdmin, run the script, confirm it's flagged. This was already covered in Prompt 2.5's acceptance criteria against dev/local data; worth repeating once against the real production database at least once, since that's the environment it actually needs to protect.

---

## Part 7 — Post-Deploy Checklist (every deploy, not just the first)

- [ ] Login page loads and is styled correctly (a broken Tailwind build is the most common "silent" deploy failure — check this every time)
- [ ] `.htaccess` protection still holds: `dotttv.tv/dotttvfinance/.env`, `/app/config/config.php`, and `/storage/uploads/` all return 403/404, never file content — a deploy that overwrites or drops the root `.htaccess` silently reopens this, so don't skip it just because it passed last time
- [ ] Service worker cache version was bumped if any static asset changed (Tech Spec §17) — check `sw.js`'s cache name constant was updated, or returning users will keep serving stale cached assets
- [ ] `.env` on the server is untouched (spot-check via SSH if using Method A, or simply confirm the app still connects to the DB correctly, which it can't do with a wiped `.env`)
- [ ] `storage/uploads/` still contains previously-uploaded receipts (confirm the exclude rules actually worked, don't just trust the config)
- [ ] Submit one real test action (e.g. a small test expense) end-to-end to confirm the whole request path — router, DB, audit log — survived the deploy intact

---

## Part 8 — Rollback

Simplest approach for a solo builder, no fancy release-symlink infrastructure needed at this scale:

1. `git revert` the problematic commit(s) on `main` (or `git reset --hard` to the last known-good commit if you're comfortable force-pushing on a solo project — riskier on a team repo, fine here).
2. Push — the same GitHub Actions workflow redeploys the reverted state automatically.
3. If the problem was a database migration, you generally can't "revert" a schema change as cleanly as code — write a corresponding down-migration (`0003_rollback_0002.sql`) rather than trying to reverse-engineer the change live in phpMyAdmin under pressure.

If you outgrow this simple approach later (e.g. once non-technical staff depend on zero-downtime deploys), a timestamped-releases-plus-symlink pattern is the standard next step — not needed for DOTT TV's current scale, mentioned here only so you know the option exists.

---

## Part 9 — Secrets Summary (what lives where)

| Secret | Lives in |
|---|---|
| Production DB credentials | Server `.env` only |
| SMTP credentials | Server `.env` only |
| App secret key | Server `.env` only |
| SSH private key / FTP password | GitHub Actions Secrets only |
| Anything else sensitive | Neither git history nor Slack/email — if you're unsure where a credential belongs, it belongs in `.env`, not in a commit, a comment, or a chat message |