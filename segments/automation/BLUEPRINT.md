# Observatory Automation Runner - Complete Blueprint

**Project:** Modernize EKOS automation stack (9 telescope machines)  
**Timeline:** Phase 3 (Months 3-4, after website image archive complete)  
**Scale:** 9 telescope machines, continuous operation, sub-second reliability  
**Lead:** Denis  
**Status:** Blueprint (Ready to build)

---

## Executive Summary

The current automation runner is a loosely-coupled collection of bash scripts that works but is hard to monitor, debug, and maintain. This blueprint defines a complete rewrite in **Go** with:

- **REST API on each scope** — Web interface can query status, logs, trigger jobs
- **Smart auto-recovery** — Detect failure types (INDI crash vs network hiccup) and fix intelligently
- **Full audit logging** — Every action logged with timestamps for debugging
- **Infrastructure-as-code** — Ansible playbooks + Docker for reproducible deployment
- **No unattended surprises** — Clear visibility into every telescope's state

**What's being built:**
- **Go-based runner daemon** on each scope machine (replaces bash scripts)
- **REST API** exposing `/status`, `/logs`, `/jobs`, `/trigger` endpoints
- **Structured logging** (JSON logs for machine parsing)
- **Smart crash detection** (watchdog for KStars/INDI/Network)
- **Ansible deployment** (setup new scope in one command)
- **Integration with web interface** (Phase 1 website calls these APIs)

---

## Current State Analysis

### What Exists
- Bash scripts orchestrating observation pipeline
- Pull jobs (rsync from star-server)
- Process jobs (ekos_runner.py)
- Load into KStars (D-Bus)
- Monitor execution (polling KStars status)
- Push results (upload FITS, inject headers)
- Weather safety monitoring
- Emergency shutdown on errors

### Pain Points
1. **No Visibility** — Can't see what's running without SSH to each scope
2. **Fragile Crash Recovery** — When things die, recovery is guess-and-check
3. **Scattered Logging** — Logs in multiple places, hard to correlate
4. **Hard to Debug** — What went wrong? Check 5 different log files
5. **Deployment Complexity** — Setting up scope09 requires manual steps
6. **No Structured Communication** — Web interface can't query scope status reliably
7. **Configuration Hell** — Environment variables scattered across machines

### What We're Keeping
- Job format (JSON from star-server)
- FITS file format
- EKOS/KStars as the execution engine
- Star-server as job source
- Systemd timer concept

### What We're Replacing
- All bash scripts → Go daemon
- Manual crash recovery → Smart watchdog
- File-based logging → Structured JSON logs
- Direct SSH access → REST API
- Ad-hoc deployment → Ansible

---

## Architecture Overview

### System Components

```
┌─────────────────────────────────────────────────────┐
│         Website Interface (React)                   │
│  (Phase 1-2, uses REST APIs from runners)          │
└────────────────────┬────────────────────────────────┘
                     │
         ┌───────────┴───────────┬──────────────┐
         │                       │              │
    ┌────▼────┐            ┌────▼────┐    ┌───▼────┐
    │ Scope03  │            │ Scope05  │    │ Scope09 │  ...
    └────┬────┘            └────┬────┘    └───┬────┘
         │                       │              │
    ┌────▼──────────────┐   ┌────▼──────────────┐
    │  Go Runner Agent   │   │  Go Runner Agent   │
    │  (REST API)        │   │  (REST API)        │
    │  - /status         │   │  - /status         │
    │  - /logs           │   │  - /logs           │
    │  - /jobs           │   │  - /jobs           │
    │  - /trigger        │   │  - /trigger        │
    └────┬──────────────┘   └────┬──────────────┘
         │                       │
    ┌────▼──────────────┐   ┌────▼──────────────┐
    │  KStars/EKOS       │   │  KStars/EKOS       │
    │  INDI              │   │  INDI              │
    │  (orchestrates     │   │  (orchestrates     │
    │   observation)     │   │   observation)     │
    └────────────────────┘   └────────────────────┘
         │                       │
    ┌────▼──────────────┐   ┌────▼──────────────┐
    │  Hardware          │   │  Hardware          │
    │  - Telescope       │   │  - Telescope       │
    │  - Dome            │   │  - Dome            │
    │  - Camera          │   │  - Camera          │
    │  - Weather Station │   │  - Weather Station │
    └────────────────────┘   └────────────────────┘
         │
    ┌────▼──────────────────────┐
    │  Star-Server              │
    │  - Job Queue              │
    │  - FITS Storage           │
    │  - Database               │
    └───────────────────────────┘
```

