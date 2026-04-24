# Observatory Automation - Key Info

## Project Location
`/home/astro/Observatory-Automation/` (main repo with .git)

## Current Test Machine (Scope03)
- **Hostname:** PAM-OBS-PMB08-U
- **IP:** 147.197.130.127
- **KStars:** Native install (bleeding edge branch, not Flatpak)
- **Scripts:** `~/.ekos-runner/`

## Directory Structure
```
/home/astro/
├── Observatory-Automation/     ← Main repo (git-controlled)
│   ├── runner/                 ← All automation scripts
│   ├── HOW_IT_WORKS.md         ← Process documentation
│   ├── deployment/             ← Deployment docs
│   └── source/                 ← Source code
├── ekos-jobs/                  ← Job data (scope01, scope03)
├── ekos-test/                  ← Test environment
└── .ekos-runner/              ← (can delete - empty)
```

## Key People
- **Denis** (ds) - Main contact, server admin
- User IDs: 223 (Denis), 1245 (approver), etc.

## Server Info
- **star-server** (147.197.221.254) - Main observatory server
- SSH key: `~/.ssh/id_rsa_star`
- Jobs path: `/www/bayfordbury/automation/jobs`
- Database credentials: `/www/bayfordbury/private/db.php`
- PHP 8.0 on server

## Telscope Scopes
- scope01-09 (scope03 = CKT telescope)
- SSH access via star-server

## Job Flow (Working)
```
pull_jobs.sh  →  ekos_runner.py  →  push_jobs.sh
     ↓                ↓                 ↓
  outgoing/        process job       incoming/
  scopeXX/             │             captures/
                      │                   │
                ┌─────┴─────┐             │
                │ pull_jobs │             │
                │ push_jobs │◀────────────┘
                └───────────┘
```

## Scripts Created

### runner/pull_jobs.sh
- Pulls jobs from star-server to local
- Moves pulled files to `sent/` on server
- Options: `--machine-id`, `--local`, `--dry-run`, `--password`

### runner/ekos_runner.py  
- Processes ALL jobs in incoming folder (not just one)
- Generates individual .esq/.esl files per job
- Creates combined_scheduler.esl with all jobs (EKOS handles prioritization)
- Adds project/student info to FITS filenames: `{project}_{target}_{filter}_{exposure}_{timestamp}.fits`
- Creates manifest.json with capture tracking info
- Options: `--machine-id`, `--queue-root`, `--dry-run`

### runner/push_jobs.sh
- Pushes status + FITS to star-server
- Calls `load_scheduler.sh` to load into KStars
- Calls `update_rtml_status.php` after push
- Options: `--machine-id`, `--status-only`, `--fits-only`, `--dry-run`, `--local`

### runner/load_scheduler.sh
- Auto-starts KStars if not running
- Loads combined_scheduler.esl (or individual .esl files)
- Options: `--machine-id`, `--queue-root`, `--dry-run`, `--no-start`

### runner/update_rtml_status.php
- Updates MySQL rtml table status
- Updates `control/queue/{scope}.dat` files
- Usage: `php update_rtml_status.php --set-status pending --job-file /path/to/job.json`
- Called from pull_jobs.sh (pending) and push_jobs.sh (completed)

## Status Codes
- 0 = Not submitted
- 1 = Pending review
- 2 = Approved/Queued
- 3 = Completed (new, for EKOS)
- -1 = Cancelled
- -2 = Not approved / Failed

## .dat File Format
```
timestamp<br>
PRJ_{rtmlid}|userid_projectname|Robotic|plans|images|seconds|completed|pending|deferred|failed|disabled<br>
PLN_{planid}|target|status|plans|images|seconds
```

## LX200GPS Initialization (2026-03-26)

### Problem
LX200GPS (Autostar II) telescopes cannot be unparked via INDI driver alone.

### Solution
- `lx200gps_init.py` - Sends `:I#` (Initialize) command via serial
- Configured in **EKOS Profile Editor** as **pre-driver script**

### Setup
1. Deploy: `./deploy.sh --machine-id scopeXX --install-lx200gps`
2. In EKOS Profile Editor, add pre-driver script: `/usr/local/share/indi/scripts/lx200gps_wrapper.sh`
3. Wrapper auto-detects FTDI/USB serial ports

### Serial Port Detection
Wrapper script checks: `/dev/serial/by-path/*`, `/dev/serial/by-id/*`, then `/dev/ttyUSB0/1`, `/dev/ttyACM0`

## Scope03 Info (CKT Telescope)
- IP: 147.197.130.108
- User: astro
- DDW Dome connected
- LX200GPS telescope
- Weather Safety Proxy configured

