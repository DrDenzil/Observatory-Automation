# Observatory Automation

A modern web-based observation request, scheduling, and execution platform for the Bayfordbury Observatory (University of Hertfordshire). This project replaces the legacy PHP system with a React frontend and FastAPI backend, integrated with a Go-based EKOS runner for automated KStars driving.

## Architecture

```
Observatory-Automation/
├── website/
│   ├── frontend/          React 18 + TypeScript + Vite
│   └── backend/           FastAPI + SQLAlchemy async ORM
├── runner/                Go agent (D-Bus → KStars/EKOS/INDI)
├── segments/              Design docs & blueprints
└── Original Repo/         Legacy PHP codebase (reference)
```

## Implemented Features

### Phase 1 MVP ✅
- **Request submission** — observers submit observation requests with targets, filters, binning
- **Staff approval workflow** — approve/reject requests with optional rejection reason
- **Job queue & status** — submitted jobs → queued → running → completed/failed
- **Live dashboards** — observer (my requests), staff (approval + job queue), admin (all activity)
- **Role-based access** — observer / staff / admin, enforced on API + frontend

### Phase 2 — Request Enhancements ✅
- **A1: Target name autocomplete** — 110+ Messier + NGC + named stars, SIMBAD fallback via Sesame API
  - Local catalogue search with fuzzy matching (primary, alias, substring)
  - SIMBAD HTTP API fallback with caching (SimbadCache table)
  - Common names displayed inline (e.g. "M31 — Andromeda Galaxy")
- **A2: Mandatory telescope selection** — observer must pick a telescope when submitting
  - Telescope linked to request, stored in DB, displayed on approval page
- **A3: Exposure calculator** — ported from `expcalc.php`
  - Camera database (SBIG STX-16803, ASI6200, Q49000, etc.)
  - SNR calculation with full physics (zero-mag flux, encircled energy integral)
  - Noise budget breakdown (source / sky / read / dark)
  - Three modes: Calculate SNR | Find max exposure | Solve to target SNR
- **A4: Per-telescope constraints** — filters and binning constraints enforced per telescope
  - NewRequest form clamps available filters/binning to selected telescope
  - Switching telescopes auto-adjusts existing targets' filters/binning

### Phase 3 — Telescope & Runner Integration ✅
- **B1: Telescope configuration page** (admin only)
  - CRUD operations: add, edit, delete telescopes
  - Config stored in TelescopeConfig model (id, num, short_name, filters, FOV, dec limits, min_binning, status)
  - Seed data pre-loaded (CKT, PIRATE)
- **Runner (Go)** — replaces old Python automation
  - Polls `/api/runner/jobs/next?scope_id={id}` to claim jobs
  - Builds EKOS schedule bundles from observation request
  - Drives KStars/EKOS via D-Bus (load schedule, start capture, monitor progress)
  - Sends heartbeats every 10s (state, progress, hardware health)
  - Reports job progress + completion/failure back to web API
  - Simulator mode for testing without hardware
- **Run Now dispatch button** (staff only)
  - Scope selector (filters to online + idle scopes)
  - POST `/api/jobs/{job_id}/dispatch` pins job to scope
  - Runner picks it up immediately (polled frequently when pinned)
  - Validates scope is online and idle before dispatch

### Supporting Features ✅
- **Dark/light/redshift theming** — CSS variables, configurable per user
- **Request detail page** — full view + approval/rejection actions
- **Job queue visibility** — live status updates (queued/running/completed/failed)
- **Scope status panel** — shows online/offline, state, current job, hardware (KStars/INDI running)
- **Database** — SQLite with SQLAlchemy async ORM, migrations via `create_all()` on startup

---

## Not Yet Implemented

### High Priority (deployability)
1. **User registration & management UI** (admin page)
   - Currently only possible via direct DB edit
   - Need: list users, change roles, create accounts, delete users
   - Impacts: cannot onboard real observers without this

2. **Draft request saving**
   - Currently NewRequest submits immediately
   - Need: Save as draft → come back later flow
   - Impacts: observers lose work if browser closed mid-form