### Key Design Decisions

**Why Go?**
- Fast, compiled language (no startup latency)
- Excellent concurrency (goroutines for multiple tasks)
- Single binary deployment (easy to ship to 9 machines)
- Good standard library (HTTP, JSON, logging)
- Better than bash for error handling and recovery logic

**REST API over Custom Protocol?**
- HTTP is universally understood
- Easy to debug (curl, browser)
- Web interface uses standard libraries
- Scales to adding new clients later

**Structured JSON Logging?**
- Parseable by machines (for dashboards/alerts)
- Timestamps, levels, context all included
- Works with ELK/Loki/CloudWatch if we scale up
- Way easier to debug than free-form text logs

**Ansible for Deployment?**
- Infrastructure-as-code (check into git)
- Idempotent (run twice = same result)
- Version controlled (track what changed when)
- Easy to add "new scope" to inventory, done

---

## Phase 3: Automation Modernization (Months 3-4, Weeks 15-20)

**Goal:** Transform loose shell scripts into a reliable, observable automation system.

### Phase 3 Deliverables

#### 1. Go Runner Agent (Core)

**Responsibilities:**
- Orchestrate full observation pipeline
- Communicate with KStars via D-Bus
- Fetch jobs from star-server
- Monitor execution and handle crashes
- Upload results
- Expose REST API for web interface

**Architecture:**
```
┌─ Main Loop (Timer-based)
│  Every 2 hours (or on-demand trigger):
│  ├─ Fetch next job from star-server
│  ├─ Start execution pipeline
│  └─ Monitor until complete
│
├─ HTTP Server (REST API)
│  ├─ GET  /status         → Current scope state
│  ├─ GET  /logs           → Audit log
│  ├─ GET  /jobs           → Job queue
│  ├─ POST /trigger        → Manual run
│  └─ POST /shutdown       → Emergency stop
│
├─ Watchdog Service
│  ├─ Monitor KStars process
│  ├─ Monitor INDI server
│  ├─ Detect crashes
│  └─ Smart recovery
│
└─ State Machine
   ├─ Idle
   ├─ Fetching
   ├─ Processing
   ├─ Executing
   ├─ Uploading
   ├─ Failed
   └─ Recovered
```

**Core Files:**
```
runner/
├── main.go                 # Entry point, config, main loop
├── server.go              # HTTP REST API
├── state.go               # State machine + events
├── kstars.go              # D-Bus communication
├── pipeline.go            # Pull → Process → Load → Monitor → Push
├── watchdog.go            # Process monitoring + recovery
├── logger.go              # Structured JSON logging
├── config.go              # YAML config loading
└── tests/
    ├── pipeline_test.go
    ├── watchdog_test.go
    └── kstars_test.go
```

**Key Features:**
- [ ] Fetch jobs from star-server (with retry logic)
- [ ] Convert jobs to EKOS format (call ekos_runner.py)
- [ ] Load into KStars via D-Bus
- [ ] Monitor execution (query KStars every 10s)
- [ ] Detect job completion
- [ ] Push results to server
- [ ] Log everything (structured JSON)
- [ ] Graceful shutdown on errors
- [ ] REST API for all operations

#### 2. REST API Specification

**Base URL:** `http://scope03:9090` (configurable port)

**Endpoints:**

```
GET /health
  → { "status": "ok", "uptime_seconds": 12345 }

GET /status
  → {
      "machine_id": "scope03",
      "state": "running",
      "current_job": "ekos_1234_20260616T150000Z",
      "progress": {
        "step": "executing",
        "step_description": "Capturing target M13 filter R",
        "elapsed_seconds": 450,
        "estimated_total_seconds": 600
      },
      "hardware": {
        "kstars_running": true,
        "indi_running": true,
        "network_connected": true
      },
      "last_activity": "2026-06-16T15:45:00Z"
    }

GET /logs?limit=100&level=info
  → [
      {
        "timestamp": "2026-06-16T15:45:00Z",
        "level": "info",
        "component": "pipeline",
        "message": "Starting job ekos_1234_20260616T150000Z",
        "context": { "target": "M13", "filters": ["R", "G", "B"] }
      },
      ...
    ]

GET /jobs?limit=10
  → {
      "queue": [...],         # Pending jobs
      "current": {...},       # Running job
      "completed": [...]      # Last 10 completed
    }

POST /trigger
  Body: { "scope_id": "scope03" }
  → { "status": "triggered", "job_id": "ekos_5678_..." }

POST /shutdown
  Body: { "reason": "weather" }
  → { "status": "shutting_down" }

POST /recovery
  Body: { "component": "indi" }
  → { "status": "recovering", "message": "INDI restarted" }
```

