# Observatory Automation - Deployment Reference

Complete breakdown of devices, responsibilities, and file locations after deployment.

---

## 1. Star-Server

**Purpose:** Central hub - web interface, database, file storage, job queue

### Connection
| Property | Value |
|----------|-------|
| Hostname | `star-server` |
| IP | `147.197.221.254` |
| SSH User | `ds` |
| SSH Key | `~/.ssh/id_rsa_star` |

### Directories (on star-server)

| Path | Purpose |
|------|---------|
| `/www/bayfordbury/` | Web root |
| `/www/bayfordbury/automation/jobs/` | Job queue |
| `/www/bayfordbury/automation/jobs/outgoing/{scope}/` | Pending jobs per scope |
| `/www/bayfordbury/automation/control/` | Control scripts |
| `/www/bayfordbury/automation/control/fitsin/` | FITS upload queue |
| `/www/bayfordbury/automation/control/fitsin/import.php` | FITS import script |
| `/www/bayfordbury/automation/fits/{user}/{project}/` | Processed images |
| `/www/bayfordbury/private/db.php` | Database credentials |

### Database (MySQL)

| Database | Tables | Purpose |
|----------|--------|---------|
| `bayfordbury_obs` | `rtml` | Job queue and status |
| | `images` | Image registry with DBID |
| | `users` | User accounts and levels |

### Web Interface

| URL | Purpose |
|-----|---------|
| `observatory.herts.ac.uk` | Main website |
| `observatory.herts.ac.uk/imagebrowser.php` | Image search |

### Processes

| Process | Purpose |
|---------|---------|
| Apache/PHP | Web server |
| `import.php` | Auto-runs when FITS uploaded to `fitsin/` |

---

## 2. Telescope Machines (Scope01-Scope09)

**Purpose:** Execute observations, capture images, monitor weather

### Connection Info

| Scope | IP | Hardware |
|-------|-----|----------|
| Scope01 | `147.197.130.x` | Standard config |
| Scope03 | `147.197.130.108` | DDW Dome + LX200GPS |
| Scope06 | `147.197.130.x` | Standard config |

### Directories (on scope machines)

| Path | Purpose |
|------|---------|
| `~/.ekos-runner/` | Automation scripts |
| `/var/lib/ekos-runner/jobs/{scope}/` | Job data |
| `/var/lib/ekos-runner/jobs/{scope}/incoming/` | Pulled jobs |
| `/var/lib/ekos-runner/jobs/{scope}/claimed/` | Jobs being processed |
| `/var/lib/ekos-runner/jobs/{scope}/completed/` | Finished jobs |
| `/var/lib/ekos-runner/jobs/{scope}/generated/` | EKOS files (.esq, .esl) |
| `/var/lib/ekos-runner/jobs/{scope}/captures/` | FITS images |
| `/var/lib/ekos-runner/jobs/{scope}/logs/` | Log files |

### Scripts (in `~/.ekos-runner/`)

| Script | Purpose |
|--------|---------|
| `pull_jobs.sh` | Download jobs from star-server |
| `ekos_runner.py` | Convert RTML to EKOS format |
| `push_jobs.sh` | Upload FITS to star-server |
| `load_scheduler.sh` | Load jobs into KStars |
| `emergency-shutdown.sh` | Emergency shutdown sequence |
| `weather-watchdog.sh` | Monitor weather, trigger shutdown |
| `weather_safety.py` | Fetch weather data |
| `lx200gps_init.py` | Initialize LX200GPS telescope |

### INDI Configuration (`~/.indi/`)

| Config File | Device |
|-------------|--------|
| `LX200 GPS_config.xml` | Telescope |
| `DDW Dome_config.xml` | Dome controller |
| `Weather Safety Proxy_config.xml` | Safety monitoring |
| `CCD Simulator_config.xml` | Camera |

### Systemd Services

| Service | Purpose |
|---------|---------|
| `weather-watchdog.service` | Weather monitoring daemon |

---

## 3. Weather Station

**Purpose:** Environmental monitoring for safety decisions

### Connection

| Property | Value |
|----------|-------|
| Host | `147.197.130.103` |
| Port | `7332` |
| Protocol | TCP socket |

### Protocol

**Request:**
```
$ws|$wg|$to|$ho|$rr|$fr|$sl\n
```

**Response:**
```
{wind_speed}|{wind_gust}|{temp}|{humidity}|{rain_rate}|{fog}|{sun_altitude}
```

