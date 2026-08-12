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

1. **Create the subdomain**: cPanel → Domains → Create a subdomain (e.g. `finance.dotttv.tv`), document root set to a path *outside* the eventual git-tracked app root — see the directory layout note below.
2. **Create the database**: cPanel → MySQL Databases → create a new database and a new database user with full privileges on that database only (not a shared/global user if your plan allows creating scoped ones).
3. **Run the schema**: cPanel → phpMyAdmin → select the new database → Import → upload `schema.sql`. Confirm all 17 tables, the `fund_balances` view, and the seed data landed (spot-check `SELECT * FROM roles;` returns the 4 seeded roles).
4. **Directory layout on the server** — this matters more than it looks like it does:
   ```
   /home/yourcpanelusername/
   ├── finance-app/              ← git repo lives here, OUTSIDE public web root
   │   ├── app/
   │   ├── storage/
   │   ├── vendor/
   │   ├── .env                  ← created manually here, once, never overwritten by deploy
   │   └── ...
   └── finance.dotttv.tv/        ← the subdomain's actual document root
       ├── index.php             ← this is public_html/'s contents, deployed FROM finance-app/public_html/
       ├── assets/
       └── ...
   ```
   In other words: the git repo's `public_html/` folder is not itself the subdomain's document root — its *contents* get deployed into the document root. This keeps `app/`, `storage/`, `vendor/`, and `.env` structurally unreachable by any URL, regardless of `.htaccess` misconfiguration, which is the actual security property Tech Spec §4 is designed around.
5. **Create `.env` on the server manually**, once, with real production credentials (DB, SMTP, app secret). This file is never part of any deploy — both methods below explicitly exclude it.
6. **Create the storage folders** (`storage/uploads/receipts`, `storage/uploads/vouchers`, `storage/logs`) with write permissions for the web server user (typically the cPanel account's own user — verify with your host if uploads fail with permission errors later).

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
            ./ ${{ secrets.SSH_USER }}@${{ secrets.SSH_HOST }}:~/finance-app/

      - name: Sync public assets to document root
        env:
          SSH_PRIVATE_KEY: ${{ secrets.SSH_PRIVATE_KEY }}
        run: |
          ssh -i ~/.ssh/deploy_key -p ${{ secrets.SSH_PORT }} \
            ${{ secrets.SSH_USER }}@${{ secrets.SSH_HOST }} \
            "rsync -avz --delete ~/finance-app/public_html/ ~/finance.dotttv.tv/"
```

**Why Composer and Tailwind run in CI, not on the server**: this avoids depending on the shared host having Composer or Node available at all — the built `vendor/` and compiled CSS get shipped as part of the deploy artifact. This is the more robust choice for budget cPanel hosting where you can't always be sure what's installed.

**Why `--exclude='.env'` and the storage excludes matter**: without them, `rsync --delete` would wipe your production `.env` and any receipts/vouchers/logs that only exist on the server, replacing them with nothing (since those paths are gitignored and don't exist in the CI checkout). This is the single most common way to accidentally destroy production data during a "simple" deploy — double-check these excludes are in place before your first real deploy.

### 3.5 First deploy and verification

- Push to `main`, watch the Actions tab for the workflow run.
- On success, visit `finance.dotttv.tv` and confirm the login page loads styled correctly.
- SSH in manually once and confirm `~/finance-app/.env` still has your real values (untouched) and `~/finance-app/vendor/` exists.

---

## Part 4 — Method B: GitHub Actions + FTP/SFTP (if no SSH access)

### 4.1 Get FTP/SFTP credentials

cPanel → FTP Accounts → create a dedicated deploy account scoped to the subdomain's directory if your host allows scoping (safer than using your main account credentials for automated deploys).

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

      - name: Deploy app/, storage-structure, vendor/ to non-web-root path
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          protocol: ftps
          local-dir: ./
          server-dir: /finance-app/
          exclude: |
            **/.git*
            **/.env
            **/storage/uploads/**
            **/storage/logs/**

      - name: Deploy public assets to document root
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          protocol: ftps
          local-dir: ./public_html/
          server-dir: /finance.dotttv.tv/
```

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

- **Audit log verification** (Tech Spec §11, §20): monthly, running `php ~/finance-app/scripts/verify_audit_log.php`, with output emailed to the Super Admin. Example cron expression: `0 6 1 * *` (6am on the 1st of each month).
- Session/rate-limit cleanup if you implemented file-based rate limiting rather than a DB table in Build Prompt 1.1 — a daily cleanup of stale attempt-tracking files.

---

## Part 7 — Post-Deploy Checklist (every deploy, not just the first)

- [ ] Login page loads and is styled correctly (a broken Tailwind build is the most common "silent" deploy failure — check this every time)
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
