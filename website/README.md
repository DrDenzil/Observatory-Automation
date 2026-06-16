# Bayfordbury Observatory — Web Application

Request management portal for the University of Hertfordshire Bayfordbury Observatory.
Observers submit imaging requests; staff approve them and assign them to a telescope scope.

---

## Architecture

```
Observatory-Automation/
├── website/
│   ├── backend/    FastAPI + SQLAlchemy (async) + SQLite/PostgreSQL
│   └── frontend/   React + Vite + TypeScript
└── runner/         Go agent on each telescope machine (see runner/README.md)
```

The backend exposes a REST API at `/api/`. The frontend is a single-page app that
talks to it. The **runner** agents (one per telescope) poll the backend for queued
jobs, drive KStars/EKOS, and report status back — replacing the old bash automation.

### Runner integration

```
Observer submits request ──▶ Staff approves ──▶ Job queued
                                                   │
   Runner polls  GET /api/runner/jobs/next ◀───────┘   (claims next job for its scope)
   Runner builds .esq/.esl, drives KStars
   Runner reports POST /api/runner/jobs/{id}/progress ─▶ Job running → completed
   Runner beats  POST /api/runner/heartbeat ──────────▶ Staff dashboard shows live status
```

Runners authenticate with the `X-Runner-Key` header (shared secret = backend
`RUNNER_API_KEY`). The staff dashboard's **Telescope Control** panel reads
`GET /api/scopes` to show each scope's live state, current job, hardware flags,
and last heartbeat.

---

## Local Development

### Prerequisites

- Python 3.12+
- Node.js 20+
- (Optional) Docker + Docker Compose for production deploy

### 1. Backend

```bash
cd website/backend

python -m venv .venv
source .venv/bin/activate          # Windows: .venv\Scripts\activate

pip install -r requirements.txt

# Create the database and seed default users
python seed.py

# Start the dev server
uvicorn app.main:app --reload --port 8000
```

API docs available at http://localhost:8000/docs

### 2. Frontend

```bash
cd website/frontend

npm install
npm run dev
```

App available at http://localhost:5173 (or whichever port Vite picks).

---

## Environment Variables

Create `website/backend/.env` to override defaults:

| Variable | Default | Description |
|---|---|---|
| `DATABASE_URL` | `sqlite+aiosqlite:///./observatory.db` | SQLAlchemy async DB URL |
| `SECRET_KEY` | `dev-secret-key-change-in-production` | JWT signing key — **change this in production** |
| `ACCESS_TOKEN_EXPIRE_MINUTES` | `480` | JWT lifetime (8 hours) |
| `CORS_ORIGINS` | `http://localhost:5173,...` | Comma-separated allowed origins |
| `RUNNER_API_KEY` | `dev-runner-key-change-in-production` | Shared secret telescope runners present in `X-Runner-Key` — **change this** |
| `SCOPE_OFFLINE_AFTER_SECONDS` | `120` | Seconds without a heartbeat before a scope shows offline |
| `ADMIN_EMAIL` | `admin@observatory.local` | Seed script admin email |
| `ADMIN_PASSWORD` | `changeme` | Seed script admin password — **change this** |
| `STAFF_EMAIL` | `staff@observatory.local` | Seed script staff email |
| `STAFF_PASSWORD` | `changeme` | Seed script staff password |
| `OBSERVER_EMAIL` | `observer@observatory.local` | Seed script observer email |
| `OBSERVER_PASSWORD` | `changeme` | Seed script observer password |

---

## Seed Script

```bash
cd website/backend
python seed.py              # create schema + default users (idempotent)
python seed.py --reset      # DROP all tables first, then re-seed (prompts for confirmation)
```

Default seed users:

| Role | Email | Password |
|---|---|---|
| admin | admin@observatory.local | changeme |
| staff | staff@observatory.local | changeme |
| observer | observer@observatory.local | changeme |

---

## Production Deploy (Docker Compose)

```bash
# From the repository root
cp website/backend/.env.example website/backend/.env   # edit with real secrets
docker compose up -d
```

The compose file starts:
- `backend` — FastAPI on port 8000
- `frontend` — nginx serving the built React app on port 80, proxying `/api/` to the backend

On first deploy, seed the database:

```bash
docker compose exec backend python seed.py
```

---

## User Roles

| Role | Permissions |
|---|---|
| `observer` | Submit and view own requests |
| `staff` | All observer permissions + approve/reject requests, manage job queue |
| `admin` | All staff permissions |

---

## CI

GitHub Actions runs on every push to `main` / `develop` and on pull requests:

1. **Backend** — `ruff` lint, `pyright` type check
2. **Frontend** — `tsc --noEmit` type check, `npm run build`
3. **Docker** — builds both images to catch Dockerfile regressions
