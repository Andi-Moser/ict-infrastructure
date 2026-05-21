# TeamEats — Local Development

## Prerequisites

Choose one of the two approaches below. Docker is recommended as it matches production.

**Option A — Docker (recommended)**
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) or Docker Engine + Compose plugin

**Option B — PHP directly**
- PHP 8.1 or higher
- Extensions: `pdo`, `pdo_sqlite` (check with `php -m | grep -i sqlite`)

---

## Option A: Docker

### First run

```bash
docker compose up --build
```

The `--build` flag is only needed the first time (or after changing the `Dockerfile`).

### Subsequent runs

```bash
docker compose up
```

The app is now available at **http://localhost:8080**.

### How live reload works

`docker-compose.override.yml` is picked up automatically alongside `docker-compose.yml`. It mounts your local source files directly into the container:

```
./api/       → /app/api/
./public/    → /app/public/
./router.php → /app/router.php
```

This means **editing any PHP or JS file takes effect immediately** — no rebuild needed. The only time you need to rebuild is when you change the `Dockerfile` itself (e.g. adding a PHP extension).

### Watching logs

```bash
docker compose logs -f
```

PHP errors, warnings, and the request log all appear here.

### Stopping

```bash
docker compose down
```

The SQLite database is stored in `./data/teameats.db` (a Docker volume mount), so data persists between restarts.

---

## Option B: PHP built-in server

```bash
DB_PATH=./data/teameats.db php -S localhost:8080 router.php
```

The app is now available at **http://localhost:8080**.

Every file edit is live instantly. PHP errors appear directly in the terminal.

---

## Testing the API with curl

```bash
# List all ideas
curl http://localhost:8080/api/ideas

# List predefined suggestions
curl http://localhost:8080/api/predefined

# Create an idea (predefined)
curl -X POST http://localhost:8080/api/ideas \
  -H "Content-Type: application/json" \
  -d '{"date":"2026-06-10","idea":"Pizza","description":"From the place around the corner","proposed_by":"Andi","email":"andi@office.com"}'

# Create an idea (custom)
curl -X POST http://localhost:8080/api/ideas \
  -H "Content-Type: application/json" \
  -d '{"date":"2026-06-10","idea":"Thai takeaway","proposed_by":"Bob","email":"bob@office.com"}'

# Register for idea #1
curl -X POST http://localhost:8080/api/ideas/1/registrations \
  -H "Content-Type: application/json" \
  -d '{"name":"John Instructor","email":"john@office.com","comment":"Count me in!"}'

# List registrations for idea #1
curl http://localhost:8080/api/ideas/1/registrations

# Unregister (registration id=1, must provide matching email)
curl -X DELETE http://localhost:8080/api/registrations/1 \
  -H "Content-Type: application/json" \
  -d '{"email":"john@office.com"}'

# Delete an idea (no registrations: any email works; with registrations: must be proposer email)
curl -X DELETE http://localhost:8080/api/ideas/1 \
  -H "Content-Type: application/json" \
  -d '{"email":"andi@office.com"}'
```

---

## Testing email locally

The app sends email via a plain SMTP relay (no auth). In production this is the office relay, but locally you can capture emails with [Mailpit](https://github.com/axllent/mailpit):

```bash
# Start Mailpit (SMTP on :1025, web UI on :8025)
docker run -d --name mailpit -p 1025:1025 -p 8025:8025 axllent/mailpit
```

Then set `SMTP_HOST` and `SMTP_PORT` in `docker-compose.override.yml`:

```yaml
environment:
  SMTP_HOST: "host.docker.internal"   # reaches localhost from inside Docker
  SMTP_PORT: "1025"
```

Or if using the PHP built-in server directly:

```bash
SMTP_HOST=localhost SMTP_PORT=1025 DB_PATH=./data/teameats.db php -S localhost:8080 router.php
```

Open **http://localhost:8025** to see captured emails.

If `SMTP_HOST` is empty (the default in `docker-compose.override.yml`), the app skips sending and logs a notice — registrations still work normally.

---

## Customising the predefined ideas

Edit `public/predefined_ideas.json`. Each entry:

```json
{
  "id":          "unique-slug",
  "name":        "Display name",
  "description": "Short description shown on the card",
  "image_url":   "/images/yourfile.jpg"   // or null
}
```

Images go in `public/images/`. No server restart needed — the file is read on each request.

---

## Production deployment

### 1. Copy files to the server

Copy the project to the server — everything except `docker-compose.override.yml` (that file is for local development only and must not be present on the server, otherwise Docker Compose would pick it up automatically).

```
teameats/
├── Dockerfile
├── docker-compose.yml
├── .env                   ← create this on the server (see step 2)
├── router.php
├── api/
├── public/
└── data/                  ← created automatically on first run
```

### 2. Configure environment on the server

Create a `.env` file (use `.env.example` as a template):

```bash
SMTP_HOST=relay.office.lan
SMTP_PORT=25
```

### 3. Build and start

```bash
docker compose up -d --build
```

The app is now available on port **8080**. Your external nginx proxies to it and handles IP whitelisting — the app itself has no authentication.

### 4. Check container health

```bash
docker compose ps          # should show "healthy" after ~30 seconds
docker compose logs -f     # live log stream
```

### 5. Deploy an update

```bash
docker compose down
git pull                   # or copy updated files
docker compose up -d --build
```

Data is preserved because the SQLite database lives in `./data/` on the host, outside the container.

### 6. Backup

The entire database is a single file. Back it up with:

```bash
cp data/teameats.db data/teameats.db.bak
```

Or automate with a cron job that copies the file off-server.

### nginx IP whitelist (reference)

The app relies on an external nginx to restrict access to the office network. Minimal relevant config:

```nginx
server {
    listen 80;
    server_name teameats.office.lan;

    allow 10.0.0.0/8;      # office network — adjust to your range
    deny  all;

    location / {
        proxy_pass http://localhost:8080;
    }
}
```

---

## Project structure

```
teameats/
├── Dockerfile
├── docker-compose.yml          # production config
├── docker-compose.override.yml # dev overrides (auto-loaded locally)
├── router.php                  # routes /api/* to PHP, everything else to public/
├── api/
│   ├── index.php               # API router
│   ├── db.php                  # SQLite connection + migrations
│   ├── ideas.php               # ideas endpoints
│   ├── registrations.php       # registrations endpoints
│   └── mail.php                # SMTP email helper
├── public/
│   ├── index.html              # Vue 3 app shell
│   ├── app.js                  # Vue 3 logic (Composition API)
│   └── predefined_ideas.json   # editable list of lunch suggestions
└── data/
    └── teameats.db             # SQLite database (created on first run)
```