### Data Fields

| Field | Unit | Description |
|-------|------|-------------|
| `$ws` | km/h | Wind speed |
| `$wg` | km/h | Wind gust |
| `$to` | °C | Temperature |
| `$ho` | % | Humidity |
| `$rr` | mm/h | Rain rate |
| `$fr` | bool | Fog present |
| `$sl` | degrees | Sun altitude |

---

## 4. Weather Safety Architecture

### Components

```
Weather Station (147.197.130.103:7332)
         │
         ▼
weather_safety.py (script)
         │
         ▼
Weather Safety Proxy (INDI driver)
         │
    ┌────┴────┐
    ▼         ▼
EKOS        weather-watchdog.sh
         │
         ▼
emergency-shutdown.sh
```

### Safety Thresholds

| Condition | Threshold | Action |
|-----------|-----------|--------|
| Sun altitude | > -10° | Dome stays closed |
| Rain rate | > 0.5 mm/h | Dome stays closed |
| Wind speed | > 50 km/h | Dome stays closed |
| Humidity | > 95% | Dome stays closed |
| Temperature | < -10°C or > 40°C | Dome stays closed |

### File Locations

| Component | Location |
|-----------|----------|
| Weather script | `/usr/local/share/indi/scripts/weather_status.p` |
| Script config | `Weather Safety Proxy_config.xml` |
| Polling interval | 60 seconds |

---

## 5. INDI Drivers

### Installed Drivers

| Driver | Executable | Device |
|--------|------------|--------|
| Telescope | `indi_lx200gps` | LX200GPS (Autostar II) |
| Dome | `indi_ddw_dome` | Digital Dome Works |
| Camera | `indi_ccd_simulator` | CCD Simulator |
| Weather | `indi_weather_safety_proxy` | Safety Proxy |

### LX200GPS Notes
- Cannot be unparked via INDI driver alone
- Requires `:I#` (Initialize) command after parking
- `lx200gps_init.py` handles this

### DDW Dome Notes
- Serial connection (9600 baud typical)
- Safety interlocks on loss of data from PC
- Does NOT natively snoop Weather Safety Proxy

---

## 6. KStars/EKOS

### Versions

| Component | Version |
|-----------|---------|
| KStars | 3.x (native, not Flatpak) |
| INDI Library | 2.1.9+ |

### D-Bus Interface

| Property | Value |
|----------|-------|
| Destination | `org.kde.kstars` |
| Scheduler Path | `/KStars/Ekos/Scheduler` |
| Method | `loadScheduler(string)` |

### Profile Editor

| Setting | Location |
|---------|----------|
| Equipment profiles | KStars → Ekos → Profile Editor |
| Pre-driver scripts | Script field above driver dropdown |
| Post-driver scripts | Script field below driver dropdown |

---

## 7. Job Processing Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                           STAR-SERVER                                 │
│  /automation/jobs/outgoing/scope03/ekos_6162_*.json                │
│         │                                                             │
└─────────┼─────────────────────────────────────────────────────────────┘
          │ pull_jobs.sh (rsync over SSH)
          ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        SCOPE MACHINE                                 │