#### 3. Structured Logging

Every action generates a JSON log entry:

```json
{
  "timestamp": "2026-06-16T15:45:00.123Z",
  "level": "info",
  "component": "pipeline",
  "event": "job_started",
  "job_id": "ekos_1234_20260616T150000Z",
  "context": {
    "target": "M13",
    "filters": ["R", "G", "B"],
    "exposure_seconds": 5
  },
  "duration_ms": 245,
  "error": null
}
```

**Log Levels:** debug, info, warn, error, fatal

**Components:** pipeline, kstars, indi, watchdog, api, network

**Files:**
- `/var/log/ekos-runner/scope03.log` (all events)
- `/var/log/ekos-runner/scope03-errors.log` (errors only)
- Rotating (1GB per file, keep 10 files = 10GB history)

#### 4. Smart Watchdog & Auto-Recovery

Detects failure modes and applies targeted fixes:

```
Failure Mode          | Detection          | Recovery
──────────────────────┼────────────────────┼──────────────────────
KStars not running    | D-Bus unavailable  | Kill + restart
INDI not running      | Process check      | Kill + restart  
Network down          | SSH/rsync fails    | Retry + alert staff
KStars frozen         | No response 30s    | Kill + restart
INDI crashed          | Process missing    | Restart + reconnect
Job stuck (>timeout)  | No progress 1h     | Emergency shutdown + alert
Weather unsafe        | Weather API check  | Park + alert
```

**Example Watchdog Flow:**
```
1. Monitor KStars via D-Bus every 10 seconds
2. If no response for 30s → State = "kstars_unresponsive"
3. Log: "KStars D-Bus timeout, attempting restart"
4. Kill all KStars/INDI processes
5. Sleep 2 seconds
6. Start INDI server
7. Start KStars
8. Wait 30s for D-Bus registration
9. Check status
10. If OK → Resume job, log recovery success
11. If fail → Alert staff, wait for manual intervention
```

#### 5. Configuration System (YAML)

```yaml
# /etc/ekos-runner/scope03.conf
machine:
  id: scope03
  name: "CKT Telescope"
  timezone: UTC

kstars:
  profile: "CKT-LX200GPS"
  timeout_seconds: 30
  auto_start: true

indi:
  drivers:
    - "indi_lx200gps"
    - "indi_sbig_ccd"
  port: 7624
  auto_start: true

star_server:
  host: "star.herts.ac.uk"
  user: "ds"
  key_file: "/home/astro/.ssh/id_rsa_star"
  base_path: "/www/bayfordbury/automation"
  retry_max: 3
  retry_delay_seconds: 5

runner:
  mode: "timer"           # or "daemon"
  timer_interval: "2h"    # Run every 2 hours
  max_job_duration: "2h"  # Kill job if > 2h
  log_level: "info"
  api_port: 9090
  api_host: "0.0.0.0"    # Listening address

weather:
  enabled: true
  station_host: "147.197.130.103"
  station_port: 7332
  safety_thresholds:
    sun_altitude_degrees: -10
    rain_rate_mm_per_hour: 0.5
    wind_speed_kmh: 50
    humidity_percent: 95
    temp_min_celsius: -10
    temp_max_celsius: 40

watchdog:
  enabled: true
  check_interval_seconds: 10
  kstars_timeout_seconds: 30
  indi_timeout_seconds: 30
  job_timeout_minutes: 120

logging:
  level: "info"
  format: "json"
  file: "/var/log/ekos-runner/scope03.log"
  max_size_mb: 1000
  max_files: 10

notifications:
  enabled: true
  webhook_url: "https://example.com/alerts"
  on_events: ["error", "recovery", "job_complete"]
```

#### 6. Deployment with Ansible

```yaml
# ansible/site.yml
---
- hosts: telescopes
  vars:
    ekos_runner_version: "1.0.0"
    machine_id: "{{ inventory_hostname }}"
  roles:
    - common
    - ekos-runner
    - kstars
    - indi
    - weather-safety
    - systemd

# ansible/inventory.ini
[telescopes]
scope03 ansible_host=147.197.130.108 ekos_profile=CKT-LX200GPS
scope05 ansible_host=147.197.130.105 ekos_profile=Simulator
scope09 ansible_host=147.197.130.109 ekos_profile=Production

# Deploy all:
ansible-playbook site.yml

# Deploy only scope09:
ansible-playbook site.yml -l scope09

# Check what would change:
ansible-playbook site.yml --check
```

