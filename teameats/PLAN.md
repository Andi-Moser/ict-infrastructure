# TeamEats — Development Plan

## Status: ✅ All phases complete

---

## Phases

### Phase 1 — Project scaffold ✓
- [x] Create directory structure (`/api`, `/public`, `/data`)
- [x] Create `Dockerfile` that runs PHP built-in server and serves the frontend
- [x] Create `docker-compose.yml` with a volume for the SQLite database
- [x] Verify the container boots and serves a placeholder `index.html`

### Phase 2 — Backend API (PHP + SQLite) ✓
- [x] Write `db.php` — open/create SQLite database, run migrations on first boot
- [x] Create the `ideas` table schema
- [x] Create the `registrations` table schema (FK to `ideas.id`)
- [x] `GET  /api/ideas` — list all ideas (with registration count) optionally filtered by date
- [x] `POST /api/ideas` — create a new idea (custom or from predefined list)
- [x] `DELETE /api/ideas/{id}` — delete an idea (only if no registrations yet, or by proposer)
- [x] `GET  /api/ideas/{id}/registrations` — list registrations for an idea
- [x] `POST /api/ideas/{id}/registrations` — register an instructor (triggers email)
- [x] `DELETE /api/registrations/{id}` — unregister an instructor
- [x] Write `mail.php` — send email via SMTP relay to the idea proposer
- [x] Add basic input validation and error responses (JSON)

### Phase 3 — Predefined ideas config ✓
- [x] Create `predefined_ideas.json` with name, description, and image URL for each known lunch spot
- [x] Expose `GET /api/predefined` so the frontend can load the list dynamically

### Phase 4 — Frontend (VueJS CDN + Tailwind CDN) ✓
- [x] `index.html` shell — include Vue 3 CDN and Tailwind CDN links
- [x] `app.js` — main Vue app entry point
- [x] **Ideas list view** — show all upcoming ideas grouped by date; show registration count per idea
- [x] **Add idea form** — dropdown to pick from predefined ideas or type a custom one; date picker; proposer name + email
- [x] **Idea detail / register panel** — show idea info; list who is registered; register form (name, email, optional comment)
- [x] **Unregister** — allow an instructor to remove their registration (by matching name + email)
- [x] Basic responsive layout with Tailwind

### Phase 5 — Email notification ✓
- [x] On successful registration, send email from instructor's address to idea proposer via SMTP relay
- [x] Email body includes: instructor name, idea, date, optional comment
- [x] Confirm SMTP relay is reachable from within the container

### Phase 6 — Polish & edge cases ✓
- [x] Past dates: hidden from main list; collapsible "vergangene Mittagessen" section at the bottom
- [x] Prevent duplicate registrations (same name + email for same idea)
- [x] Predefined ideas show their image; custom ideas show a generic placeholder
- [x] Friendly empty states ("Noch keine Mittagsideen — sei der Erste!")
- [x] Confirm dialogs before unregistering

### Phase 7 — Docker & deployment ✓
- [x] Finalise `Dockerfile` (PHP with SQLite extension, copy source files)
- [x] Add `.dockerignore` to keep secrets and dev files out of the image
- [x] Add `HEALTHCHECK` so Docker reports container status
- [x] Document how to build and run the container
- [x] Verify volume persistence across container restarts
- [x] Note that nginx IP whitelisting is handled externally — no in-app auth needed

---

## File structure (target)

```
teameats/
├── Dockerfile
├── docker-compose.yml
├── api/
│   ├── index.php          # router / front controller
│   ├── db.php             # database connection & migrations
│   ├── mail.php           # SMTP email helper
│   ├── ideas.php          # ideas endpoints
│   └── registrations.php  # registrations endpoints
├── public/
│   ├── index.html         # Vue + Tailwind CDN shell
│   ├── app.js             # Vue app
│   └── predefined_ideas.json
└── data/                  # SQLite DB lives here (Docker volume)
```

---

## Key decisions & constraints

| Concern | Decision |
|---|---|
| Auth | None — IP whitelist handled by external nginx |
| Build step | None — Vue and Tailwind via CDN only |
| Database | SQLite file in a Docker volume |
| Email | SMTP relay on office network, no credentials needed |
| Images | Only predefined ideas have images (hardcoded in JSON) |
| Duplicate prevention | Block same name + email registering twice for the same idea |
