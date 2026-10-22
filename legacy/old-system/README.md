# Old BITAC software (after cutover)

The old system (`bitac.technocratsbd.com`) keeps running beside the new leave system. Both live in this repository, so **one push deploys both**.

- Admins log in to the old system to adjust leave from before the cutover.
- The new system's **অ্যাডমিন প্যানেল → পুরনো সিস্টেম সিঙ্ক** button reads `app/legacy-api/leave-totals.php` over the internal Docker network.

```
legacy/old-system/
  app/                      the old PHP code — the web root of the container
    legacy-api/leave-totals.php   read-only API for the new system
  Dockerfile                php:8.5.12-apache + composer install
  docker-compose.yml        app + MySQL 8.4 LTS
```

## Where the old code came from

`app/` is the VPS snapshot of 21 Oct 2026. On the VPS that is branch `2008231` of `github.com/ferdousrah/bitac-leave-management`.

Three changes were made to it:

- **Credentials moved out of the code.** `connection.php` and `function.php` (twice) now read `DB_HOST`, `DB_USER`, `DB_PASSWORD` and `DB_NAME` from the environment. The old password is no longer in the source.
- **Added `legacy-api/`** for the new system.
- **Left out:**
  - `vendor/`, which composer installs during the build
  - `uploads/` and `pdfs/`, which are volumes
  - the broken pre-PHP-8 `mpdf/` copy
  - `phpmyadmin/`, `.git`, `*.bak`, `*.zip`, logs
  - an `api/` folder from an unrelated project that held another database's credentials

## Coolify: two resources, one repository

Both resources point at `github.com/ferdousrah/bitac_leave`, branch `main`, through the GitHub App, with **Deploy on push** enabled. Watch Paths keep a push from rebuilding the system it didn't touch.

| | New leave system | Old system |
|---|---|---|
| Build pack | Docker Compose | Docker Compose |
| Base directory | `/` | `/legacy/old-system` |
| Compose file | `docker-compose.yml` | `docker-compose.yml` |
| Watch Paths | `**`<br>`!legacy/old-system/**` | `legacy/old-system/**` |
| Domain | the new domain | `bitac.technocratsbd.com` (`app` service) |

Turn on **Connect to Predefined Network** on **both** resources so that the new system can reach the old one.

A push that changes both sides deploys both. An example is a change to `leave-totals.php` together with `api/legacy/sync.php`.

> Check with the first push. A change only under `legacy/old-system/` should deploy only the old system; a change anywhere else should deploy only the new one. If both deploy every time, the Watch Paths are being read relative to the base directory. In that case set the old system's Watch Paths to `**`.

### Environment

The old resource needs:
```
DB_PASSWORD=<strong>
DB_ROOT_PASSWORD=<strong>
LEGACY_API_TOKEN=<long random string>
```

The new resource needs:
```
LEGACY_API_URL=http://<old app container name>/legacy-api/leave-totals.php
LEGACY_API_TOKEN=<same value>
```

Coolify shows the old app's full container name (`app-<uuid>`). It stays the same across deploys.

## One-time data load (at cutover)

1. **Database.** Take a fresh dump on the VPS, not the one from 21 Oct:
   ```
   mysqldump --single-transaction --routines bitac_db | gzip > bitac_db.sql.gz
   gunzip < bitac_db.sql.gz | docker exec -i <old db container> mysql -uroot -p"$DB_ROOT_PASSWORD" bitac_db
   ```
   Run `SELECT @@GLOBAL.sql_mode;` on the VPS. If it differs from MySQL's default, set the same mode in `docker-compose.yml`. Old code written against a lax mode can fail on inserts under strict mode.
2. **Uploads.** These are about 330 MB and are not in git. Copy the VPS's `uploads/` into the `old_uploads` volume:
   ```
   docker cp ./uploads/. <old app container>:/var/www/html/uploads/
   docker exec <old app container> chown -R www-data:www-data /var/www/html/uploads
   ```
3. Check that the new container can reach the old API:
   ```
   curl -s -H "X-Api-Token: $LEGACY_API_TOKEN" "$LEGACY_API_URL?employee_id=872"
   ```
   Then press **এখন সিঙ্ক করুন** on the new system.

## The API

`GET legacy-api/leave-totals.php` with the header `X-Api-Token`. Add `?employee_id=N` to inspect one person; the sync button refuses that form. Until `LEGACY_API_TOKEN` is set, every call gets 503.

```json
{"ok": true, "generated_at": "...", "latest_change": "2026-10-18",
 "rows": [{"employee_id": 872, "bucket": 1, "year": 0, "source": "prev", "days": 55}, ...]}
```

Each row reports days from one `source`:

| `source` | Taken from |
|---|---|
| `prev` | `previous_leave_deduction` |
| `application` | approved `leave_applications` |
| `deduction` | `leave_deduction_history` |

Rows use the same filters as the old dashboard. `bucket` is the old `leaveTypeInTwo`, which is the same code as the deduction `leaveID`:

| `bucket` | Leave type |
|---|---|
| 1 | পূর্ণ গড় |
| 2 | অর্ধ-গড় (actual days) |
| 3 | নৈমিত্তিক |
| 4 | বিনা বেতনে |
| 5 | ঐচ্ছিক |
| 6 | অ-কর্তনযোগ্য |
| 10 | অসাধারণ |

`year` is the calendar year for buckets 3 and 5, and 0 for every other bucket.

## Keep it patched

The PHP image tag is pinned on purpose. On every PHP security release, bump `FROM php:8.5.x-apache` here **and** in the root `Dockerfile`. Both systems then redeploy from that one push.
