# Observatory Website Rebuild - Complete Blueprint

**Project:** observatory.herts.ac.uk Modernization  
**Timeline:** 3-4 months (Phase 1 at 6 weeks, Phase 2-3 follow)  
**Scale:** 1000+ users, 100k+ astronomical images  
**Lead:** Denis  
**Status:** Blueprint (Ready to build)

---

## Executive Summary

The current observatory.herts.ac.uk website is outdated, lacks dark mode (critical for nighttime use), has poor UX, and doesn't match University of Hertfordshire branding. This blueprint defines a complete rebuild across 3 phases over 3-4 months, delivering a modern, scalable platform for observation request submission, image archive browsing, and staff operations.

**What's being built:**
- **Public Portal** — Users submit observation requests
- **Staff Dashboard** — Approve jobs, monitor queue, trigger operations
- **Image Archive** — 1000+ users searching and downloading 100k+ FITS images
- **Dark Mode** — Critical feature for using in observatory domes at night
- **University Branding** — Match Herts.ac.uk visual style and standards

**Key principle:** This is a complete rewrite, not an upgrade. The existing PHP backend serves as reference for data model only. The new stack is modern, maintainable, and scales to the operational load.

---

## Current State Analysis

### What Exists
- PHP-based website (analysis reference)
- MySQL database with images, jobs, users
- Job automation pipeline (runner scripts)
- 9 robotic telescopes submitting results
- FITS image storage (~100k images)

### Pain Points
1. **Dark Mode Missing** — Astronomers use bright screen at night (unusable)
2. **Poor UX** — Navigation, search, workflow unclear
3. **Technical Debt** — Old PHP patterns, unmaintainable code
4. **No Branding** — Doesn't match University standards
5. **Limited Image Tools** — No search, comparison, or metadata display
6. **Separate Systems** — Portal, staff dashboard, automation aren't integrated

### What We're Keeping
- Database schema (as reference)
- Image storage location
- FITS file format and headers
- Job queue concept
- User authentication model

### What We're Replacing
- Frontend technology (all)
- Backend API (new design)
- UI/UX (complete redesign)
- Architecture (monolith → API-driven)

---

## Architecture Overview

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                     Load Balancer                            │
└────────────┬────────────────────────────────────────────────┘
             │
    ┌────────┴────────┐
    │                 │
┌───▼────────┐   ┌────▼──────────┐
│  Web UI    │   │  Mobile UI    │
│  (React)   │   │   (Responsive) │
└───┬────────┘   └────┬──────────┘
    │                 │
    └────────┬────────┘
             │
    ┌────────▼──────────────┐
    │  REST API Layer       │
    │  (FastAPI/Python)     │
    │  - Requests           │
    │  - Jobs               │
    │  - Images             │
    │  - Auth               │
    └────────┬──────────────┘
             │
    ┌────────┴─────────────────┐
    │                          │
┌───▼──────────────┐  ┌───────▼────────┐
│  PostgreSQL DB   │  │  Image Cache   │
│  (Primary data)  │  │  (Redis)       │
└──────────────────┘  └────────────────┘
    │
    └─────── Reads FITS from /fits/ on star-server
