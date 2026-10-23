# Assessment: PHP2Shell (CVE-2026-109618 / CVE-2026-109619)

*Status: 2026-10-21 · Scope: all services in this repository plus the vhosts on the nginx reverse proxy (`edu10`)*

## TL;DR

- **What it is:** Two critical memory-safety bugs in PHP core. Both are rated CVSS v4.0 9.2. An attacker without a login can run code on the server by sending a crafted `multipart/form-data` request. PHP parses that request **before any application code runs**, so a login page or app-level security does not help.
- **Affected:** Every PHP version from 5.4 upwards. Fixes were released on 2026-10-21: **8.5.12, 8.4.27, 8.3.36 and 8.2.35**. PHP 8.1 and older get no official release.
- **Exploitation:** Details leaked on 2026-10-20. Exploit code is public, and Cloudflare and Patchstack report probing and early attacks. Expect mass scanning.
- **Our exposure:** At least **4 PHP services are reachable from the internet with no restriction**: formlog, bookstack, moodle and owncloud. ldap is probably a fifth. **formlog is the worst case.** It runs PHP 8.4.1 under mod_php, which PHP rates highest risk, and it calls `session_start()`. **owncloud cannot be patched** because ownCloud 10 runs on EOL PHP 7.4.
- **The idea "restrict to internal network via nginx":** It is a good **short-term containment** step and we should do it today. It is **not a fix**. Our internal network is a school network with learners on it, and the backend ports can be reached directly, bypassing nginx. Moodle and ownCloud probably cannot be made internal-only at all. Patching is still required.

## Decisions & status (2026-10-21)

| Service | Decision | Status |
|---|---|---|
| formlog | Not critical. Upgrade to PHP 8.5.12. | `Dockerfile` pinned to `php:8.5.12-apache`. `pull_policy: build` added so `docker compose up` rebuilds. The build fails on purpose until the image is published (only `8.5.12RC1` and `8.5.11` exist today), so no vulnerable build is possible. Code lints cleanly on PHP 8.5. |
| bookstack | Not critical. Next `up` should use the newest image. | Image set to `latest` with `pull_policy: always`. The current `latest` (v26.09.2, built 2026-10-20) predates the fix, so check `php -v` in the container after the next `up`, then pin back to a version tag. |
| moodle | Handled separately | open |
| owncloud | Block outside access until the upgrade | `owncloud.conf` **and `cloudtest.conf`** (same upstream) include `snippets/internal-only.inc` |
| ldap | Being patched | in progress |
| teameats | Taken offline | in progress |
| cloud, kurse | Restrict to internal access | `cloud.conf` and `kurse.conf` include `snippets/internal-only.inc` |
| st, uk, proxmox-gui, others | No PHP in use | not affected |

`snippets/internal-only.inc` allows only `213.144.139.58`, the same IP already used for teameats. If internal clients reach edu10 with their `10.x` address instead, add those ranges there. Only do that if the firewall does **not** source-NAT external traffic into `10.x`, otherwise the block lets everyone through.

## 1. The vulnerability

| | CVE-2026-109618 | CVE-2026-109619 |
|---|---|---|
| Root cause | An out-of-memory or fatal error interrupts PHP while request data is only partly initialised. Sansec ties this to the session upload-progress handler. | Request-owned internal state survives after its memory has been freed (use-after-free in the fatal-error backtrace / OPcache paths). |
| Impact | Unauthenticated RCE, memory disclosure | Unauthenticated RCE |
| Trigger | A `multipart/form-data` request. It is processed before the application runs. | Same |

**SAPIs:** mod_php (Apache), PHP-FPM, php-cgi, FrankenPHP and LiteSpeed are all affected. The PHP project rates the risk highest for **mod_php**. None of the sources we read says whether the CLI built-in server (`php -S`) is affected. It parses multipart bodies like the other SAPIs, so **we treat it as affected**.

**Workarounds:** The PHP project says ini settings reduce exposure but "do not make an installation safe". The options are `session.upload_progress.enabled=0`, a fixed `memory_limit`, small `post_max_size`, and `fatal_error_backtraces=0` on 8.5. PHP core developers say plainly that the only real mitigation is upgrading.

## 2. Inventory of our services

Legend: 🔴 public and vulnerable · 🟠 restricted or unclear · 🟢 not PHP