## Outstanding Issues

### myqueue.php Integration (BLOCKED)
- `myqueue.php` expects project names in format `userid_projectname`
- EKOS jobs use `ekos_{rtmlid}_{timestamp}` format
- myqueue.php crashes on PHP 8 with string timestamp (needs int cast)
- **Options**: Modify myqueue.php, create new page, or use hybrid approach

### Queue Ref Format
- Queue refs: `ekos_6162_20260319T204242Z`
- RTML ID extracted via regex: `preg_match('/^ekos_(\d+)_/', $queueRef, $matches)`

## Weather Safety System (2026-03-26)

### Components
- `weather_safety.py` - Fetches weather from station 147.197.130.103:7332
- `weather_status.p` - Installed to `/usr/local/share/indi/scripts/weather_status.p`
- `INDI Weather Safety Proxy` - Polls weather script every 60s

### Safety Thresholds
| Condition | Threshold | Action |
|-----------|-----------|--------|
| Sun altitude | > -10° | Dome stays closed |
| Rain rate | > 0.5 mm/h | Dome stays closed |
| Wind speed | > 50 km/h | Dome stays closed |
| Humidity | > 95% | Dome stays closed |
| Temperature | < -10°C or > 40°C | Dome stays closed |

### Sun Override
Set `SUN_SAFE=0` environment variable to disable sun check (for solar telescope)

## Emergency Shutdown System (2026-03-26)

### Scripts
- `emergency-shutdown.sh` - Unified shutdown for all equipment
- `weather-watchdog.sh` - Monitors weather, triggers shutdown if unsafe

### Shutdown Sequence
1. Abort EKOS scheduler
2. Park dome (close shutter)
3. Park telescope
4. Disable cooler/heaters
5. Update job status in database
6. Log event

### Watchdog Behavior
- Checks every 30 seconds
- Requires 2 consecutive unsafe readings before shutdown
- 5-minute cooldown between shutdowns
- Runs as systemd service

### Deploy Options
```bash
./deploy.sh --machine-id scope03 --install-weather --install-watchdog
```

### Image Tracking (Implemented)

**FITS Filename Format:**
```
{project_code}_{target}_{filter}_{exposure}_{timestamp}.fits
Example: denis_test_m13_R_5.0_20260324T165547.fits
```

**Project Code Format:**
```
u{userid}_{project_name_abbreviated}
Example: u223_DENTES (from user 223, project "Denis Test")
```

**Capture Manifest (manifest.json):**
```json
{
  "queue_ref": "ekos_6163_...",
  "rtml_id": 6163,
  "submitted_by": 223,
  "project": "Denis Test",
  "project_code": "u223_DENTES",
  "captures": {
    "directory": "/path/to/captures",
    "expected_files": [...],
    "captured_files": [...],
    "captured_at": null
  }
}
```

**Tracking Flow:**
1. Job pulled from server
2. ekos_runner creates manifest with expected captures
3. FITS saved with project-coded filenames
4. push_jobs uploads FITS + manifest
5. Server can link captures to project/student via manifest

### Coordinate Format (Fixed 2026-03-25)
- Input JSON provides RA in **degrees** (e.g., 250.42 for M13)
- EKOS scheduler expects RA in **hours**
- Fix: `RA_hours = RA_degrees / 15`
- Added `convert_ra_to_hours()` and `convert_dec_to_degrees()` functions

### FITS File Organization (Implemented 2026-03-25)
- push_jobs.sh now organizes FITS into server structure
- Target folder: `/www/bayfordbury/automation/fits/{observerid}/{project_name}/`
- Filename format: `{target}_{filter}_{exposure}s_B{binning}_T{telescope}.fit`
- Example: `M13_R_5.0s_B1_T1.fit`
- Reads metadata from manifest.json (submitted_by, project)
- Renames files to match existing server convention

## Files to Watch
- `runner/WORKFLOW.md` - Setup documentation
- `source/PHP files/myqueue.php` - Queue display (needs fixing)
- `source/PHP files/myrtml.php` - RTML status display
- `source/PHP files/ekosjobsubmit.php` - Job submission

## Last Session (2026-03-24)
- Built complete job automation pipeline
- SSH key setup for star-server
- Tested pull/run/push on scope03 job 6162
- myqueue.php integration not working - deferred

## Session (2026-03-25 - Full Day)

### User Reference
| Name | User ID |
|------|---------|
| Sam Rolfe | 223 |
| Denis Smith | 1245 |
| Vaishnav Babu | 681 |

### Completed Tasks

#### 1. Multiple Jobs Processing
- **ekos_runner.py** now processes ALL jobs in incoming folder (not just one)
- Creates `combined_scheduler.esl` with all jobs for EKOS prioritization
- Individual `.esq`/`.esl` files created per job