```

### Key Decisions

**Why Separate Web Server?**
- star-server is overloaded (database, image storage, job queue)
- Web server scales independently
- Can scale frontend without touching automation
- Easier to monitor and maintain

**Why React?**
- Large ecosystem for image manipulation (dark mode, comparison, stacking)
- Best libraries for advanced astronomy features
- Scales from 100 to 1000+ concurrent users
- Many developers can work on it

**Why FastAPI?**
- Modern Python framework
- Fast (async support)
- Automatic API docs
- Great for image/metadata processing
- Easy to integrate with existing Python scripts

**Why PostgreSQL?**
- Better full-text search than MySQL (critical for image gallery)
- JSONB for flexible metadata
- Array types for filter lists
- Scales better for 100k+ image records

**Dark Mode as First-Class Feature**
- Not a toggle added later
- Designed in from day 1
- Uses CSS-in-JS with proper theme support
- Reduces eye strain at night (astronomy critical)

---

## Phase 1: Foundation (Weeks 1-6)

**Goal:** Get observation submission and approval workflow live. This is the critical path.

### Phase 1 Deliverables

#### 1. Infrastructure & DevOps
- [ ] Separate web server (VM or cloud instance)
- [ ] Docker Compose setup for development
- [ ] PostgreSQL database initialized
- [ ] Redis cache for image metadata
- [ ] Nginx reverse proxy
- [ ] SSL/TLS certificates
- [ ] GitHub Actions CI/CD pipeline (tests + deploy)

#### 2. API Layer (Backend)
- [ ] FastAPI scaffold with auth
- [ ] Database models (users, requests, jobs, images)
- [ ] Authentication (university SSO or local accounts)
- [ ] Request submission endpoint (POST /api/requests)
- [ ] Request approval endpoint (POST /api/requests/{id}/approve)
- [ ] Job status endpoint (GET /api/jobs)
- [ ] User profile endpoint (GET /api/me)
- [ ] Error handling & logging

**Endpoints (Phase 1):**
```
POST   /api/requests              - Submit new request
GET    /api/requests              - List my requests
GET    /api/requests/{id}         - View request details
POST   /api/requests/{id}/approve - Staff: approve request
POST   /api/requests/{id}/reject  - Staff: reject request
GET    /api/jobs                  - List jobs (staff)
GET    /api/jobs/{id}             - Job details
GET    /api/me                    - Current user
POST   /api/auth/login            - Login
POST   /api/auth/logout           - Logout
```

#### 3. Frontend (React)
- [ ] Project scaffold (Create React App or Vite)
- [ ] Dark mode theme system
- [ ] University branding (colors, fonts, logo)
- [ ] Responsive layout (desktop, tablet, mobile)

**Pages (Phase 1):**
- [ ] Login page
- [ ] User dashboard (my requests, status)
- [ ] New request form (targets, filters, exposure, count)
- [ ] Staff dashboard (pending approvals, job queue)
- [ ] Request detail view

#### 4. Database Schema (Core)
```sql
CREATE TABLE users (
  id UUID PRIMARY KEY,
  email VARCHAR UNIQUE NOT NULL,
  name VARCHAR,
  role VARCHAR, -- 'observer', 'staff', 'admin'
  created_at TIMESTAMP
);

CREATE TABLE requests (
  id UUID PRIMARY KEY,
  user_id UUID REFERENCES users,
  project_name VARCHAR,
  description TEXT,
  status VARCHAR, -- 'draft', 'submitted', 'approved', 'rejected'
  created_at TIMESTAMP,
  submitted_at TIMESTAMP,
  approved_by UUID REFERENCES users,
  approved_at TIMESTAMP
);

CREATE TABLE request_targets (
  id UUID PRIMARY KEY,
  request_id UUID REFERENCES requests,
  target_name VARCHAR,
  ra FLOAT, -- degrees
  dec FLOAT, -- degrees
  filters VARCHAR[], -- ['R', 'G', 'B']
  exposure_seconds FLOAT,
  count INT
);

CREATE TABLE jobs (
  id UUID PRIMARY KEY,
  request_id UUID REFERENCES requests,
  scope_id VARCHAR, -- 'scope03', etc
  status VARCHAR, -- 'queued', 'running', 'completed', 'failed'
  created_at TIMESTAMP,
  started_at TIMESTAMP,
  completed_at TIMESTAMP
);