3. **Job detail page** — `/job/:id` route
   - Currently job errors invisible unless querying API directly
   - Need: full job view with error messages, runner progress, logs
   - Impacts: staff can't debug failed jobs

4. **Observer registration** — `/register` route
   - Currently Login-only; new users need admin to create DB account
   - Need: self-serve signup with email verification
   - Impacts: cannot hand off to new users

### Medium Priority (feature completeness)
5. **Weather integration** (C1)
   - Blocked on weather station data feed format
   - Need: `/api/weather` endpoint, live chart on status page
   - References: `Original Repo/runner/weather_safety.py` has safety thresholds
   - Impacts: automation can't auto-park if weather degrades

6. **All-sky cameras** (C2)
   - Blocked on source files (contact Bayfordbury directly)
   - Need: ingest latest frame + optional timelapse viewer
   - Impacts: observers can't check sky conditions live

7. **FITS ingest/archive**
   - Captures written to disk by KStars; no archive ingestion yet
   - Need: move FITS files to archive, index, make queryable
   - Impacts: captured images not retrievable from web UI

8. **D-Bus validation**
   - Runner code assumes KStars/EKOS available; no offline test mode
   - Need: integration tests against real or mocked D-Bus services
   - Impacts: hard to validate runner on dev machines without full setup

### Low Priority (nice-to-have)
9. **Instant Run Now** — dispatch API currently waits for next poll
   - Runner has local HTTP trigger endpoint; could ping it immediately
   - Impacts: perceived latency on Run Now button

10. **Runner unit tests**
    - Only `ekos_test.go` exists; `pipeline.go`, `client.go`, `state.go` untested
    - Would help catch regressions without hardware

---

## Setup

### Backend
```bash
cd website/backend
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt

# Run migrations & seed data
# (already done on app startup via Base.metadata.create_all)

# Start server
.venv/bin/python -m uvicorn app.main:app --reload --port 8000
```

### Frontend
```bash
cd website/frontend
npm install
npm run dev  # Vite dev server on http://localhost:5173
```

### Runner
```bash
cd runner
go build -o ekos-runner
cp runner.yaml.example runner.yaml  # Edit with your machine ID, API URL, etc.
./ekos-runner -config runner.yaml
```

---

## Environment & Config

### Backend (FastAPI)
- **Database** — SQLite (`observatory.db`), async via aiosqlite
- **Auth** — JWT tokens (HS256), roles stored in User model
- **SIMBAD** — HTTP Sesame API for target name resolution
- **Dependencies** — see `requirements.txt` (FastAPI, SQLAlchemy, aiosqlite, httpx, pydantic, etc.)

### Frontend (React)
- **Build** — Vite + esbuild
- **Routing** — React Router v6
- **Styling** — CSS Modules + CSS variables (dark/light/redshift themes)
- **HTTP** — fetch API wrapped in `api/client.ts`

### Runner (Go)
- **D-Bus** — godbus/dbus for KStars/EKOS/INDI communication
- **Config** — YAML file (scope_id, web URL, poll interval, etc.)
- **Simulator** — `NewSimKStars()` for testing without hardware
- **Service** — systemd unit (`ekos-runner.service`)

---

## File Structure Highlights

### Backend
```
website/backend/app/
├── models/           SQLAlchemy ORM models
│   ├── job.py        Job (status, scope_id, timestamps)
│   ├── request.py    ObservationRequest (linked to TelescopeConfig)
│   ├── target.py     Target (RA, dec, filters, binning, exposure)
│   ├── scope.py      Scope (runner heartbeat state)
│   ├── telescope.py  TelescopeConfig (filters, FOV, dec limits)
│   ├── user.py       User (role, auth)
│   └── catalogue.py  SimbadCache (target name → RA/dec cache)
├── routes/           FastAPI routers
│   ├── requests.py   CRUD + approve/reject
│   ├── jobs.py       List + dispatch endpoint
│   ├── runner.py     Runner job claim & progress
│   ├── scopes.py     Scope status list
│   ├── catalogue.py  Target name resolution (local + SIMBAD)
│   ├── telescopes.py Admin CRUD
│   └── auth.py       Login, token refresh
├── schemas/          Pydantic v2 models (serialization)
├── services/         Auth service, role checking
├── data/             catalogue.py — 175+ object definitions
└── main.py           App init + DB setup
```

