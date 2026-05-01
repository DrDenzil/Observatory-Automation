# Observatory Automation - Bayfordbury CKT

## Current Status (April 28, 2026)

The EKOS/Observatory-Automation pipeline is working end-to-end for the CKT (Telescope) at Bayfordbury Observatory.

## Pipeline Flow

```
Website (job submission)
    ↓
pull_jobs.sh (download from star-server to incoming/)
    ↓
ekos_runner.py (generate .esl/.esq files in generated/)
    ↓
load_scheduler.sh (load into KStars/EKOS + auto-start)
    ↓
EKOS Scheduler (capture images when conditions met)
    ↓
push_jobs.sh (upload FITS to star-server + trigger import.php)
    ↓
Repeat for next job in queue
```

## Key Scripts

| Script | Location | Purpose |
|--------|----------|---------|
| run.sh | ~/.ekos-runner/ | Main entry point - runs full pipeline |
| pull_jobs.sh | ~/.ekos-runner/ | Pull new jobs from star-server |
| ekos_runner.py | ~/.ekos-runner/ | Generate EKOS config files |
| load_scheduler.sh | ~/.ekos-runner/ | Load scheduler into KStars |
| push_jobs.sh | ~/.ekos-runner/ | Upload FITS to star-server |

## Running the Pipeline

```bash
# Full automated pipeline (runs completely unattended)
cd ~/.ekos-runner
nohup ./run.sh scope03 &

# The run.sh script now:
# 1. Pulls jobs from star-server
# 2. Generates EKOS config files
# 3. Loads scheduler and auto-starts
# 4. Monitors for job completion
# 5. Pushes captures automatically
# 6. Loads next job when current completes
# 7. Repeats until all jobs done

# Monitor job status
dbus-send --session --dest=org.kde.kstars --print-reply /KStars/Ekos/Scheduler org.freedesktop.DBus.Properties.Get string:"org.kde.kstars.Ekos.Scheduler" string:"jsonJobs"
```

## Simulator Mode (scope03)

The scope03 machine uses INDI simulator for testing. The system detects this from:
- Profile name contains "sim" or "test"
- Profile name is "scope03"
- Job project is "Simulator"
- Job has `simulator_mode: true`

When detected:
- **Startup**: ASAP (not time-based)
- **Constraints**: Empty (no EnforceTwilight, no MinimumAltitude)
- **Steps**: Track only (no Focus, Align, Guide)
- **Completion**: Sequence (run full sequence, not time-based)

This allows testing without waiting for dark skies.

## IMPORTANT FIXES APPLIED

### 1. Runner processes all jobs
**File**: `ekos_runner.py`
**Change**: Loop to process ALL pending jobs in incoming/, not just one

### 2. Load scheduler picks next pending job
**File**: `load_scheduler.sh`
**Change**: Picks oldest job that doesn't have captures yet (FIFO queue)

### 3. Stop scheduler before clearing
**File**: `load_scheduler.sh`
**Change**: Stop → Clear → Verify → Load (prevents stale state bug)

### 4. Auto-start scheduler after loading
**File**: `load_scheduler.sh`
**Change**: Starts scheduler automatically after loading job

### 5. Simulator detection
**File**: `ekos_runner.py`
**Change**: Detects simulator from profile="scope03" OR project="Simulator"

### 6. RA format fix
**File**: `ekos_runner.py`
**Change**: RA comes from website in degrees, EKOS expects hours → divide by 15

### 7. Auto-start scheduler after loading
**File**: `load_scheduler.sh`
**Change**: Fixed status check - verifies status 2 (running) with retry logic

### 8. Auto-load next job on completion
**File**: `run.sh`
**Change**: Monitors scheduler, pushes captures on job completion, loads next pending job

### 9. Track processed jobs with .pushed marker
**File**: `push_jobs.sh`, `load_scheduler.sh`
**Change**: Creates `.pushed` marker after successful upload; load_scheduler skips jobs with this marker

### 10. Fixed set -e issue in load_scheduler
**File**: `load_scheduler.sh`
**Change**: Changed `set -euo pipefail` to `set -uo pipefail` - the `-e` was causing early exit when checking pushed jobs

### 11. OBSERVER header injection
**File**: `push_jobs.sh`
**Change**: Reads submitted_by from completed job JSON and injects into FITS OBSERVER header (was missed before)

### 13. Import.php observer routing
**File**: `/www/bayfordbury/automation/control/fitsin/import.php` on star-server
**Change**: Fixed to use numeric OBSERVER header as observerid - routes files to correct user folder (e.g., /fits/1245/Simulator/)