#### 2. Image Tracking (Implemented)
- **FITS Filename Format:** `{project}_{target}_{filter}_{exposure}_{timestamp}.fits`
- **Project Code:** `u{userid}_{project_abbreviated}` e.g., `u223_DENTES`
- **Capture Manifest:** `manifest.json` with expected captures list

#### 3. Coordinate Fix
- Input RA in **degrees** (250.42 for M13)
- EKOS expects RA in **hours** (16.69h)
- Added `convert_ra_to_hours()` and `convert_dec_to_degrees()` functions

#### 4. FITS Organization - Full Pipeline
**Old flow:** Upload to `/fits/{user}/{project}/` with custom naming
**New flow:** Tap into existing `import.php` system

New push_jobs.sh flow:
1. Get next DBID from server database
2. Upload to `/control/fitsin/{dbid}.fit`
3. `import.php` processes: extracts metadata, registers in DB, organizes files

#### 5. PHP Fixes (Deployed to Server)
- **ekosjobsubmit.php:** Fixed `submitted_by` to use `$owner_userid` (not `$userid`)
- **rtmlconfirm.php:** Fixed `approval.submitted_by` to use approver's ID

Before fix: `submitted_by` showed whoever queued the job (wrong)
After fix: `submitted_by` shows original RTML uploader (correct)

#### 6. load_scheduler.sh Enhancement
- Added auto-start KStars if not running
- Added `--no-start` option
- Added KSTAR_TIMEOUT environment variable (default 30s)

#### 7. Created HOW_IT_WORKS.md
Simple guide explaining the 9-stage process from RTML submission to image viewing

### Scripts Updated Today

| Script | Changes |
|--------|---------|
| `ekos_runner.py` | Process all jobs, combined scheduler, RA conversion, project codes |
| `push_jobs.sh` | Fitsin upload, DBID allocation, import.php integration |
| `load_scheduler.sh` | Auto-start KStars, timeout handling |
| `ekosjobsubmit.php` | Fixed submitted_by (deployed to server) |
| `rtmlconfirm.php` | Fixed approval.submitted_by (deployed to server) |

### Files Created
- `HOW_IT_WORKS.md` - Simple process documentation
- `DEPLOYMENT.md` - Full deployment guide
- `load_scheduler.sh` - D-Bus scheduler loader

### import.php Integration
The existing server import system at `/control/fitsin/import.php`:
1. Reads FITS from `fitsin/` folder as `{dbid}.fit`
2. Extracts metadata from FITS headers
3. Registers in `images` table with unique DBID
4. Moves file to `/fits/{observerid}/{project}/{target}_{filter}_{exp}s_B{bin}_T{tel}_{dbid}.fit`

Our push_jobs.sh now uploads to this folder, letting the existing system handle DBID allocation and organization.

### KStars Integration
- KStars via native install (bleeding edge branch, not Flatpak)
- D-Bus: `org.kde.kstars` at `/KStars/Ekos/Scheduler`
- `loadScheduler()` method works correctly
- Combined scheduler loads all jobs into EKOS

### Test Results
- Job 6165: `submitted_by: 1245` (Denis) ✓
- FITS uploaded to `fitsin/112523.fit` ✓
- import.php ran and processed ✓

### load_scheduler.sh (Updated)

Location: `runner/load_scheduler.sh`

Features:
- Auto-starts KStars if not running (native install)
- Waits up to 30s for D-Bus interface (configurable via KSTAR_TIMEOUT)
- Loads .esl files into KStars scheduler
- Verifies jobs are loaded

Options:
- `--machine-id` - Machine ID (default: scope06)
- `--queue-root` - Job directory (default: /var/lib/ekos-runner/jobs)
- `--dry-run` - Preview without loading
- `--no-start` - Don't auto-start KStars

Usage:
```bash
./load_scheduler.sh --machine-id scope01
./load_scheduler.sh --machine-id scope01 --queue-root /home/ekos/ekos-jobs
```

### Test Results
- Scheduler file loading: WORKING
- Job appears in KStars scheduler: WORKING
- Sequence file reference: `file:///tmp/ekos-test/test_sequence.esq`

### Files Created/Modified
```
runner/DEPLOYMENT.md                  # NEW - Full deployment guide
runner/load_scheduler.sh              # NEW - D-Bus scheduler loader
runner/ekos-pull.timer.example       # Updated - 2h interval
runner/ekos-runner.timer.example    # Updated - 2h interval
runner/push_jobs.sh                  # Updated - calls load_scheduler
runner/DEPLOYMENT.md                 # Updated - flatpak + D-Bus docs
```

