# Migration plan: Moodle from Bitnami to Oksigenia (new VM)

*Status: 2026-10-23 · Scope: `moodle.ict-bz.ch` (currently edu11, `bitnami/moodle` + `bitnami/mariadb`)*

## Goal and approach

- Replace the frozen Bitnami images with `oksigenia/moodle:5.3` (Moodle 5.3 LTS, PHP 8.4, contains the php2shell fix) and the official `mariadb` image.
- **The current VM (edu11) is not changed.** Exams are running on it today. Only read-only commands are run there (version checks, DB dump, file copy), and only after the exams have finished.
- A **new VM** is set up. A copy of the production data is moved to it and upgraded there. This test run happens **today**.
- The definitive move happens on a **weekend**: final copy of the data, upgrade, switch the nginx upstream on edu10 to the new VM. edu11 stays untouched as the rollback.

```
            today (test)                            weekend (cutover)
edu11 ──dump + rsync──▶ new VM (moodletest)    edu11 ──final dump + rsync──▶ new VM (moodle)
(prod, unchanged)        test & fix                 (maintenance mode)        nginx upstream → new VM
```

## Target setup

| | Today (edu11) | New VM |
|---|---|---|
| Moodle image | `bitnami/moodle:latest` (≤ 5.0.2, frozen) | `oksigenia/moodle:5.3.0-r19` (pinned, check for a newer `-rN` before the cutover) |
| Moodle version | 5.0.x (to be confirmed, step 1) | 5.3 LTS, security fixes until Oct 2029 |
| DB image | `bitnami/mariadb:latest` | `mariadb:11.8` (LTS, ≥ 11.4 required by Moodle 5.3) |
| DB data path | `./data/mariadb` → `/bitnami/mariadb` (uid 1001) | `./data/mariadb` → `/var/lib/mysql` (uid 999), filled from a **dump**, not copied |
| Code / moodledata | `/bitnami/moodle`, `/bitnami/moodledata` | same paths, the image accepts the Bitnami paths and variables |
| Upload limit | `.htaccess` copied by `copy_htaccess.sh` | `PHP_UPLOAD_MAX_FILESIZE` / `PHP_POST_MAX_SIZE` env vars |
| Cron | inside the Bitnami container | inside the container (`MOODLE_CRON=on`, every 60 s) |
| Ports | `80:8080`, `443:8443` | `80:8080` only (nginx on edu10 talks HTTP) |

Why a dump instead of copying the MariaDB data directory: the Bitnami and official images use different paths, uids and possibly different MariaDB versions (Bitnami `latest` may be 11.x or 12.0, depending on when it was last pulled). A logical dump of the `bitnami_moodle` database works regardless of the source version.

---

## Phase 0: Preparation (today, during the exams, no impact on edu11)

### 0.1 Set up the new VM

- [ ] Create the VM (same resources as edu11 or more; disk ≥ 2× the size of `data/` on edu11, because the image keeps pre-upgrade backups in moodledata).
- [ ] Install Docker + Compose plugin, clone this repository.
- [ ] Note the IP: `10.10.200.___` (referred to as `<NEW_IP>` below).
- [ ] SSH from the new VM to edu11 as root works (needed for `rsync`).
- [ ] Firewall: port 80 on the new VM reachable **only from edu10** (see `php2shell-assessment.md`, step 6).

### 0.2 Prepare the configuration on the new VM

Do **not** commit these changes to `master` yet: if someone runs `git pull` on edu11, the production compose file would change. Use a branch, e.g. `moodle-oksigenia`, and merge it after the cutover.

`moodle/docker-compose.yml`:

```yaml
services:
  moodle_mariadb:
    image: docker.io/library/mariadb:11.8
    command: --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci
    env_file:
      - .env
    volumes:
      - ./data/mariadb:/var/lib/mysql # <-- All database files. DO NOT DELETE THIS DIRECTORY
    restart: always
  moodle:
    image: docker.io/oksigenia/moodle:5.3.0-r19
    ports:
      - '80:8080'
    env_file:
      - .env
    volumes:
      - ./data/moodle_installation:/bitnami/moodle # <-- Moodle code + config.php
      - ./data/moodle_userdata:/bitnami/moodledata # <-- All user data. DO NOT DELETE THIS DIRECTORY
      - ./config.d:/etc/moodle/config.d:ro # <-- Extra PHP config, loaded alphabetically
    depends_on:
      - moodle_mariadb
    restart: always
```

`moodle/.env`: copy the **production** `.env` from edu11 (same DB user, password and DB name, so the existing `config.php` keeps working), then add:

```ini
MOODLE_URL=https://moodletest.ict-bz.ch   # test; for the cutover: https://moodle.ict-bz.ch
MOODLE_SSL_PROXY=auto
PHP_UPLOAD_MAX_FILESIZE=512M
PHP_POST_MAX_SIZE=512M
PHP_MEMORY_LIMIT=512M
PHP_MAX_INPUT_VARS=5000
PHP_DATE_TIMEZONE=Europe/Zurich
```

`MARIADB_CHARACTER_SET` / `MARIADB_COLLATE` are Bitnami-only and ignored by the official image (replaced by `command:` above). They can stay in the file.

`moodle/config.d/90-test-no-email.php`: **test VM only**, so the copy doesn't send forum posts, notifications or reminders to real users:

```php
<?php
$CFG->noemailever = true;
```

### 0.3 Test vhost on edu10

New file `nginx/nginx/conf/moodletest.conf`, a copy of `moodle.conf` with:

- `server_name moodletest.ict-bz.ch;` and matching certificate paths
- `include /etc/nginx/conf.d/snippets/internal-only.inc;` in the 443 server block (the test instance must not be public; check the include path used in `owncloud.conf`)
- an upstream `moodletest_server` pointing to `<NEW_IP>:80`

Then: DNS record `moodletest.ict-bz.ch`, request the certificate with certbot, reload nginx. Reloading nginx does not interrupt running connections to `moodle.ict-bz.ch`.

---

## Phase 1: Read-only checks on edu11 (today, **after the exams have finished**)

All commands in `<repo>/moodle` on edu11. None of them change anything.

```bash
docker compose images                                    # which image IDs are actually running
docker compose exec moodle php -v
docker compose exec moodle grep -E "^\\\$release|^\\\$branch" /bitnami/moodle/version.php
docker compose exec moodle_mariadb mariadb --version
docker compose exec moodle grep -nE "wwwroot|sslproxy|reverseproxy|dbhost|dbtype|prefix" /bitnami/moodle/config.php
du -sh data/*
```

Record the results:

| Check | Result |
|---|---|
| Moodle version (`$release`) | |
| PHP version | |
| MariaDB version | |
| `wwwroot` / `sslproxy` in `config.php` | |
| Size of `data/moodle_userdata`, `data/moodle_installation`, `data/mariadb` | |

In the Moodle admin UI (read-only), note:

- [ ] **Site admin → Plugins → Plugins overview → "Additional plugins"**: list every add-on and check on moodle.org/plugins whether a release for 5.3 exists.
- [ ] Are **Chat** or **Survey** activities in use? Is the **Classic** theme in use (site or course level)? Both were removed in Moodle 5.0 / 5.3, and the image **refuses to upgrade** while they exist (see Phase 3).
- [ ] Is the **TeX notation** filter enabled? Moodle 5.3 drops mimetex.
- [ ] External integrations that a copy could trigger (enrolment sync to an external DB, webhooks, Teams/Zoom, plagiarism checker, etc.). Disable them on the test copy in Phase 3.

## Phase 2: Copy the data to the new VM (today, after the exams)

Run on the **new VM** in `<repo>/moodle`. The copies are throttled (`nice`/`ionice`, `--bwlimit`) so edu11 stays responsive. **Time each step.** The durations tell you how long the weekend window must be.

```bash
# 2.1 Database dump (InnoDB, --single-transaction does not lock the tables)
ssh root@edu11 'cd <repo>/moodle && docker compose exec -T moodle_mariadb sh -c \
  "nice -n19 mariadb-dump -uroot -p\"\$MARIADB_ROOT_PASSWORD\" --single-transaction --quick --routines --default-character-set=utf8mb4 bitnami_moodle"' \
  | gzip > ~/moodle-test-$(date +%F).sql.gz

# 2.2 Code + config.php
sudo rsync -aHAX --numeric-ids --info=progress2 \
  --rsync-path="ionice -c3 nice -n19 rsync" --bwlimit=50M \
  root@edu11:<repo>/moodle/data/moodle_installation/ ./data/moodle_installation/

# 2.3 moodledata, without caches (Moodle rebuilds them)
sudo rsync -aHAX --numeric-ids --info=progress2 \
  --rsync-path="ionice -c3 nice -n19 rsync" --bwlimit=50M \
  --exclude=/cache/ --exclude=/localcache/ --exclude=/sessions/ --exclude=/temp/ --exclude=/lock/ \
  root@edu11:<repo>/moodle/data/moodle_userdata/ ./data/moodle_userdata/
```

The dump and the file copy are taken at slightly different times. That's acceptable for a test. The cutover in Phase 5 is done in maintenance mode, so it is consistent.

| Step | Duration |
|---|---|
| 2.1 DB dump | |
| 2.2 code | |
| 2.3 moodledata (full) | |