#### 7. Integration with Website

The website calls Go runner APIs:

```python
# Backend (FastAPI) calls runner APIs
import httpx

async def get_scope_status(scope_id: str) -> dict:
    """Get real-time status from Go runner on scope"""
    async with httpx.AsyncClient() as client:
        response = await client.get(
            f"http://{scope_id}:9090/status",
            timeout=2.0
        )
        return response.json()

async def trigger_observation(scope_id: str) -> dict:
    """Tell scope to start observing"""
    async with httpx.AsyncClient() as client:
        response = await client.post(
            f"http://{scope_id}:9090/trigger",
            json={"scope_id": scope_id},
            timeout=5.0
        )
        return response.json()

async def get_scope_logs(scope_id: str, limit=100) -> list:
    """Get audit logs from scope"""
    async with httpx.AsyncClient() as client:
        response = await client.get(
            f"http://{scope_id}:9090/logs?limit={limit}&level=info",
            timeout=2.0
        )
        return response.json()
```

### Phase 3 Success Criteria

- [ ] Go runner compiles and runs on all 9 scopes
- [ ] REST API returns correct status < 1 second
- [ ] Structured logs are valid JSON, parseable
- [ ] KStars crash → automatic restart within 30 seconds
- [ ] INDI crash → automatic restart + reconnect
- [ ] Network timeout → retry with backoff, alert after 3 attempts
- [ ] Can trigger observation from web UI
- [ ] Can view live logs from web UI
- [ ] Ansible playbook deploys new scope in < 5 minutes
- [ ] Configuration in YAML, version controlled, reviewed before deploy
- [ ] 30-day uptime on production (no crashes, only planned shutdowns)

### Phase 3 Timeline

```
Week 15-16: Go Runner Core
├─ Project setup, config system
├─ State machine + main loop
├─ D-Bus communication
└─ Pipeline orchestration

Week 17-18: REST API + Watchdog
├─ HTTP server implementation
├─ Watchdog service
├─ Auto-recovery logic
└─ Testing with manual failures

Week 19-20: Deployment + Integration
├─ Ansible playbooks
├─ Docker packaging
├─ Website integration (calls APIs)
├─ Logging aggregation
└─ Production testing (Scope03 first)
```

---

## Integration with Website (Phase 1-2)

The website immediately benefits from the Go runner APIs:

**Timeline:**
- **Phase 1 (Week 1-6):** Website uses existing bash scripts (fallback)
- **Phase 2 (Week 7-14):** Website ready to integrate with APIs once built
- **Phase 3 (Week 15-20):** Go runner APIs available, website switches to them

**Website → Runner Integration:**

```
Website Dashboard wants to know:
├─ GET /status → Current scope state (running/idle/error)
├─ GET /logs → Audit trail for debugging
├─ GET /jobs → What's queued, what's running
├─ POST /trigger → Staff clicks "Run Now"
└─ POST /shutdown → Emergency stop button

Staff Control Center:
├─ Real-time status for all 9 scopes
├─ Job queue overview
├─ Detailed logs by scope
├─ Trigger/stop operations
└─ Performance metrics (success rate, avg. job time)
```

---

## Migration Strategy (From Bash to Go)

### Phase 1: Parallel Run (Week 15-16)
- Deploy Go runner to Scope03 (dev machine)
- Keep bash scripts as fallback
- Test end-to-end (pull → process → execute → push)
- Verify results match bash version

### Phase 2: Gradual Rollout (Week 17-18)
- Deploy to Scope05 (non-critical)
- Monitor for 1 week
- Deploy to Scope01, Scope02, Scope04 (low-traffic)
- Monitor for 1 week

### Phase 3: Production Cut (Week 19-20)
- Deploy to Scope09, Scope06, Scope07, Scope08 (high-traffic)
- Maintain bash scripts as emergency fallback (30 days)
- Monitor 24/7 for week 1
- Gradually reduce monitoring

### Rollback Plan
If Go runner fails on production:
1. Switch back to bash (systemctl stop ekos-runner, start cron job)
2. Investigate on Scope03
3. Deploy fix
4. Gradual rollout again

---

## Technology Stack

### Go Runner
- **Language:** Go 1.21+
- **Build:** `go build -o ekos-runner main.go`
- **HTTP:** `net/http` (stdlib)
- **JSON:** `encoding/json` (stdlib)
- **Logging:** `github.com/sirupsen/logrus` (structured)
- **YAML:** `gopkg.in/yaml.v3`
- **Testing:** `testing` + `testify` (assertions)
- **Concurrency:** Goroutines (built-in)

