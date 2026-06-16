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
| run-continuous.sh | ~/.ekos-runner/ | Continuous operation loop (run.sh + auto-restart) |
| status-all.sh | ~/.ekos-runner/ | Detailed job queue status across all scopes |
| pull_jobs.sh | ~/.ekos-runner/ | Pull new jobs from star-server |
| ekos_runner.py | ~/.ekos-runner/ | Generate EKOS config files |
| load_scheduler.sh | ~/.ekos-runner/ | Load scheduler into KStars |
| push_jobs.sh | ~/.ekos-runner/ | Upload FITS to star-server |
| deploy.sh | ~/.ekos-runner/ | Deployment tool for telescope machines |

## Deployment

Use `deploy.sh` to set up a fresh telescope machine:

```bash
# Basic deployment
./deploy.sh --machine-id scope01

# Full deployment with cron automation
./deploy.sh --machine-id scope03 --install-cron

# With weather safety proxy
./deploy.sh --machine-id scope03 --install-weather

# With LX200GPS init script
./deploy.sh --machine-id scope05 --install-lx200gps

# With weather watchdog (emergency shutdown)
./deploy.sh --machine-id scope05 --install-watchdog

# With systemd timer (recommended for continuous operation)
./deploy.sh --machine-id scope03 --install-systemd
```

What `deploy.sh` does:
1. Installs dependencies (python3, rsync, sshpass, xdotool)
2. Optionally installs KStars/INDI natively (not Flatpak)
3. Creates `/var/lib/ekos-runner/jobs/scope{01-09}/` directory structure
4. Copies all runner scripts to `~/.ekos-runner/`
5. Sets up SSH key authentication for star-server
6. Optionally creates cron/systemd entries for automated processing

All deployed scripts live under a single directory: **`~/.ekos-runner/`**.

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

# Continuous runner (auto-restarts on completion, loops forever)
cd ~/.ekos-runner
./run-continuous.sh scope03

# Check job queue status across all scopes
cd ~/.ekos-runner
./status-all.sh

# Monitor scheduler status via D-Bus
dbus-send --session --dest=org.kde.kstars --print-reply /KStars/Ekos/Scheduler org.freedesktop.DBus.Properties.Get string:"org.kde.kstars.Ekos.Scheduler" string:"jsonJobs"
```

## Systemd Integration

The repo includes `ekos-runner.service` and `ekos-runner.timer` for continuous
operation via systemd (user mode). Deploy with `--install-systemd` flag:

```bash
# Install manually (if not using deploy.sh)
cp ekos-runner.service ekos-runner.timer ~/.config/systemd/user/
systemctl --user daemon-reload
systemctl --user enable --now ekos-runner.timer

# Check status
systemctl --user status ekos-runner.service
systemctl --user status ekos-runner.timer