│  /var/lib/ekos-runner/jobs/scope03/incoming/                        │
│         │                                                             │
│         ▼                                                             │
│  ┌──────────────┐                                                     │
│  │ekos_runner.py│ ──▶ /generated/ekos_6162/*.esq, *.esl             │
│  └──────────────┘                                                     │
│         │                                                             │
│         ▼                                                             │
│  ┌──────────────────────┐                                           │
│  │ load_scheduler.sh    │ ──▶ KStars via D-Bus                      │
│  └──────────────────────┘                                           │
│         │                                                             │
│         ▼                                                             │
│  ┌──────────────┐                                                     │
│  │ EKOS runs    │ ──▶ /captures/*.fits                              │
│  └──────────────┘                                                     │
│         │                                                             │
└─────────┼─────────────────────────────────────────────────────────────┘
          │ push_jobs.sh (rsync over SSH)
          ▼
┌─────────────────────────────────────────────────────────────────────┐
│                           STAR-SERVER                                 │
│  /control/fitsin/{dbid}.fit                                          │
│         │                                                             │
│         ▼                                                             │
│  import.php ──▶ /fits/{user}/{project}/*.fit                        │
│                        images table (DBID allocated)                  │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 8. Status Codes

### RTML Job Status

| Code | Status | Meaning |
|------|--------|---------|
| 0 | Not submitted | Draft |
| 1 | Pending review | Awaiting approval |
| 2 | Approved/Queued | Ready for telescope |
| 3 | Completed | Captured successfully |
| -1 | Cancelled | User cancelled |
| -2 | Failed | Rejected or error |

### Emergency Shutdown Types

| Type | Reason | Status Update |
|------|--------|---------------|
| WEATHER | Weather went unsafe | `INTERRUPTED_WEATHER` |
| WATCHDOG | Watchdog timeout | `INTERRUPTED_TIMEOUT` |
| NORMAL | End of session | `COMPLETED` |
| EMERGENCY | Manual abort | `INTERRUPTED` |

---

## 9. File Permissions

### Scope Machine

| Path | Owner | Permissions |
|------|-------|-------------|
| `~/.ekos-runner/` | astro:astro | 755 |
| `~/.ekos-runner/*.sh` | astro:astro | 755 (executable) |
| `/var/lib/ekos-runner/` | astro:astro | 755 |
| `/usr/local/share/indi/scripts/` | root:root | 755 |
| `/usr/local/share/indi/scripts/*.p` | root:astro | 755 (executable) |
| `/etc/xdg/systemd/user/` | root:root | 755 |

### Star-Server

| Path | Owner | Permissions |
|------|-------|-------------|
| `/www/bayfordbury/automation/` | www-data:www-data | 755 |
| `/www/bayfordbury/automation/jobs/` | ds:www-data | 775 |
| `/www/bayfordbury/automation/control/` | ds:www-data | 775 |

---

## 10. Deploy Commands

### Full Deployment (Scope03)
```bash
./deploy.sh --machine-id scope03 --install-weather --install-watchdog --install-lx200gps
```

### Individual Options
```bash
--install-weather    # Weather Safety Proxy + weather_safety.py
--install-watchdog   # weather-watchdog.sh + systemd service
--install-lx200gps   # lx200gps_init.py + wrapper
--install-cron       # Cron jobs for automation
```

### After Deploy - Start Watchdog
```bash
systemctl --user start weather-watchdog
systemctl --user status weather-watchdog
```

### View Logs
```bash
# Watchdog logs
journalctl --user -u weather-watchdog -f

# Shutdown logs
cat /var/log/observatory-shutdown.log

# Automation logs
cat /var/log/ekos-runner-scope03.log
```

---

## 11. Emergency Procedures

### Weather Goes Bad (Automatic)
1. `weather-watchdog.sh` detects 2 consecutive unsafe readings
2. Calls `emergency-shutdown.sh --weather`
3. Sequence: Abort scheduler → Park dome → Park telescope → Disable cooler
4. Job status updated in database

### Manual Emergency Shutdown
```bash
~/.ekos-runner/emergency-shutdown.sh --reason "Manual abort"
```

### Test Shutdown Sequence (Dry Run)
```bash
~/.ekos-runner/emergency-shutdown.sh --dry-run
```

### Stop Watchdog
```bash
systemctl --user stop weather-watchdog
```

---

## 12. Network Diagram

```
                            INTERNET
                               │
                               ▼
                    ┌──────────────────┐
                    │   observatory.   │
                    │   herts.ac.uk    │
                    └────────┬─────────┘
                             │
                    ┌────────▼─────────┐
                    │   Star-Server    │
                    │ 147.197.221.254  │
                    │  - Apache/PHP    │
                    │  - MySQL         │
                    │  - Job Queue     │
                    └────────┬─────────┘
                             │
         ┌───────────────────┼───────────────────┐
         │                   │                   │
         ▼                   ▼                   ▼
┌───────────────┐   ┌───────────────┐   ┌───────────────┐
│   Scope01     │   │   Scope03     │   │   Scope06     │
│ 147.197.130.x │   │ 147.197.130.  │   │ 147.197.130.x │
│               │   │     108       │   │               │
│  - KStars     │   │  - KStars     │   │  - KStars     │
│  - INDI       │   │  - INDI       │   │  - INDI       │
│  - DDW Dome   │   │  - DDW Dome   │   │               │
│  - LX200GPS   │   │  - LX200GPS   │   │               │
└───────────────┘   └───────┬───────┘   └───────────────┘
                             │
                    ┌────────▼─────────┐
                    │ Weather Station  │
                    │ 147.197.130.    │
                    │     103:7332    │
                    └─────────────────┘
```