CREATE TABLE images (
  dbid INT PRIMARY KEY,
  request_id UUID REFERENCES requests,
  job_id UUID REFERENCES jobs,
  target_name VARCHAR,
  filter VARCHAR,
  exposure_seconds FLOAT,
  fits_path VARCHAR,
  ra FLOAT,
  dec FLOAT,
  metadata JSONB, -- custom FITS headers
  created_at TIMESTAMP
);
```

#### 5. Integration with Existing System
- [ ] Read job queue from star-server (SSH connection)
- [ ] Poll job status updates
- [ ] Map submitted requests to runner job format
- [ ] Parse completed job results back to database

### Phase 1 Success Criteria
- [ ] User can submit observation request
- [ ] Staff can see and approve requests
- [ ] Approved requests show up in runner job queue
- [ ] 3 requests can be submitted and approved without errors
- [ ] Dark mode works (dark toggle, proper contrast)
- [ ] Site matches Herts branding (logo, colors, fonts)
- [ ] API responds < 2 seconds for all endpoints
- [ ] Dark mode is the default for logged-in users (astronomy use case)

### Phase 1 Timeline
- **Week 1-2:** Infrastructure, database, FastAPI scaffold
- **Week 2-3:** Frontend scaffold, auth, request submission UI
- **Week 3-4:** Staff approval dashboard, job status integration
- **Week 5-6:** Testing, dark mode polish, deployment

---

## Phase 2: Image Archive (Weeks 7-14)

**Goal:** Build searchable FITS image browser with metadata and basic tools.

### Phase 2 Deliverables

#### 1. Image Metadata Indexing
- [ ] Sync all 100k+ images from /fits/ on star-server
- [ ] Extract FITS headers (RA, Dec, filter, exposure, date, observer, etc.)
- [ ] Index into PostgreSQL
- [ ] Cache thumbnails on web server (for fast load)
- [ ] Full-text search on image metadata

#### 2. Image Gallery UI
- [ ] Image browser with grid/list views
- [ ] Search by:
  - [ ] Target name (M13, NGC-1234, etc.)
  - [ ] Date range
  - [ ] Observer/user
  - [ ] Project
  - [ ] Filter (R, G, B, H-alpha, etc.)
  - [ ] Coordinates (RA/Dec range)
- [ ] Advanced search (astrometric data, magnitude, etc.)
- [ ] Image detail page (metadata, FITS headers, download)

#### 3. Image Tools (MVP)
- [ ] **Comparison view** — Load 2 images side-by-side, overlay, zoom
- [ ] **Download** — Single FITS or zip of results
- [ ] **Metadata display** — Show full FITS headers
- [ ] **Thumbnail preview** — Quick browse (generated at upload time)
- [ ] **Collections** — Save searches, mark favorites

#### 4. Performance Optimization
- [ ] Image metadata caching (Redis)
- [ ] Lazy-loading for large result sets
- [ ] Thumbnail CDN or local cache
- [ ] Query optimization for 100k+ images
- [ ] Pagination (50 images per page)

### Phase 2 Endpoints
```
GET    /api/images/search         - Search images
GET    /api/images/{dbid}         - Image details
GET    /api/images/{dbid}/fits    - Download FITS file
GET    /api/images/{dbid}/png     - Thumbnail
POST   /api/collections           - Save search
GET    /api/collections           - My saved searches
DELETE /api/collections/{id}      - Delete search
```

### Phase 2 Success Criteria
- [ ] Can search 100k images and get results < 1 second
- [ ] Image comparison view loads and renders smoothly
- [ ] Download single FITS works without errors
- [ ] Batch download (zip) < 30 seconds
- [ ] Mobile: can browse gallery on phone
- [ ] Dark mode applies to all image views (easy on eyes)
- [ ] Pagination handles large result sets

---

## Phase 3: Advanced Features & Optimization (Weeks 15-20)

**Goal:** Image analysis tools, staff operations, analytics.

### Phase 3 Deliverables

#### 1. Image Analysis Tools
- [ ] **Image Stacking** — Stack multiple FITS images (basic alignment + sum)
- [ ] **Photometry** — Plot brightness profile, detect stars
- [ ] **WCS Display** — Show coordinates on image (astrometric overlay)
- [ ] **Spectroscopy** (if applicable) — Wavelength calibration display

#### 2. Staff Operations Dashboard
- [ ] Real-time telescope status (from runner integration)
- [ ] Manual job triggers (pull_jobs, emergency shutdown)
- [ ] Error alerts and notifications
- [ ] Bandwidth/storage monitoring
- [ ] Audit logs (who did what, when)

#### 3. Analytics & Reporting
- [ ] Dashboard: images captured per month
- [ ] Success rate by telescope/observer
- [ ] Storage usage trends
- [ ] Common targets/projects
- [ ] User activity

#### 4. Maintenance & Hardening
- [ ] Rate limiting (prevent abuse)
- [ ] Backup strategy for 100k+ images
- [ ] Disaster recovery procedures
- [ ] Security audit (OWASP top 10)
- [ ] Performance tuning under load

### Phase 3 Success Criteria
- [ ] Image stacking produces valid FITS output
- [ ] Staff can trigger operations from web UI
- [ ] Site handles 500 concurrent users without slowdown
- [ ] Full image archive backed up off-site
- [ ] Security audit completed (no critical issues)

---

## Feature Backlog (Post-MVP Enhancements)

Feature requests gathered after the Phase 1 MVP + runner integration shipped.
Tracked in [`ROADMAP.md`](../../ROADMAP.md) at the repo root. Common theme:
**replicate the existing site's functionality, modernised** (React + FastAPI +
dark/redshift theming). Source PHP lives under
`Original Repo/backend/source/PHP files/`.

### Category A — Request Workflow Enhancements
Extends the Phase 1 request form / submission flow.

- **A1. Target catalogue auto-coordinates** — target name → RA/Dec autocomplete.
  Source ref: `targetcheck.php`. New `GET /api/catalogue/resolve`. Standalone. *(Medium)*
- **A2. Mandatory telescope field on request form** — observer picks the scope;
  drives `job.scope_id`. Required field (frontend + backend validation). *(High; depends on B1 for the list)*
- **A3. Exposure calculator** — port `expcalc.php` (per-filter flux tables) into a
  React component / `/api/expcalc`. *(Medium; optionally uses B1 camera params)*
- **A4. Per-telescope form constraints** — filters/declination-limits/min-binning
  on the form vary by selected telescope. *(Medium; depends on B1, A2)*

### Category B — Observatory Configuration (Admin)
- **B1. Telescope setup page (admin-editable)** — port `obssetup.php`. New
  `TelescopeConfig` model + admin CRUD UI (`/api/telescopes`). **Distinct from the
  `Scope` heartbeat table** (runtime status vs static config). Drives A2/A3/A4.
  *(High — foundational, unblocks Category A)*

### Category C — Observatory Status / Public Pages
- **C1. Weather graphics** — modernise `weather/graph.php`; ingest station data →
  `/api/weather` → modern charts. Ties into runner `weather_safety.py` + blueprint
  weather thresholds. *(Medium)*
- **C2. All-sky cameras** — modernise `allsky/index.php`. **Blocked:** obtain
  original source files and add to repo first. *(Low priority)*

**Suggested order:** B1 → A2 → A4/A3 → A1 → C1 → C2.

---

## Technology Stack Details

### Frontend
- **Framework:** React 18+ with TypeScript
- **Build:** Vite (faster than Create React App)
- **UI Library:** Material-UI or custom component library
- **Dark Mode:** CSS-in-JS (emotion/styled-components)
- **State:** Redux or Zustand (for global theme state)
- **API Client:** Axios with interceptors (auth, error handling)
- **Image Tools:** 
  - `astropy` (Python backend) for FITS processing
  - `plotly` for interactive charts
  - `canvas` API for image comparison overlays

**Dark Mode Implementation:**
```javascript
// All components use theme-aware colors
const theme = {
  light: {
    bg: '#ffffff',
    text: '#000000',
    border: '#cccccc'
  },
  dark: {
    bg: '#1a1a1a',  // Reduces eye strain at night
    text: '#f0f0f0',
    border: '#444444'
  }
};