# View logs
journalctl --user -u ekos-runner.service -f
```

The timer triggers the service periodically (configurable interval).
The service runs `run.sh` for the configured machine-id.

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

### 14. load_scheduler.sh - missing esac/done + ensure_kstars_running + GENERATED_PATH
**File**: `load_scheduler.sh`
**Change**: Added missing `esac` and `done` to close the arg-parsing while/case loop (bash syntax error). Added `ensure_kstars_running()` function (was called but never defined). Added `GENERATED_PATH` variable definition. Added "Stop scheduler before clearing" step (was documented in fix 3 but never implemented). Removed stray `fi`.

### 15. push_jobs.sh - SSH timeout
**File**: `push_jobs.sh`
**Change**: Added `ConnectTimeout=10` and `ServerAliveInterval=5` to SSH_OPTS to prevent SSH from hanging indefinitely when server is unreachable.

### 16. run.sh - completed_jobs counter + pending push detection
**File**: `run.sh`
**Change**: Fixed `completed_jobs` counter (was never incremented, always showed 0). Added `pending_push` detection for jobs with captures that haven't been pushed yet, preventing the monitoring loop from exiting prematurely.

### 17. deploy.sh - path inconsistency fix
**File**: `deploy.sh`
**Change**: Changed all `${HOME}/ekos-runner/` references to use `$INSTALL_DIR`
(`~/.ekos-runner/`). Core scripts were already deployed to `~/.ekos-runner/` but
helper scripts (`run.sh`, `status.sh`, `run-automation.sh`) and usage messages
still pointed to the old `~/ekos-runner/` path. Added `INSTALL_DIR` as a global
variable. Also added missing `install_cron` call to `main()` so `--install-cron`
actually runs during deployment.

### 18. run.sh - crash recovery for KStars/INDI + auto profile connection
**File**: `run.sh`
**Change**: Added full crash recovery with zero manual intervention.
1. `ensure_indi_running()` — checks `pgrep -x indiserver`, restarts with
   configurable `INDI_DRIVERS` (default: simulator + weather proxy)
2. `ensure_kstars_running()` — detects KStars D-Bus unavailability, kills stale
   KStars process, relaunches `kstars`, waits up to 30s for D-Bus registration
3. `ensure_ekos_connected()` — waits for `/KStars/Ekos` D-Bus path, calls
   `setProfile("Simulators")` then `connectDevices()` on the EKOS D-Bus
   interface, polls `indiStatus` property until it reaches 2 (Connected).
4. Idle+no-captures handler — when scheduler reports status 0/1 and no captures
   are pending, checks for unprocessed jobs in `generated/` and reloads them.
   This handles the case where KStars restarted mid-job and the scheduler is
   empty but jobs are still queued.
5. `ensure_ekos_connected` is also called during initial startup (Step 3) so
   the pipeline connects automatically from the very first run.
6. Configurable via env vars `INDI_DRIVERS`, `INDI_PORT`, and `EKOS_PROFILE`.
7. Notifications sent on crash detection via `notify.sh`.

**Scenarios now handled**:
- KStars crash mid-job → INDI restarted → KStars restarted → EKOS profile set →
  devices connected → scheduler reloaded → continues
- INDI crash while KStars stays up → INDI restarted → EKOS reconnected →
  continues
- First-time startup → INDI started → KStars connected → profile set →
  devices connected → pipeline runs

### 19. Production deployment hardening for scope09
**Files**: `deploy.sh`, `run.sh`, `scripts/notify.sh`, `weather_safety.py`, `pull_jobs.sh`, `ekos-runner.service`
**Change**: Prepared deployment path for real-world scope09 testing.
1. `notify.sh` is now safe to `source` and defines a reusable `notify()` function.
2. `deploy.sh` no longer overwrites the real monitored `run.sh` with a simplified helper.
3. `deploy.sh` initializes `INSTALL_SYSTEMD=false`, writes a machine-specific user service, and installs but does not enable/start the timer automatically.
4. `deploy.sh` creates `~/.ekos-runner/ekos-runner.env` for `EKOS_PROFILE`, `INDI_PORT`, and production `INDI_DRIVERS`.
5. `run.sh` only defaults to simulator drivers/profile for `scope03`; real scopes default to profile name matching the machine ID and require explicit `INDI_DRIVERS` for crash-recovery INDI restarts.
6. `weather_safety.py` uses proper Julian day calculation for sun altitude.
7. `pull_jobs.sh` SSH has connection/server-alive timeouts to avoid indefinite hangs.
8. Checked-in `ekos-runner.service` is scope09-safe and environment-file driven for manual installs.

**Deployment rule**: For scope09, run `./deploy.sh --machine-id scope09 --install-weather --install-watchdog --install-systemd`, edit `~/.ekos-runner/ekos-runner.env`, run `./run.sh scope09` supervised, then enable/start `ekos-runner.timer` only after hardware/weather behavior is confirmed.

### 20. Scope09 remote deployment test
**Date**: June 3, 2026
**Machine**: `astro@147.197.130.45` (`PAM-OBS-DAT-U`)
**Command tested**: `./deploy.sh --machine-id scope09 --skip-deps --install-weather --install-watchdog --install-systemd`
**Result**: Deployment completed after fixing watchdog systemd installation.

**Verified on remote**:
1. Runner scripts installed to `/home/astro/.ekos-runner/`.
2. Scope09 queue directories exist under `/var/lib/ekos-runner/jobs/scope09/`.
3. Star-server SSH key installed and `ssh star-server "echo STAR_OK"` works.
4. Weather script installed at `/usr/local/share/indi/scripts/weather_status.p`; `--check` returned unsafe during daytime (`Sun above horizon`) and humidity warning, which is expected/safe.
5. `indi_weather_safety_proxy` is present at `/usr/bin/indi_weather_safety_proxy`.
6. Dry-run checks passed: `pull_jobs.sh --dry-run`, `ekos_runner.py --dry-run`, `load_scheduler.sh --dry-run`, `push_jobs.sh --dry-run`.
7. `systemd-analyze verify` passed for user units.
8. Final state: `ekos-runner.timer`, `ekos-runner.service`, and `weather-watchdog.service` are disabled/inactive; no KStars/INDI/runner processes; no queue files or FITS captures for scope09.

**Fixes discovered during test**:
1. `install_watchdog()` wrongly used system-level `systemctl` for a user service. Fixed to install into `~/.config/systemd/user` and use `systemctl --user`.
2. `weather-watchdog.service` had `WantedBy=multi-user.target`; fixed to `WantedBy=default.target` for user systemd.
3. For safety, both watchdog and EKOS timer installers now install only and print explicit `enable/start` commands. They do not enable/start automatically.

**Important next step**: Fill real scope09 `INDI_DRIVERS` in `/home/astro/.ekos-runner/ekos-runner.env` and confirm the KStars/EKOS profile before running `./run.sh scope09` supervised.
**File**: `deploy.sh`
**Change**: Changed all `${HOME}/ekos-runner/` references to use `$INSTALL_DIR`
(`~/.ekos-runner/`). Core scripts were already deployed to `~/.ekos-runner/` but
helper scripts (`run.sh`, `status.sh`, `run-automation.sh`) and usage messages
still pointed to the old `~/ekos-runner/` path. Added `INSTALL_DIR` as a global
variable. Also added missing `install_cron` call to `main()` so `--install-cron`
actually runs during deployment.

## Deployment Requirements

New machines should use `deploy.sh` for setup (see Deployment section above).
It handles dependency installation, script copying, SSH key setup, and optional
cron/systemd integration automatically.

Manual dependencies (if deploying without deploy.sh):
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

### 6. ~/ekos-runner-stale/ archive cleanup
**Status**: Archived to `~/ekos-runner-stale/` on May 12, 2026.
**Next**: Can be fully removed once the deploy.sh fix is confirmed working.

### 7. Error notifications via webhook
**Purpose**: Notify on pipeline errors (not on success)
**Implementation**: 
- `runner/scripts/notify.sh` - Helper script
- Uses Bayfordbury API: `https://147.197.221.254/api/notification.php`
- API key: `9okEap1xDT2mVR3k`
- Calls from: `run.sh`, `load_scheduler.sh`, `push_jobs.sh`
**Notifications sent on**:
- Max wait time reached
- Scheduler errors
- Push job failures
**Date added**: May 1, 2026
**Status**: Infrastructure created but not working yet (needs debugging)

