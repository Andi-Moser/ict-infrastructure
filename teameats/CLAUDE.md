# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

TeamEats is a lunch-coordination app for office staff. Office workers propose lunch ideas for a date; instructors register interest; an email is sent to the proposer on each registration. No authentication — access is restricted by an external nginx IP whitelist.

## Running locally

**Docker (recommended — matches production):**
```bash
docker compose up --build   # first run
docker compose up           # subsequent runs
```
App is at http://localhost:8080. Editing any PHP or JS file takes effect immediately via the `docker-compose.override.yml` volume mounts — no rebuild needed unless `Dockerfile` changes.

**PHP built-in server:**
```bash
DB_PATH=./data/teameats.db php -S localhost:8080 router.php
```

**Capture emails locally (Mailpit):**
```bash
docker run -d --name mailpit -p 1025:1025 -p 8025:8025 axllent/mailpit
# Then set in docker-compose.override.yml:
# SMTP_HOST: "host.docker.internal"
# SMTP_PORT: "1025"
```
Web UI at http://localhost:8025. If `SMTP_HOST` is empty, email is skipped silently.

## Architecture

No build step anywhere. Vue 3 and Tailwind are loaded from CDN.

**Request flow:**
1. `router.php` — PHP built-in server router. Routes `/api/*` to `api/index.php`; everything else is served as a static file from `public/`.
2. `api/index.php` — manual URL dispatcher. Parses path segments and delegates to handler functions.
3. `api/db.php` — singleton PDO connection to SQLite. Runs `CREATE TABLE IF NOT EXISTS` migrations on every cold start. Also defines shared helpers: `json_response()`, `request_body()`, `require_fields()`.
4. `api/ideas.php` / `api/registrations.php` — endpoint handlers called by the dispatcher.
5. `api/mail.php` — thin SMTP wrapper (no auth, plain relay).

**Frontend (`public/`):**
- `index.html` — Vue 3 CDN app shell with Tailwind CDN.
- `app.js` — single-file Vue 3 Composition API app. All views, state, and API calls live here.
- `predefined_ideas.json` — editable list of preset lunch spots. Only predefined ideas can carry an image. Served via `GET /api/predefined`.

**Database:** SQLite file at `./data/teameats.db` (host-mounted volume). Schema is managed by `migrate()` in `db.php` — add new `ALTER TABLE` or `CREATE TABLE IF NOT EXISTS` statements there.

## API endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/ideas` | All ideas with registration count (filter: `?date=YYYY-MM-DD`) |
| POST | `/api/ideas` | Create idea |
| DELETE | `/api/ideas/{id}` | Delete idea (proposer email required if registrations exist) |
| GET | `/api/ideas/{id}/registrations` | List registrations for an idea |
| POST | `/api/ideas/{id}/registrations` | Register; triggers email to proposer |
| DELETE | `/api/registrations/{id}` | Unregister (matching email required) |
| GET | `/api/predefined` | List predefined ideas from JSON |

## Environment variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `SMTP_HOST` | *(empty)* | SMTP relay host; empty = skip email |
| `SMTP_PORT` | `25` | SMTP relay port |
| `DB_PATH` | `../data/teameats.db` | SQLite file path (PHP server only) |

## Production deployment

Do **not** copy `docker-compose.override.yml` to the server — Docker Compose picks it up automatically and would mount local source files over the container image.

```bash
docker compose up -d --build
```

Database backup: `cp data/teameats.db data/teameats.db.bak`