// Set dark mode as default
const [theme, setTheme] = useContext(ThemeContext);
useEffect(() => {
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  setTheme(prefersDark ? 'dark' : 'light');
}, []);
```

### Backend
- **Framework:** FastAPI (Python 3.10+)
- **Database:** PostgreSQL 14+
- **Cache:** Redis (image metadata, session store)
- **Task Queue:** Celery (for long-running jobs like image stacking)
- **Web Server:** Gunicorn + Nginx
- **ORM:** SQLAlchemy
- **Image Processing:** Astropy, Pillow, Numpy
- **Authentication:** JWT tokens + university SSO (if available)

### Infrastructure
- **Containerization:** Docker Compose (dev), Docker Swarm/K8s (prod)
- **CI/CD:** GitHub Actions
- **Monitoring:** Prometheus + Grafana (optional, Phase 3)
- **Logging:** ELK stack or Loki (optional, Phase 3)
- **Storage:** Star-server NFS mount for /fits/

### Database
- **PostgreSQL** for relational data
- **Redis** for caching + sessions
- **Full-text search** using PostgreSQL capabilities
- **Backups:** Weekly dumps + replication

---

## User Personas & Workflows

### Persona 1: Observer (Researcher)
**Goal:** Submit observation requests, view results

**Workflow:**
1. Login to website
2. Create new request (specify target, filters, exposure)
3. Submit for approval
4. Wait for approval
5. Wait for observation to complete
6. Download FITS images from archive
7. Compare images, analyze data

**UI Requirements:**
- Simple request form
- Status tracking
- Image search/download
- Dark mode (optional use at night)

### Persona 2: Staff (Denis)
**Goal:** Approve requests, monitor operations, troubleshoot

**Workflow:**
1. Login with staff role
2. View pending approvals
3. Review request details (target, exposure, etc.)
4. Approve or reject
5. Monitor job queue (which scope is running what)
6. View live telescope status
7. Trigger manual operations (emergency shutdown)
8. Check error logs

**UI Requirements:**
- Dashboard with pending items
- Job queue with real-time status
- Telescope control panel
- Log viewer
- Dark mode (primary use at night in dome)

### Persona 3: Administrator
**Goal:** System configuration, user management, analytics

**Workflow:**
1. Manage user roles/permissions
2. View system health (storage, database)
3. Generate reports (monthly summary, success rates)
4. Configure weather thresholds
5. Manage integrations with star-server

**UI Requirements:**
- Admin panel (Phase 3)
- User management
- System monitoring
- Report generation

---

## Branding & Design System

### Colors (Matching herts.ac.uk)
Look at current university branding:
- **Primary:** Gold/amber (#D4AF37 or similar)
- **Secondary:** Dark blue (#1a3a52 or similar)
- **Accent:** Bright blue or green (check current site)
- **Light Mode:** White background, dark text
- **Dark Mode:** Dark gray/black (#1a1a1a or #0f0f0f), light text

### Typography
- **Headlines:** University-standard font (check herts.ac.uk)
- **Body:** Clean, readable sans-serif (Inter, Open Sans)
- **Monospace:** For FITS headers, coordinates (Courier New or JetBrains Mono)

### Layout
- **Header:** University logo + navigation (search, user menu)
- **Sidebar:** Filter/search on image gallery
- **Footer:** Copyright, contact, links
- **Responsive:** Stack on mobile, multi-column on desktop

### Dark Mode Palette
```
Background:  #0f0f0f (true black reduces eye strain)
Surface:     #1a1a1a (card backgrounds)
Text:        #e8e8e8 (light gray, not pure white)
Accent:      #ffd700 (gold, maintains branding)
Border:      #333333 (subtle dividers)
```

---

## Data Migration Strategy

### From Old System to New
1. **Week 1 (prep):**
   - Get all existing data from old database
   - Export user accounts
   - Document data mapping

2. **Week 6 (before Phase 1 launch):**
   - Dump all users, requests, images into PostgreSQL
   - Verify data integrity
   - Test queries on 100k+ images

3. **During Phase 2:**
   - Sync new images as they arrive
   - Keep dual systems running (old + new) for 2 weeks
   - Verify request/job flows match

4. **Cutover:**
   - Monday: Announce switchover window (4-6 hours)
   - Pause new jobs on old system
   - Final sync of any stragglers
   - Switch DNS to new site
   - Monitor for errors

---

## Risk Assessment & Mitigation

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|-----------|
| 100k image metadata sync slow | High | Medium | Phase 2 milestone, build indexing in advance |
| Dark mode accessibility issues | Medium | High | Early testing with real astronomers at night |
| Database query performance on 100k+ rows | Medium | High | Index planning from day 1, load test week 4 |
| University SSO integration complexity | Medium | Medium | Start with local auth, add SSO in Phase 2 |
| FITS file corruption during migration | Low | High | Verify checksums, maintain old system as backup |
| Request/job workflow mismatch with runner | Medium | High | Test integration early (week 3), dry-run before cutover |
| Concurrent user load on small team | Low | High | Load test with 100+ simulated users, caching strategy |
| Image stacking algorithm stability | Medium | Medium | Use proven astronomy libraries (astropy), test extensively |

---

## Definition of Done

### Code
- [ ] Passes all unit tests (>80% coverage)
- [ ] Passes integration tests (with real data)
- [ ] TypeScript strict mode, no `any` types
- [ ] Python: black/flake8/mypy all pass
- [ ] Code review from 1 peer before merge
- [ ] No console errors in browser

### Database
- [ ] Schema migration scripts exist and tested
- [ ] Backup before any schema change
- [ ] Indexes added for query performance
- [ ] Data integrity checks pass

### UI/UX
- [ ] Works on Chrome, Firefox, Safari (desktop)
- [ ] Responsive on mobile (375px+)
- [ ] Dark mode tested by actual astronomers
- [ ] Page load < 3 seconds (first paint)
- [ ] API responses < 2 seconds (95th percentile)

### Deployment
- [ ] Deploys via `git push` (GitHub Actions)
- [ ] Rollback plan documented
- [ ] Database backups automated
- [ ] Logs aggregated and searchable

### Documentation
- [ ] API docs auto-generated (FastAPI does this)
- [ ] README with setup instructions (< 10 minutes)
- [ ] Architecture diagram
- [ ] Troubleshooting guide
- [ ] Data model documented

---

## Phase Timeline (Detailed)

```
PHASE 1: Foundation (6 weeks)
├─ Week 1
│  ├─ Server setup, Docker, PostgreSQL
│  ├─ FastAPI scaffold, auth
│  └─ React scaffold, dark mode foundation
├─ Week 2
│  ├─ Request submission endpoint
│  ├─ Request form UI
│  └─ Database integration
├─ Week 3
│  ├─ Staff approval endpoints/UI
│  ├─ Job status integration
│  └─ First integration test (submit → approve → queue)
├─ Week 4
│  ├─ Branding implementation
│  ├─ Load testing (100 requests)
│  └─ Dark mode polish
├─ Week 5
│  ├─ Bug fixes from testing
│  ├─ Optimization (query, frontend)
│  └─ Security audit (basic)
└─ Week 6
   ├─ Data migration (user, request history)
   ├─ Staging environment test
   ├─ Documentation
   └─ Soft launch (staff only)