### D-Bus Communication
- `go-dbus/dbus` library for KStars D-Bus calls
- Equivalent to bash `dbus-send` calls

### Deployment
- **Ansible** 2.12+
- **Docker** (optional, for containerized testing)
- **Systemd** (timer or daemon mode)

### External Dependencies
- `ekos_runner.py` (unchanged, called as subprocess)
- Star-server (SSH/rsync for jobs)
- KStars (via D-Bus, existing)
- INDI (existing)

---

## Error Handling & Edge Cases

| Scenario | Handling |
|----------|----------|
| Star-server unreachable | Retry with backoff, alert after 3 attempts, keep running last job |
| Job file corrupted | Log error, move to failed/, alert staff |
| KStars takes > 30s to start | Timeout, kill, retry once, then alert |
| INDI server won't connect | Try 3 times with 2s delay, then alert |
| Job runs > 2h (timeout) | Kill job, emergency shutdown, alert |
| Captures never appear | After 1h with no progress, kill job, alert |
| Network hiccup during push | Retry with exponential backoff, keep local copies |
| Multiple runners on same scope | Use file lock to prevent concurrent execution |
| Config file invalid YAML | Log error, use defaults, alert |
| Disk full (no space for logs) | Stop logging, alert, keep running |

---

## Monitoring & Observability

### Metrics to Track
- Job success rate (%)
- Average job duration (seconds)
- KStars uptime (%)
- INDI uptime (%)
- Network connectivity (%)
- Error rate (errors per day)
- Recovery time (time to restart after crash)

### Dashboards
- Grafana: Real-time metrics across all 9 scopes
- Loki: Centralized logging + search
- Health check: `/health` endpoint for monitoring

### Alerting
```yaml
alerts:
  - name: "High Error Rate"
    condition: "errors_per_hour > 5"
    action: "notify_staff"
  
  - name: "Scope Offline"
    condition: "last_activity > 3h"
    action: "notify_staff"
  
  - name: "Job Timeout"
    condition: "job_duration > 2h"
    action: "emergency_shutdown + notify_staff"
```

---

## Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|-----------|
| Go runner crashes under load | Low | High | Thorough testing, circuit breakers, graceful degradation |
| D-Bus API changes in KStars | Low | Medium | Vendor KStars version, test on updates |
| YAML config syntax errors | Medium | Medium | Validate on startup, clear error messages |
| Ansible playbook breaks | Medium | Medium | Test playbook on all scope types, dry-run before apply |
| Network partition | Medium | Low | Retry logic + alert, keep running locally |
| Disk full on scope | Low | High | Monitor disk, alert when > 80%, log rotation |
| Go binary incompatibility | Low | Medium | Build on target OS, or use Docker |

---

## Success Looks Like

**Month 1 (Week 15-16):**
- Go runner builds and runs on Scope03
- Can view real-time status from web interface
- Logs are structured JSON

**Month 2 (Week 17-18):**
- Go runner deployed to 5 test scopes
- Running observations successfully for 1 week
- Auto-recovery works (crash → restart → resume)

**Month 3-4 (Week 19-20):**
- All 9 scopes running Go runner in production
- Staff using web interface to monitor/control
- Zero unplanned downtime (only maintenance)
- Bash scripts retired after 30-day proving period

---

## Future Enhancements (Not in Phase 3)

1. **Metrics Export** — Prometheus-compatible metrics endpoint
2. **Event Streaming** — Kafka/Redis Pub/Sub for real-time dashboard updates
3. **Advanced Scheduling** — Intelligently balance jobs across scopes
4. **Machine Learning** — Predict job duration, detect anomalies
5. **Mobile App** — iOS/Android for on-the-go monitoring
6. **Multi-Observatory** — Support Bayfordbury + other observatories
7. **Cost Tracking** — Per-project billing based on telescope usage

---

## Questions for Denis

1. **Go Expertise** — Is Go available/familiar for maintenance? (If not, Python might be safer)
2. **YAML Validation** — Do you want a web UI for config editing, or keep it git-based?
3. **Metrics Collection** — Should we collect Prometheus metrics? (adds ~20% complexity)
4. **Docker** — Should each scope run in a container, or binary on host?
5. **Backup Go Runner** — If Go runner crashes, fall back to bash automatically?
6. **Email Alerts** — Should alerts send email, Slack, webhook, or all three?

---

**Document Version:** 1.0  
**Last Updated:** 2026-06-16  
**Next Review:** After Phase 3 completion (Week 20)
