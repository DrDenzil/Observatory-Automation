# Observatory Automation — Roadmap & Feature Backlog

Living document tracking feature requests beyond the Phase 1 MVP + runner
integration (both complete). Each item maps to a category in
[`segments/website/BLUEPRINT.md`](segments/website/BLUEPRINT.md).

Status legend: 🔵 Backlog · 🟡 In progress · 🟢 Done · ⚪ Blocked

All items share a common theme: **replicate the existing site's functionality but
modernise it** (React UI, FastAPI endpoints, dark/redshift theme support).

---

## Done

- 🟢 **Phase 1 MVP** — request submission → staff approval → job queue → dashboards
- 🟢 **Runner integration** — Go agent polls web API, drives EKOS, reports status live
- 🟢 **B1** — Telescope config page (admin CRUD) + seed data for CKT & PIRATE
- 🟢 **A2** — Mandatory telescope selection on request form; telescope_id stored on request
- 🟢 **A4** — Per-telescope filter and binning constraints on request form
- 🟢 **A1** — Target name autocomplete: 110 Messier + NGC + named stars, SIMBAD fallback

---

## Category A — Request Workflow Enhancements

- 🟢 **A3** — Exposure calculator ported from expcalc.php; camera DB, noise budget bar, three modes (calculate / find max / solve to SNR)

---

## Category C — Observatory Status / Public Pages

### C1. Weather graphics 🔵
- **Request:** Add weather graphs, with a nicer way to display the data than the
  current page.
- **Existing:** https://observatory.herts.ac.uk/weather/graph.php · runner-side
  safety logic in `Original Repo/runner/weather_safety.py`.
- **Modernise:** ingest weather station data → `/api/weather` → modern charts
  (e.g. a charting lib) on a public/staff page. Ties into the blueprint's weather
  thresholds and the automation runner's weather-safety hooks.
- **Priority:** Medium · **Depends on:** weather data source/feed

### C2. All-sky cameras 🔵
- **Request:** Add all-sky camera feeds at some point. **Low priority.**
- **Existing:** https://observatory.herts.ac.uk/allsky/index.php
- **Action needed:** ⚪ Obtain the original all-sky source files and add them to
  the repo before building (blocked on getting the files).
- **Modernise:** all-sky viewer page (latest frame + optional timelapse).
- **Priority:** Low · **Depends on:** source files

---

## Suggested sequencing

1. **B1** (telescope config) — foundational; unblocks the request-form work.
2. **A2** (mandatory telescope field) — small, high-value, completes job routing.
3. **A4** (per-telescope constraints) + **A3** (exposure calculator).
4. **A1** (target catalogue) — independent; slot in anytime.
5. **C1** (weather) then **C2** (all-sky, when source files are available).

Also still open from the core loop (not feature requests, but pending): FITS
image ingest back into the archive, real-KStars D-Bus validation, "Run Now"
trigger button, weather-safety park/abort.