| Service (vhost) | Backend | PHP runtime | Exposure today | Patch path | Rating |
|---|---|---|---|---|---|
| **formlog** (`formlog.ict-bz.ch`) | edu18:9000 | `php:8.4.1-apache` → **mod_php**, pinned to 8.4.1 (Nov 2024) | Public, no restriction | Change base image to `php:8.4-apache` (≥ 8.4.27), rebuild | 🔴 **critical**: highest-risk SAPI, uses `session_start()`, PHP release ~2 years old |
| **bookstack** (`bookstack.ict-bz.ch`) | edu20:6875 | linuxserver image `version-v26.03.3` (nginx + PHP-FPM, Alpine) | Public. **The IP restriction was removed on 2026-09-03 (commit 7fab434).** | Bump to a linuxserver tag built with fixed Alpine PHP packages | 🔴 high |
| **moodle** (`moodle.ict-bz.ch`) | edu11:80 | `bitnami/moodle:latest` (Apache + PHP) | Public, by design | Unclear. Bitnami stopped publishing free updated images in 2025. Check whether `:latest` still receives updates. If not, migrate to another image. | 🔴 high, patch path at risk |
| **owncloud** (`owncloud.ict-bz.ch`, also `cloudtest.ict-bz.ch`, which proxies to the same upstream) | edu12:80 | `owncloud/server:10.15` → **PHP 7.4 (EOL)** | Public, by design | **No official fix.** The PHP project only offers source-level backport branches for 7.4/8.0/8.1. Whether ownCloud ships a patched image is unknown. | 🔴 **critical**: no clean patch path |
| **ldap** web UI (`ldap.ict-bz.ch`) | edu13:8000 | Not in this repo. The `/build/assets/` route suggests a Laravel app (e.g. phpLDAPadmin 2.x), so it is **probably PHP**. | Public | Verify on edu13 | 🟠 verify; it is an admin interface to our directory |
| **teameats** (`teameats.ict-bz.ch`) | edu18:8080 | `php:8.3-cli`, built-in server `php -S` | nginx allows only 213.144.139.58 | Rebuild with `--pull` (floating `8.3` tag → 8.3.36) | 🟠 medium: restricted, but see §3 |
| cloud, kurse, st, uk | edu02 / edu01 / edu03 | **Not in this repo.** Possibly PHP (LMS/cloud style services). | Public | Verify | 🟠 unknown |
| proxmox-gui | 10.10.100.110:8000 | Not in this repo | Public | Verify | 🟠 unknown |
| n8n, librechat, proxmox / m300-x | – | Node.js, Perl. Not PHP. | Public | n/a for this CVE | 🟢 |

## 3. Assessment of the proposed fix: nginx internal-only access

The proposal: add `allow <internal>; deny all;` to the vhosts on edu10, as already done for teameats.

**What it achieves**
- It blocks internet-wide mass exploitation within minutes, with one `docker compose restart` on edu10. This removes most of the realistic risk while we patch.
- The pattern already exists in the repo (`teameats.conf`, previously `bookstack.conf`), so it is easy to roll out and roll back.

**Why it is not enough on its own**
1. **The internal network is not trusted.** This is a training centre. Learners are on the internal network, and anyone behind 213.144.139.58 counts as internal. A learner downloading a public exploit is a realistic scenario, so internal-only only shrinks the circle of attackers.
2. **nginx is not the only way in.** Every backend publishes its port on all host interfaces: formlog `9000`, bookstack `6875`, teameats `8080`, owncloud `8080`/`80`, moodle `80`/`443`. Anyone who can route to `10.10.200.x` reaches PHP directly and never touches the nginx ACL. Unless a firewall restricts those ports to edu10, the ACL protects only the public hostnames.
3. **Moodle and ownCloud must stay public.** Learners use them from home. They are also the two services with the weakest patch path, so the proposal leaves the biggest risks open.
4. **It does not clean up.** Exploitation was observed before the official release, and bookstack has been public since 2026-09-03. A service that was reachable needs a compromise check (§4, step 5), not just a closed door.

**Verdict:** Do it **now** as containment for every PHP vhost that does not strictly need to be public. In parallel, block `multipart/form-data` where uploads are not needed and patch everything. Do not treat the ACL as the permanent fix.

## 4. Recommended plan

### Step 1: Contain (today, on edu10)

Restrict the PHP vhosts that do not need to be public (formlog, bookstack, ldap, teameats, and cloudtest unless it is still in use). Use a shared snippet so the rule lives in one place. It must **not** end in `.conf`, because the whole directory is mounted as `conf.d` and every `*.conf` file there is loaded at `http` level:

```nginx
# nginx/nginx/conf/snippets/internal-only.inc
allow 10.0.0.0/8;        # internal clients that reach edu10 directly
allow 213.144.139.58;    # our public egress IP (internal clients via NAT)
deny  all;
```

```nginx
# in the 443 server block of each affected vhost
include /etc/nginx/conf.d/snippets/internal-only.inc;
```