## Phase 3: Upgrade the test copy (today)

```bash
# 3.1 Start an empty MariaDB (creates DB + user from .env), then restore the dump
docker compose up -d moodle_mariadb
docker compose logs -f moodle_mariadb          # wait for "ready for connections"
gunzip -c ~/moodle-test-*.sql.gz | docker compose exec -T moodle_mariadb sh -c \
  'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'

# 3.2 Start Moodle: on the first start the image backs up the code + DB,
#     replaces the core code, keeps the add-ons, upgrades the DB to 5.3
docker compose up -d moodle
docker compose logs -f moodle                  # ends with "Moodle is ready"
```

What happens on the first start:

- `config.php` is kept; the original is saved to `data/moodle_installation/.oksigenia/config.php.pre-oksigenia`.
- Pre-upgrade backup: `data/moodle_userdata/oksigenia/backups/<date>/` (`database.sql.gz`, `code.tar.gz`).
- Add-ons are moved into the new `public/` layout (Moodle ≥ 5.1).
- **The site stays in maintenance mode after the upgrade.**

If the upgrade refuses to start:

| Message about | Fix |
|---|---|
| Chat / Survey activities | Install `moodlehq/moodle-mod_chat` / `moodlehq/moodle-mod_survey` as add-ons, or set `MOODLE_ALLOW_REMOVED_PLUGINS=yes` (**this deletes those activities**; decide with the teachers). |
| Classic theme | Switch site/courses to Boost, or `MOODLE_ALLOW_REMOVED_PLUGINS=yes`. |
| Add-on without 5.3 support | Update or remove the add-on in `data/moodle_installation`. If that's not possible, fall back to `oksigenia/moodle:5.2.4-r18` (supported until Oct 2027). |

After a fix: restore the dump again (step 3.1 with a fresh `data/mariadb`) or use `MOODLE_RETRY_UPGRADE=yes`, then restart.

```bash
# 3.3 Open the test site
docker compose exec moodle moodle-cli maintenance --disable
```

Then disable the external integrations noted in Phase 1 (Site admin, test copy only).

## Phase 4: Test checklist (today)

Open `https://moodletest.ict-bz.ch` from the internal network.

- [ ] `php -v` in the container shows **≥ 8.4.27** (php2shell fix)
- [ ] Site admin → **Notifications**: no pending upgrades, no errors
- [ ] Site admin → Server → **Environment**: all checks OK (PHP, MariaDB ≥ 11.4, `max_input_vars`)
- [ ] Site admin → **Plugins overview**: no missing or broken add-ons
- [ ] `wwwroot` is `https://moodletest.ict-bz.ch`, no redirect loops, no mixed-content warnings (= `MOODLE_URL`/`sslproxy` override `config.php` correctly; if not, set them in a `config.d` file)
- [ ] **LDAP login** with a teacher and a learner account; local admin login
- [ ] Testers **do not change passwords** on the test copy (LDAP password changes would reach the real directory)
- [ ] Open several courses, files, activities; question banks (moved into activities by 5.x; the first cron runs are slow)
- [ ] **Quiz**: start and submit an attempt, view results
- [ ] **Upload** a file > 100 MB (limit 512 MB) and do a **course backup + restore** of a large course
- [ ] Cron: `data/moodle_userdata/oksigenia/cron-last.log` updated every minute, no errors; Site admin → Server → Tasks → Scheduled tasks: no failing tasks after ~30 min
- [ ] **No emails** sent (`noemailever` active, e.g. "Send test email" in Site admin → Server → Email → Outgoing mail configuration fails or is suppressed)
- [ ] Theme / branding looks right; TeX formulas render (if used)

Record problems and their fixes here; every fix must be repeated at the cutover.

| Problem | Fix |
|---|---|
| | |

**Go/no-go for the weekend:** all checks green, durations from Phase 2 known.

---

## Phase 5: Cutover (weekend)

Announce the maintenance window to teachers and learners beforehand.

### 5.1 Prepare the new VM

```bash
docker compose down
sudo rm -rf data/mariadb                        # test DB, will be restored from the final dump
rm config.d/90-test-no-email.php                # production must send emails
sed -i 's|^MOODLE_URL=.*|MOODLE_URL=https://moodle.ict-bz.ch|' .env
```

Check whether a newer `5.3.0-rN` tag exists and update `image:` if so (re-test briefly).

`data/moodle_installation` must be reset to the **unmodified Bitnami state** before the final copy, because the test run already replaced the core code. The `rsync --delete` in 5.3 does that.

### 5.2 Close the old site (edu11)

```bash
docker compose exec moodle php /opt/bitnami/moodle/admin/cli/maintenance.php --enable
```

