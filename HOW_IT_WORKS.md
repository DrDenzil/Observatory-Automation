# EKOS Observatory Automation - How It Works

A simple guide to how images get from the telescope to the website.

---

## Overview

```
RTML Plan → Website → Star Server → Telescope → Images → Website Database
```

---

## Stage 1: Submit Request

**What:** Student uploads RTML plan via website

**Where:** observatory.herts.ac.uk

**Result:** Plan saved, awaiting staff approval

---

## Stage 2: Approval

**What:** Staff member reviews and approves the plan (level 10+ required)

**Where:** Website admin panel

**Result:** Approval recorded, plan queued for telescope

---

## Stage 3: Job Created

**What:** PHP script creates job JSON file

**Where:** Star server at `/automation/jobs/outgoing/scopeXX/`

**File:** `ekos_{id}_{timestamp}.json`

**Contains:** Target coordinates, exposure settings, observer info

---

## Stage 4: Pull Job

**What:** Telescope client pulls job from server

**Script:** `pull_jobs.sh`

**Where:** Downloads to `/var/lib/ekos-runner/jobs/{scope}/incoming/`

---

## Stage 5: Safety Check

**What:** Weather and sun safety verified before opening dome

**Script:** `weather_safety.py` (via INDI Weather Safety Proxy)

**Checks:**
| Condition | Threshold | Action |
|-----------|-----------|--------|
| Sun altitude | > -10° | Dome stays closed |
| Rain rate | > 0.5 mm/h | Dome stays closed |
| Wind speed | > 50 km/h | Dome stays closed |
| Humidity | > 95% | Dome stays closed |
| Temperature | < -10°C or > 40°C | Dome stays closed |
| Station safe_level | > 0 | Dome stays closed |

**Solar telescope override:** Set `SUN_SAFE=0` to disable sun check

---

## Stage 6: Process Job

**What:** Python converts job to EKOS format

**Script:** `ekos_runner.py`

**Creates:**
- `.esq` file - Sequence instructions
- `.esl` file - Scheduler configuration
- `manifest.json` - Job metadata

---

## Stage 7: Capture Images

**What:** EKOS/KStars takes the photos (only if safety checks pass)

**Where:** Saved to `/generated/{queue_ref}/captures/`

**Files:** `{target}_{filter}_{exposure}s_B{binning}_T{telescope}.fits`

---

## Stage 8: Push Images

**What:** Uploads images back to server

**Script:** `push_jobs.sh`

**Upload:** SSH to star server as `{dbid}.fit`

**Where:** `/control/fitsin/`

---

## Stage 9: Import

**What:** PHP script processes uploaded images (auto-triggered by push_jobs.sh)

**Script:** `import.php` (runs on server automatically after upload)

**What it does:**
1. Reads FITS header metadata
2. Allocates DBID from database
3. Registers image in database
4. Moves file to permanent location

**Final location:** `/fits/{observer_id}/{project_name}/`

---

## Stage 10: View Online

**What:** Image appears on website

**Where:** observatory.herts.ac.uk/imagebrowser.php

**Searchable by:** DBID, target name, coordinates, filter, date

---

## File Locations

| Item | Path |
|------|------|
| Jobs (server) | `/automation/jobs/outgoing/scopeXX/` |
| Jobs (telescope) | `/var/lib/ekos-runner/jobs/scopeXX/` |
| Captures (telescope) | `/var/lib/ekos-runner/jobs/scopeXX/generated/` |
| Import queue | `/control/fitsin/` |
| Processed images | `/fits/{user_id}/{project}/` |
| Image database | MySQL `images` table |

---

## Safety Systems

### Weather Safety
- INDI Weather Safety Proxy polls `weather_safety.py`
- Connects to weather station at 147.197.130.103:7332
- Feeds safety status to EKOS

### Sun Safety
- Calculated from observatory coordinates (Bayfordbury: 51.77°N, 0.10°E)
- Dome won't open if sun altitude > -10°
- Can be disabled with `SUN_SAFE=0` for solar telescope

### Manual Override
- Available in EKOS toolbar
- Use with caution

### LX200GPS Initialization
- LX200GPS (Autostar II) telescopes cannot be unparked via INDI driver
- Must send `:I#` (Initialize) command to reset telescope after parking
- `lx200gps_init.py` sends this command via serial port
- Called automatically by `load_scheduler.sh` before starting scheduler
- Use `--no-init` flag if telescope doesn't need initialization (non-LX200GPS scopes)

---

## User Access

| Name | User ID | Access Level |
|------|---------|--------------|
| Sam Rolfe | 223 | Level 10 (admin) |
| Denis Smith | 1245 | Level 10 (admin) |
| Vaishnav | 681 | Standard |

Images saved to `/fits/223/` for Sam, etc.

---

## DBID

- **DBID** = unique image ID in database
- Starts from 1, now at ~112,500
- Image file named: `{target}_{filter}_{exp}s_B{bin}_T{tel}_{dbid}.fits`
- Used to find images via website search

---

## Scripts

| Script | Purpose |
|--------|---------|
| `pull_jobs.sh` | Pull jobs from server |
| `ekos_runner.py` | Convert job to EKOS format |
| `push_jobs.sh` | Upload captures to server + trigger import.php |
| `load_scheduler.sh` | Load job into KStars via D-Bus |
| `weather_safety.py` | Fetch weather and sun data for safety |
| `lx200gps_init.py` | Initialize LX200GPS telescope via serial |
| `update_rtml_status.php` | Update job status in database |

---

## Deployment

Run on telescope machine:
```bash
./deploy.sh --machine-id scope01 --install-weather --install-lx200gps --install-cron
```

This installs:
- All automation scripts
- KStars (native, removes Flatpak)
- INDI with Weather Safety Proxy
- Weather script and config
- LX200GPS initialization script (for LX200GPS scopes)
- Cron jobs for automated processing

Options:
- `--install-weather` - Weather safety system
- `--install-lx200gps` - LX200GPS initialization
- `--install-cron` - Automated processing

---

## Notes

- Scripts run every 2 hours via systemd/cron
- Logs saved to `/var/log/ekos-runner-{scope}.log`
- All communication via SSH with key authentication
- Weather station: 147.197.130.103:7332
