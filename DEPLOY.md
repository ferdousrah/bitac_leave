# BITAC Leave — Coolify Deployment

Deploy the BITAC leave management system to a VPS via Coolify with Git auto-deploy.

## Architecture

```
┌─────────────────────────────────────────┐
│  Coolify (manages everything)           │
│  ┌─────────────────────────────────┐   │
│  │  App container (Apache + PHP)   │   │
│  │   ↕                              │   │
│  │  MariaDB container               │   │
│  │   ↕                              │   │
│  │  Persistent volumes:             │   │
│  │   - uploads/                     │   │
│  │   - sessions/                    │   │
│  │   - mysql data                   │   │
│  │   - app-assets/img/branding/     │   │
│  └─────────────────────────────────┘   │
└─────────────────────────────────────────┘
```

## Prerequisites

- VPS with Coolify installed (https://coolify.io/docs/installation)
- Domain or subdomain pointed to your VPS (e.g. `bitac.yourdomain.com`)
- GitHub/GitLab/Gitea repo with this codebase

---

## Step 1 — Push code to Git

```bash
cd /path/to/bitac_leave
git init
git add .
git commit -m "Initial deployment commit"
git remote add origin git@github.com:youruser/bitac-leave.git
git push -u origin main
```

The `.gitignore` already excludes `.env`, `vendor/`, `uploads/*`, sessions, and logs.

---

## Step 2 — Export current XAMPP database

On your local XAMPP machine:

```bash
mysqldump -u root bitac_leave_dev > db-init/00-schema-and-data.sql
```

Commit this file:

```bash
git add db-init/00-schema-and-data.sql
git commit -m "Add initial DB dump for first deploy"
git push
```

> **Note:** Coolify's MariaDB container auto-imports any `.sql` file from the `/docker-entrypoint-initdb.d/` directory on **first boot** (empty data dir). For subsequent deploys, the dump is ignored.

---

## Step 3 — Coolify setup

1. **Open Coolify dashboard** → New Resource → **Application**
2. **Source:** Public Repository or your private Git provider
3. **Repo URL:** `https://github.com/youruser/bitac-leave.git`
4. **Branch:** `main`
5. **Build pack:** select **Dockerfile**
6. **Port:** `80` (Apache default; Coolify proxies via Traefik)
7. **Domain:** `bitac.yourdomain.com` (Coolify will auto-provision Let's Encrypt SSL)

### Environment Variables (paste from `.env.example`):

| Key | Value |
|---|---|
| `DB_HOST` | `db` (or your Coolify service hostname) |
| `DB_PORT` | `3306` |
| `DB_USER` | `bitac` |
| `DB_PASSWORD` | (strong random) |
| `DB_NAME` | `bitac_leave` |
| `DB_ROOT_PASSWORD` | (strong random) |
| `APP_BASE_URL` | `https://bitac.yourdomain.com` |
| `APP_PROTOCOL` | `https` |

### Persistent Storage (Coolify → Storages tab):

| Mount | Source | Type |
|---|---|---|
| `/var/www/html/uploads` | named volume | Persistent |
| `/var/www/html/sessions` | named volume | Persistent |
| `/var/www/html/app-assets/img/branding` | named volume | Persistent |

### Database (separate Coolify resource):

1. New Resource → **Database** → **MariaDB 11**
2. Set the same `DB_*` env values used above
3. Enable: "Make this service public" — **NO** (only internal access)
4. Connect networks so the app can reach `db` host

> Tip: easiest is to create a `docker-compose.yml`-based deployment where both services come up together. Coolify's "Docker Compose" build pack supports the `docker-compose.yml` shipped in this repo directly.

---

## Step 4 — Auto-deploy on Git push

In Coolify → your app → **Webhooks**:

1. Copy the webhook URL
2. Go to your Git repo → Settings → Webhooks → Add webhook
3. Paste URL, select event: **push**
4. Done — every `git push origin main` will trigger a rebuild + deploy

---

## Step 5 — First-time post-deploy steps

After the first successful deploy:

1. Visit `https://bitac.yourdomain.com`
2. Log in with the existing super-admin credentials from your XAMPP DB dump
3. Run any pending migrations one by one in browser:
   - `https://bitac.yourdomain.com/migrations/add_theme_settings.php`
   - `https://bitac.yourdomain.com/migrations/add_application_no.php`
   - `https://bitac.yourdomain.com/migrations/add_segment_kind.php`
   - `https://bitac.yourdomain.com/migrations/add_leave_segments.php`
   - (Each is idempotent — safe to re-run)

---

## Local docker-compose testing (optional)

Before pushing to Coolify, test locally:

```bash
cp .env.example .env
# Edit .env — set passwords + APP_BASE_URL=http://localhost:8080
docker compose up -d --build
docker compose logs -f app
# Visit http://localhost:8080
```

Stop:

```bash
docker compose down              # keep volumes (data preserved)
docker compose down -v           # delete volumes too (fresh start)
```

---

## Troubleshooting

**Page renders but assets 404:** Check `APP_BASE_URL` env matches the domain you're visiting.

**Mixed-content / SSL warning:** Coolify's reverse proxy sets `X-Forwarded-Proto: https` — `paths.php` already handles this. If still wrong, force `APP_PROTOCOL=https` env var.

**Sessions reset on each deploy:** Sessions volume not persistent. Verify `/var/www/html/sessions` is mounted as a Coolify Persistent Storage.

**Uploads disappear on redeploy:** Same — `/var/www/html/uploads` must be a persistent volume.

**`mysqli_connect: Connection refused`:** App container started before DB was ready. The compose file has a healthcheck — Coolify should respect `depends_on: condition: service_healthy`. Restart the app once.

**500 error after first deploy:** Check `php_errors.log` inside the container:
```bash
docker exec -it <app-container> tail -50 /var/www/html/php_errors.log
```

---

## What's already prepared

- ✅ `Dockerfile` — PHP 8.2 + Apache + all extensions for mPDF
- ✅ `docker-compose.yml` — app + MariaDB + volumes
- ✅ `config/connection.php` — reads `DB_*` from environment
- ✅ `config/paths.php` — supports `APP_BASE_URL` env or auto-detect
- ✅ `composer.json` — installed during build
- ✅ `.gitignore` / `.dockerignore` — secrets and runtime data excluded
- ✅ `db-init/` — drop your `.sql` dump here, auto-imports on first boot