### Frontend
```
website/frontend/src/
├── pages/
│   ├── Dashboard.tsx              Observer & staff dashboards
│   ├── NewRequest.tsx             Submit observation request
│   ├── RequestDetail.tsx          View + approve/reject
│   ├── StaffDashboard.tsx         Pending approval, job queue, Run Now
│   ├── ExposureCalculator.tsx     Full exposure physics calculator
│   ├── Telescopes.tsx             Admin telescope CRUD
│   └── Login.tsx                  JWT-based login
├── components/
│   ├── Header.tsx                 Nav, theme toggle, logout
│   ├── ScopePanel.tsx             Telescope status grid
│   └── AuthContext.tsx, ThemeContext.tsx — global state
├── api/
│   ├── client.ts                  Fetch wrapper, error handling
│   └── types.ts                   TypeScript interfaces (User, Job, Scope, etc.)
└── styles/                        Global CSS + theme variables
```

### Runner
```
runner/
├── main.go           Entry point, signal handling
├── config.go         YAML config loading
├── client.go         HTTP client to web API
├── pipeline.go       Job execution loop (bundle → load → run → report)
├── ekos.go           EKOS schedule bundle builder
├── kstars.go         KStars D-Bus interface (real + simulator)
├── state.go          Agent state machine
├── logger.go         Structured logging
├── server.go         Local HTTP API for progress/status
└── runner.yaml.example  Config template
```

---

## Testing

### Backend
```bash
# No automated tests yet (manual CRUD verification done)
# Recommend: pytest + pytest-asyncio for DB/API tests
```

### Frontend
```bash
# Run dev server, manual feature testing
npm run dev
# Type checking:
npm run type-check  # if configured
```

### Runner
```bash
# Unit tests exist for ekos_test.go
go test ./...
# Simulator mode available for testing without hardware
# Set Runner.Simulator: true in runner.yaml
```

---

## Known Issues & Workarounds

1. **Port 8000 in use** — kill old process: `lsof -ti:8000 | xargs kill -9`
2. **ModuleNotFoundError on backend startup** — ensure working dir is `website/backend/`
3. **Database schema stale** — SQLite doesn't auto-migrate; delete `observatory.db` and restart
4. **SIMBAD timeout** — 5s timeout; may fail on poor connectivity; fallback to local catalogue
5. **Exposure calculator camera match** — fuzzy matching can mismatch; review `expcalc.php` camera list

---

## Deployment

### Docker
```bash
cd runner
docker build -t ekos-runner .
docker run --network host -v /path/to/config:/config ekos-runner
```

### Systemd Service
```bash
sudo cp runner/ekos-runner.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now ekos-runner
```

### Web (FastAPI + React)
- Frontend: build with `npm run build`, serve static dist/ via nginx/S3
- Backend: deploy via gunicorn/uvicorn behind nginx reverse proxy
- Database: move from SQLite to PostgreSQL for multi-instance deployments

---

## Next Steps for Deployment

1. **Implement user management UI** (priority #1 blocker)
2. **Add observer registration** (self-serve signup)
3. **Build job detail page** (error visibility)
4. **Test on real hardware** (KStars/EKOS/INDI stack on Linux box)
5. **Integrate weather feed** (once data source identified)
6. **Implement FITS ingest** (archive integration)

---

## References

- **Original PHP codebase** — `Original Repo/` (reference for legacy behavior)
- **Weather safety logic** — `Original Repo/runner/weather_safety.py`
- **Exposure calculator physics** — `Original Repo/backend/source/PHP files/expcalc.php`
- **Architecture design** — `segments/website/BLUEPRINT.md`
- **Feature roadmap** — `ROADMAP.md`

---

## Contributing

- Backend changes: update `app/` models, routes, or schemas; rebuild DB if schema changes
- Frontend changes: edit `website/frontend/src/`; Vite HMR reloads automatically
- Runner changes: `go build` and test in simulator mode first
- All changes require testing before push (see Testing section)

---

## License

(Check upstream repo for license terms)