From now on nobody can write to edu11. **This is the last point where the old site is the live one.**

### 5.3 Final copy (new VM)

```bash
# DB dump (same command as 2.1, without nice — site is closed)
ssh root@edu11 'cd <repo>/moodle && docker compose exec -T moodle_mariadb sh -c \
  "mariadb-dump -uroot -p\"\$MARIADB_ROOT_PASSWORD\" --single-transaction --quick --routines --default-character-set=utf8mb4 bitnami_moodle"' \
  | gzip > ~/moodle-final-$(date +%F).sql.gz

# Code: exact copy of edu11, removes the test upgrade
sudo rsync -aHAX --numeric-ids --delete --info=progress2 \
  root@edu11:<repo>/moodle/data/moodle_installation/ ./data/moodle_installation/

# moodledata: only the delta since today, removes the test backups/oksigenia dir as well
sudo rsync -aHAX --numeric-ids --delete --info=progress2 \
  --exclude=/cache/ --exclude=/localcache/ --exclude=/sessions/ --exclude=/temp/ --exclude=/lock/ \
  root@edu11:<repo>/moodle/data/moodle_userdata/ ./data/moodle_userdata/
```

### 5.4 Upgrade and verify (new VM)

Repeat Phase 3.1 and 3.2 with the final dump, and apply every fix recorded in Phase 4. Watch the logs until `Moodle is ready`. The site stays in maintenance mode.

### 5.5 Switch nginx (edu10) and verify

In `nginx/nginx/conf/moodle.conf`:

```nginx
upstream moodle_server {
   server <NEW_IP>:80; #new VM
}
```

Reload nginx. The new site is now behind `moodle.ict-bz.ch` but **still in maintenance mode**: only site admins can log in, so check it as admin first: Notifications, Environment, Plugins overview, one course, cron log, "Send test email" (**emails must be sent now**).

Then open it on the new VM:

```bash
docker compose exec moodle moodle-cli maintenance --disable
```

Run the rest of the Phase 4 checklist (LDAP logins, quiz, upload, backup/restore).

### 5.6 Rollback

| When | How |
|---|---|
| Before the new site leaves maintenance mode | Set the upstream back to edu11 (if already switched), reload nginx, `maintenance.php --disable` on edu11. Nothing is lost. |
| After that, before users wrote data | Same as above. |
| After users wrote data on the new VM | Fix forward on the new VM. A rollback to edu11 loses everything written since 5.2. The image's pre-upgrade backup (`oksigenia/backups/<date>/`) can only restore the 5.0 state on the new VM, and is not compatible with the upgraded database. |

Decide the go/no-go point (e.g. "rollback only until X o'clock on Saturday") before the window starts.

---

## Phase 6: After the cutover

- [ ] **Stop the containers on edu11** (`docker compose stop`). The old image is vulnerable to php2shell and must not stay reachable. Keep the VM itself (powered off) for 2–4 weeks as a fallback, then delete it.
- [ ] Delete the test vhost `moodletest.conf` on edu10 (or keep it for future upgrade tests).
- [ ] Merge the `moodle-oksigenia` branch: new `docker-compose.yml`, updated `.env.example` (without the Bitnami-only variables, with `MOODLE_URL` and `PHP_*`), add `config.d/.gitkeep`, add `data/` and `config.d/*.php` handling to `.gitignore` as needed.
- [ ] Remove `setup.sh`, `copy_htaccess.sh` and `.htaccess` (replaced by the official MariaDB image and the `PHP_*` variables).
- [ ] Update `readme.md`: new server/IP for Moodle, remove the `user` / `bitnami` default login and the `setup.sh` / `.htaccess` instructions, LDAP docs link for 5.3, how to upgrade (change the tag, `docker compose pull && docker compose up -d`), where the pre-upgrade backups are.
- [ ] Update `php2shell-assessment.md`: Moodle status → done, with the PHP version.
- [ ] Set up regular backups of the new VM (DB dump + moodledata), independent of the image's pre-upgrade backups.
- [ ] Watch `oksigenia/moodle` releases (new `-rN` tags for PHP/Debian fixes, Moodle point releases) and update monthly.
- [ ] Clean up the dumps in `~/` on the new VM (they contain personal data).

## Open points

- `<repo>` path on edu11 and the new VM, `<NEW_IP>`: fill in.
- Whether `MOODLE_URL` overrides `wwwroot` in an existing Bitnami `config.php` is not documented by Oksigenia. The test in Phase 4 answers this.
- Until the cutover, edu11 stays on the unpatched PHP (php2shell). Moodle can't be made internal-only, so the exposure remains until the weekend; keep the cutover as early as possible.
