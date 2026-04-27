# Observatory Automation - Bayfordbury CKT

## Current Status (April 27, 2026)

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
# Full pipeline (pull → generate → load → wait for captures)
cd ~/.ekos-runner
./run.sh scope03

# After captures complete, push results (normally automatic)
./push_jobs.sh --machine-id scope03

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

## Known Issues

### PWI4 not reporting coordinates to EKOS
**Symptoms**: Mount shows [0,0] in EKOS despite indi_planewave_telescope publishing correct RA/DEC
**Status**: Bug reported to KDE (see: https://bugs.kde.org)
**Workaround**: Use INDI simulator for now

### Multiple jobs queue detection
**Status**: Being debugged - properly detecting which jobs already have captures

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