## Deployment Requirements

New machines need these packages:
```bash
sudo apt-get install python3 rsync sshpass xdotool
```

Note: `xdotool` is not strictly required anymore but kept for debugging.

## Known Issues

### PWI4 not reporting coordinates to EKOS
**Symptoms**: Mount shows [0,0] in EKOS despite indi_planewave_telescope publishing correct RA/DEC
**Status**: Bug reported to KDE (see: https://bugs.kde.org)
**Workaround**: Use INDI simulator for now

## Future Work / TODOs

### 1. FITS viewer window cleanup
**Problem**: FITS viewer windows accumulate (xdotool is too risky, D-Bus clearFITS doesn't work)
**Options**: 
- Find KStars setting to disable FITS auto-display
- Use manual cleanup script (run periodically)
- Accept accumulation (low priority issue)

### 2. Continuous operation
**Problem**: Runner exits after processing one cycle
**Options**:
- Wrap run.sh in a loop with sleep
- Create systemd service/timer for continuous operation
- Add monitoring/alerting for pipeline failures

### 3. PWI4 hardware testing
**Problem**: PWI4 coordinate bug prevents real hardware use
**Status**: Waiting for KDE bug fix
**Next**: Test with real PWI4 mount once bug resolved

### 4. Pipeline monitoring
**Problem**: No alerting when pipeline fails
**Options**:
- Add email/webhook notifications on failure
- Create dashboard to monitor job status
- Log aggregation for multiple telescopes

### 5. Weather safety proxy sun calculation bug
**Symptoms**: Weather safety proxy reports sun altitude as ~18° when actual is ~45°
**Root cause**: Julian date calculation in `/usr/local/share/indi/scripts/weather_status.p` uses wrong formula (`dt.toordinal() - 1721424.5` instead of proper Julian day calculation)
**Fix**: Replace lines 52-57 with proper Julian date calculation:
```python
a = (14 - dt.month) // 12
y = dt.year + 4800 - a
m = dt.month + 12 * a - 3
jdn = dt.day + ((153 * m + 2) // 5) + 365 * y + y//4 - y//100 + y//400 - 32045
frac = (dt.hour + dt.minute/60 + dt.second/3600) / 24.0
jd = jdn + frac - 0.5
```
**Workaround**: `export SUN_SAFE=0` to disable sun check
**Date fixed**: May 1, 2026

## Directory Structure

```
/var/lib/ekos-runner/jobs/scope03/
├── incoming/       # New jobs from website (JSON)
├── claimed/        # Jobs currently being processed
├── completed/     # Processed job JSON
├── failed/         # Failed jobs
├── generated/     # EKOS config files (.esl, .esq)
│   └── */captures/  # Captured FITS images
└── logs/          # Runner logs
```

## Configuration Files

- **KStars profile**: `CKT` (in ~/.config/kstarsrc)
- **INDI server**: Port 7624
- **INDI drivers**: 
  - scope03 (simulator): indi_simulator_telescope + indi_simulator_ccd
  - CKT (PWI4): indi_planewave_telescope + indi_simulator_ccd

## Useful Commands

```bash
# Check incoming jobs
ls -la /var/lib/ekos-runner/jobs/scope03/incoming/

# Check generated jobs
ls -la /var/lib/ekos-runner/jobs/scope03/generated/

# Check captures
ls -la /var/lib/ekos-runner/jobs/scope03/generated/*/captures/

# Stop scheduler
dbus-send --session --dest=org.kde.kstars --print-reply /KStars/Ekos/Scheduler org.kde.kstars.Ekos.Scheduler.stop

# Force restart INDI
pkill -f indiserver
/usr/bin/indiserver -v -p 7624 -m 1024 -r 0 -f /tmp/indififo indi_simulator_telescope indi_simulator_ccd indi_weather_safety_proxy &
```

## Links

- Observatory-Automation repo: https://github.com/anomalyco/Observatory-Automation-ds
- KStars/EKOS: https://kstars.kde.org
- INDI Driver: https://www.indilib.org

## Notes

- The pipeline works end-to-end with simulator
- PWI4 integration has a KDE bug preventing coordinate reading in EKOS
- When multiple jobs submitted, they queue and process one at a time in FIFO order
- Use Simulator project for testing to skip constraints
- SSH user for star-server is `ds` (updated from `robotic`)
- SSH key: `~/.ssh/id_rsa_star`