PHASE 2: Image Archive (8 weeks)
├─ Week 7-8
│  ├─ 100k image metadata sync
│  ├─ Thumbnail generation
│  └─ Full-text search setup
├─ Week 9-10
│  ├─ Image browser UI
│  ├─ Search interface
│  └─ Basic filtering
├─ Week 11-12
│  ├─ Image detail page
│  ├─ FITS header display
│  ├─ Download functionality
│  └─ Image comparison view
├─ Week 13-14
│  ├─ Performance tuning (500 concurrent users)
│  ├─ Dark mode for all image views
│  └─ Public launch prep

PHASE 3: Advanced (6 weeks)
├─ Week 15-16
│  ├─ Image stacking tool
│  ├─ Photometry display
│  └─ WCS overlay
├─ Week 17-18
│  ├─ Staff operations dashboard
│  ├─ Real-time alerts
│  └─ Audit logging
├─ Week 19-20
│  ├─ Analytics dashboard
│  ├─ Performance optimization
│  └─ Security hardening

Total: 20 weeks ≈ 4.5 months
(With 1 person, some parallelization possible if help arrives)
```

---

## Getting Started (Next Steps)

1. **This Week (Planning)**
   - [ ] Review this blueprint with stakeholders
   - [ ] Get server/infrastructure approval from IT
   - [ ] Collect existing website files (PHP backend, database dump)
   - [ ] Confirm Herts branding colors/fonts
   - [ ] Set up GitHub repository

2. **Week 1 (Infrastructure)**
   - [ ] Provision web server (or cloud VM)
   - [ ] Set up Docker dev environment
   - [ ] Create PostgreSQL database
   - [ ] Initialize FastAPI + React projects
   - [ ] Deploy to staging

3. **Week 2 (Features)**
   - [ ] Build request submission flow
   - [ ] Implement staff approval UI
   - [ ] Integration test with runner scripts

---

## Open Questions for Denis

1. **University SSO** — Does Herts have OIDC/SAML for federated login, or local auth only?
2. **Image Processing** — Should image stacking/photometry be done server-side (FastAPI) or client-side (JavaScript)?
3. **Notifications** — Should staff get email/Slack alerts for pending approvals?
4. **Mobile App** — Web-only, or also native iOS/Android app?
5. **Herts Branding** — Can you provide color/font files from the university design system?
6. **Server Location** — Cloud (AWS, Azure) or on-campus hardware?
7. **Budget** — Any constraints on third-party services (e.g., image CDN)?
8. **Team Help** — Will you be solo for 4 months, or can someone help with frontend/backend?

---

## Success Looks Like

**Month 1 (Phase 1 launch):**
- Staff can submit and approve observation requests via web UI
- Dark mode is fully functional (tested at night in dome)
- Branding matches university standards
- System is stable (no crashes)

**Month 2-3 (Phase 2 launch):**
- 100k+ images searchable and browsable
- Image comparison/analysis tools working
- Researchers can find and download data
- Performance stable under load

**Month 4 (Phase 3 + hardening):**
- Advanced image tools (stacking, photometry)
- Staff dashboard fully integrated
- System ready for 5-year production use
- Documentation complete, handoff ready

---

## Appendix: Existing PHP Backend Analysis

Run these commands to understand the current system:

```bash
# Get existing database schema
mysqldump -u [user] -p [database] --no-data > schema.sql

# Get data volume
mysql -u [user] -p -e "SELECT COUNT(*) FROM images; SELECT COUNT(*) FROM rtml;"

# Get file structure
find /www/bayfordbury -type f -name "*.php" | head -20
ls -lh /www/bayfordbury/automation/fits/ | head -20

# Check Apache/database config
cat /www/bayfordbury/config.php  # (watch for credentials)
```

These give us the baseline for data migration and feature mapping.

---

**Document Version:** 1.1  
**Last Updated:** 2026-06-16 (added post-MVP Feature Backlog: A1–A4, B1, C1–C2)  
**Next Review:** After Phase 1 completion (Week 6)