For PHP vhosts that **do not need file uploads**, also reject multipart requests, because every known trigger uses them. Neither formlog nor teameats uses uploads (no `$_FILES` and no multipart forms in the code):

```nginx
if ($http_content_type ~* "multipart/form-data") { return 403; }
```

Do **not** apply the multipart block to moodle or owncloud, because it breaks all uploads. For those two, lower `client_max_body_size` where practical (currently 512M and 2000M) and evaluate a WAF. Cloudflare has published free-tier rules for this, but it would mean moving DNS.

### Step 2: Patch the services we build ourselves (today)

- **formlog:** `FROM php:8.4.1-apache` → `FROM php:8.4-apache`, then `docker compose build --pull && docker compose up -d`. Confirm with `docker compose exec server php -v` (≥ 8.4.27). Consider moving off mod_php (`php:8.4-fpm` behind nginx) in the long run.
- **teameats:** `docker compose build --pull && docker compose up -d`. Confirm ≥ 8.3.36.

### Step 3: Update third-party images (as soon as fixed images exist)

- **bookstack:** Bump `lscr.io/linuxserver/bookstack` to a tag built after 2026-10-21. Check the PHP version inside the container.
- **moodle:** Check whether `bitnami/moodle:latest` still gets fixes. Pull and check `php -v`. If it does not, plan a migration to a maintained image now. This affects every future CVE, not just this one.
- **ldap UI / cloud / kurse / st / uk / proxmox-gui:** Determine the stack on each host. Patch any that run PHP.

### Step 4: ownCloud (decision needed)

ownCloud 10 runs PHP 7.4, which gets no official release. Options, in order of preference:
1. Restrict to the internal network, or take it offline, until a vendor-patched image exists.
2. Migrate to a maintained platform (ownCloud Infinite Scale or Nextcloud on a supported PHP). This was overdue regardless of this CVE.
3. Build PHP 7.4 from the PHP project's backport branch `PHP-7.4-CVE-2026-109618-109619`. This is expensive and unsupported, so treat it as a last resort.

Until then, apply the hardening settings in the container's PHP configuration: `session.upload_progress.enabled=0`, and review `memory_limit` and `post_max_size`. These reduce exposure but do not remove it.

### Step 5: Check for compromise

Prioritise services that were publicly reachable, especially bookstack (public since 2026-09-03), formlog, moodle and owncloud:
- nginx access logs on edu10 from 2026-10-20 onwards: look for multipart POSTs to unusual paths, bursts of 500/502 responses, and oversized or malformed `PHPSESSID` cookies.
- Containers: look for unexpected processes, new PHP files in web roots or volumes (`find /data -name '*.php' -newer <ref>`), and unexpected outbound connections.
- If anything looks suspicious, rebuild the container from a clean image and rotate the secrets it held (DB passwords, `APP_KEY`, LDAP bind credentials).

### Step 6: Structural follow-ups

- Bind backend ports to the VM's internal interface, or firewall them so only edu10 can reach them.
- Stop pinning to a single PHP patch release (`8.4.1`) and stop using unpinned `:latest`. Pin to a minor version (`8.4-apache`) and rebuild regularly.
- Take hard-coded secrets out of `bookstack/docker-compose.yml` (`APP_KEY`, DB passwords) and move them to `.env`, as the other services already do.
- Update the readme: its IPs (`10.200.2.x`) no longer match the nginx upstreams (`10.10.200.x`).

## Sources

- [PHP announce: Upcoming Critical PHP Security Release](https://news-web.php.net/php.announce/509)
- [Sansec: Critical PHP RCE (php2shell)](https://sansec.io/research/php2shell)
- [Tenable FAQ: php2shell](https://www.tenable.com/blog/php2shell-cve-2026-109618-cve-2026-109619-frequently-asked-questions-about-critical-php-remote)
- [Patchstack: PHP vulnerable to critical RCE](https://patchstack.com/articles/php-major-vulnerability-alert-10-2026/)
- [Huntress: Rapid Response php2shell](https://www.huntress.com/blog/php2shell-leaked-critical-php-vulnerabilities)
- [Qualys: PHP Announces Critical Security Release](https://blog.qualys.com/vulnerabilities-threat-research/2026/10/20/php-announces-critical-security-release-for-two-vulnerabilities)
- [NCSC UK: Critical security issue affecting PHP](https://www.ncsc.gov.uk/news/critical-security-issue-affecting-php)

> Note: php.net could not be reached while this was written (HTTP 502). Fixed versions and the backport branches are taken from Patchstack, Sansec and Tenable. Check them against the [official advisory](https://www.php.net/archive/2026.php) before relying on them.
