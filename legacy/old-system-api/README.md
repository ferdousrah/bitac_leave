# Old-system leave totals API

After cutover, `leave-totals.php` runs **inside the old BITAC software** (`bitac.technocratsbd.com`). The new system's **অ্যাডমিন প্যানেল → পুরনো সিস্টেম সিঙ্ক** button pulls it. The pulled totals let leave that was approved or adjusted in the old system keep counting toward balances in the new one.

## Install on the old system

1. Copy `leave-totals.php` into the old project at `legacy-api/leave-totals.php`. It must sit next to the old `connection.php`'s folder, so `../connection.php` resolves.
2. Set the `LEGACY_API_TOKEN` environment variable on the old container to a long random string. Until it is set, the API answers 503.
3. Keep the URL off the public internet. Put both containers on a shared Docker/Coolify network and call the old one by its service name.

## Configure the new system

Set these environment variables on the new app container (see `.env.example`):

```
LEGACY_API_URL=http://<old-service>/legacy-api/leave-totals.php
LEGACY_API_TOKEN=<same token as the old system>
```

Then run `migrations/add_legacy_leave_totals.php` once and press **এখন সিঙ্ক করুন**.

## What it returns

`GET leave-totals.php` with header `X-Api-Token`. Add `?employee_id=N` to inspect one person; the sync button refuses that form.

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