### Outstanding Issues
1. **myqueue.php** - Still blocked, format mismatch
2. **Flatpak path** - Must use home directory for generated files

## Scripts Summary (as of 2026-03-26)

| Script | Purpose |
|--------|---------|
| `pull_jobs.sh` | Pull jobs from star-server |
| `ekos_runner.py` | Convert job to EKOS format, handle priority |
| `push_jobs.sh` | Upload captures + trigger import.php |
| `load_scheduler.sh` | Load scheduler into KStars |
| `weather_safety.py` | Fetch weather + sun altitude |
| `emergency-shutdown.sh` | Emergency shutdown (dome, mount, cooler) |
| `weather-watchdog.sh` | Monitor weather, trigger shutdown |
| `lx200gps_init.py` | Initialize LX200GPS telescope |
| `update_rtml_status.php` | Update job status in database |

## Session (2026-04-22) - import.php Fixes + FITS Header Injection

### The Problem
When images were captured and pushed, the import.php script failed to parse them correctly:
- FITS files not being read (wrong path - getFilename vs getPathname)
- RA/DEC parsing errors (array bounds when input is decimal degrees)
- Missing metadata (project, observerid came through as "Unknown" or "0")
- GUID duplicate errors (guid was '0' instead of RTML ID)

### Root Causes
1. **File path issue**: `getFilename()` returns just "123456.fit", but script ran from wrong directory
2. **Coordinate format**: EKOS outputs RA/DEC in decimal degrees, but script expected "HH MM SS.ss" format
3. **Empty metadata**: FITS files didn't have project/observer headers - needed injection
4. **GUID collision**: Using '0' caused duplicate key errors in database

### Fixes Applied

#### 1. Server - import.php (/www/bayfordbury/automation/control/fitsin/import.php)
- Fixed file path: `$file->getPathname()` instead of `getFilename()`
- Added `basename()` for DBID extraction
- Added `intval()` for DBID
- Fixed RA parsing with bounds checking (falls back to decimal if not HH MM SS format)
- Fixed DEC parsing with bounds checking
- Set guid default to dbid: `$guid=$dbid;`
- Added USERID header parsing for observerid
- Added fallback: if observername is numeric, use it for observerid
- Made project/target/plan default to "Unknown" instead of empty string

#### 2. Local - push_jobs.sh (runner/push_jobs.sh)
- Added FITS header injection function using astropy:
  - GUID: From queue_ref (ekos_6174 -> 6174)
  - USERID: From submitted_by (1245)
  - OBSERVER: From submitted_by for observername fallback
  - PRJNAME: From project
  - PLNNAME: From target name
  - PRJID: From guid
- Fixed project lookup: reads from manifest.json first, falls back to .meta.json
- Fixed submitted_by lookup: reads from .meta.json (has submitted_by, manifest may not)
- Fixed local variable scope issues (removed "local" keywords in for loops)

### How the Fixed Pipeline Works
```
1. pull_jobs.sh        → Downloads job from server
2. ekos_runner.py      → Converts to EKOS format, creates manifest.json
3. push_jobs.sh        → Injects FITS headers BEFORE upload:
                         - Reads project from manifest.json
                         - Reads submitted_by from .meta.json
                         - Uses astropy to add headers to FITS
                         - Uploads to fitsin/ as {dbid}.fit
4. import.php (server)→ Parses FITS headers:
                         - Reads USERID for observerid
                         - Reads OBSERVER for observername  
                         - Reads PRJNAME for project
                         - Reads GUID for guid
                         - Saves to /fits/{observerid}/{project}/
```

### Test Results (2026-04-22)
```
dbid: 112523
guid: 6174 ✓ (from queue_ref)
project: CDK-Real_world ✓ ✓ ✓
observerid: 1245 ✓ ✓ ✓
observername: 1245 ✓ ✓ ✓
ra: 151.75940 ✓
dec: 0.06513 ✓

File: /www/bayfordbury/automation/fits/1245/CDK-Real_world/0_0_1s_B4_T0_112523.fit
```

### Files Modified
- `runner/push_jobs.sh` - Added header injection, fixed project/submitted_by lookup
- `/www/bayfordbury/automation/control/fitsin/import.php` - Multiple fixes for parsing
- `/www/bayfordbury/automation/control/fitsin/import.php.bak` - Backup of original

### Key Learnings
- FITS header names must be ≤8 chars (USERID not OBSERVERID)
- import.php runs from fitsin/ directory - use getPathname()
- manifest.json may not have submitted_by - check .meta.json
- Observer ID comes from submitted_by in .meta.json
- GUID should be RTML ID to avoid database collisions