### 8. Continuous runner (VERIFIED May 12, 2026)
**Status**: Tested and working end-to-end with simulator

**Fixes applied during testing**:
1. **load_scheduler.sh**: Missing `esac`/`done` syntax error, missing `ensure_kstars_running()` function, missing `GENERATED_PATH` variable, missing "stop before clear" step, stray `fi`
2. **push_jobs.sh**: Added `ConnectTimeout=10` to SSH to prevent hangs
3. **run.sh**: Fixed `completed_jobs` counter, added `pending_push` detection for unpushed captures

**Verified outcomes**:
- Pipeline runs continuously without intervention ✓
- Multiple queued jobs processed in FIFO order ✓
- New jobs automatically loaded when previous completes ✓
- Images pushed with correct OBSERVER header ✓
- .pushed marker prevents reprocessing ✓
- If no jobs, sleeps 5 minutes then checks again ✓
- If error, retries on next cycle ✓

## Directory Structure

All deployed runner scripts live in a single directory:

```
~/.ekos-runner/              # Runner scripts (deploy target)
├── deploy.sh                # Deployment script (also in git repo)
├── run.sh                   # Manual single-cycle runner
├── run-continuous.sh        # Continuous operation loop
├── status-all.sh            # Queue status overview
├── pull_jobs.sh             # Download jobs from star-server
├── push_jobs.sh             # Upload FITS to star-server
├── ekos_runner.py           # Generate EKOS config from jobs
├── load_scheduler.sh        # Load jobs into KStars/EKOS
├── weather-watchdog.sh      # Weather safety watchdog
├── scripts/                 # Helper scripts (notify.sh, etc.)
└── ...                      # Other scripts (*.sh, *.py)
```

Job queue data:

```
/var/lib/ekos-runner/jobs/scope03/
├── incoming/       # New jobs from website (JSON)
├── claimed/        # Jobs currently being processed
├── completed/      # Processed job JSON
├── failed/         # Failed jobs
├── generated/      # EKOS config files (.esl, .esq)
│   └── */captures/ # Captured FITS images
└── logs/           # Runner logs
```

> **Note**: The old `~/ekos-runner/` (no dot, from March 2026) was stale and
> out of sync with the live deployment. It has been archived to
> `~/ekos-runner-stale/`. All current scripts live under `~/.ekos-runner/`.

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
