# Bayfordbury Observatory - EKOS Automation System

A friendly guide for telescope techies.

**Last Updated:** April 2026
**For:** All automated telescopes
**Repo:** github.com/DrDenzil/Observatory-Automation

---

## What This System Does

This automation system keeps our robotic telescopes running without someone having to sit there all night pressing buttons.

The process:

1. Someone submits an observation request through our website
2. Staff (Denis, Sam, etc) review and approve it
3. The job gets shipped off to whichever telescope is next in line
4. Scripts fetch the job, convert it for KStars, and load it into the scheduler
5. KStars takes the pictures (but only if the weather's playing nice)
6. Once done, images get uploaded back to the server

The whole thing runs automatically once you've got it set up. Means we can sleep at night instead of freezing in the dome.

---

## The Hardware - What We're Working With

We've got 9 telescope scopes (scope01 through scope09). Each one's its own machine on the network, and they all chat to a central server.

### Star Server
- **Hostname:** star.herts.ac.uk (also called star-server in the scripts)
- **IP:** 147.197.221.254

The brains of the operation. Holds:
- The job queue (what jobs need doing)
- The website (where people submit requests)
- The database (all the image info)
- The image storage (the actual pretty pictures)

**Access:** SSH as user 'robotic' using the key at `~/.ssh/id_rsa_star`

**Key paths:**
- Web stuff: `/www/bayfordbury/`
- Job queue: `/www/bayfordbury/automation/jobs/`
- Where the images live: `/www/bayfordbury/automation/fits/`

### Scope03 (Development Machine)
- **Hostname:** PAM-OBS-PMB08-U (currently running as scope03)
- **IP:** 147.197.130.127
- **User:** astro (sudo if needed)
- **What's running:** KStars native (bleeding edge branch), not Flatpak

This is our development and testing playground. When we break things, it's good to know we've got a safe space to do it.

### Scope03 (The CKT Telescope)
- **IP:** 147.197.130.108

The special one. It's got:
- LX200GPS telescope (also known as Autostar II - a bit temperamental)
- DDW dome controller
- Weather Safety Proxy configured

### Weather Station
- **IP:** 147.197.130.103
- **Port:** 7332

This keeps an eye on:
- Temperature
- Humidity
- Wind speed
- Rain rate
- Where the sun is (important - don't point the camera at it!)

If the weather's rubbish, this tells the system to keep the dome firmly shut.

---

## Getting It All Set Up

### What You Need First

Before you dive in, make sure you've got:
- Ubuntu 24.04 or similar (scripts are bash, so Linux really)
- Python 3.8 or newer (the runner needs it)
- SSH key access to star-server
- KStars installed

### Getting the Code

The code lives on GitHub:
```bash
git clone https://github.com/DrDenzil/Observatory-Automation.git
```

On this machine, the main code is in `/home/astro/Observatory-Automation/`

But for running the automation day-to-day, we copy it to `~/.ekos-runner/`

The important scripts:
- `~/.ekos-runner/run.sh` - Does everything in one go
- `~/.ekos-runner/pull_jobs.sh` - Fetches jobs from server
- `~/.ekos-runner/ekos_runner.py` - Converts jobs to EKOS format
- `~/.ekos-runner/push_jobs.sh` - Sends captured images back
- `~/.ekos-runner/load_scheduler.sh` - Loads jobs into KStars

### Setting Up a New Machine

Got a fresh telescope machine? Here's what to do:

1. Get the code on there (Git, rsync, USB stick...)
2. Run the deploy script:
```bash
./runner/deploy.sh --machine-id scope01
```

Useful flags:
- `--machine-id scope01` - Essential, tells it which telescope
- `--install-weather` - Adds weather safety
- `--install-lx200gps` - For LX200GPS telescopes like scope03
- `--install-watchdog` - Continuous weather monitoring
- `--install-cron` - Runs automation every 2 hours

Example for a full setup:
```bash
./runner/deploy.sh --machine-id scope03 --install-weather --install-lx200gps
```

### KStars - The Install Question

Note about scope03 - it's running KStars from a native install on the bleeding edge branch, NOT from Flatpak.

Why?
- Bleeding edge gets all the shiny new EKOS features
- Native plays much nicer with D-Bus (needed for automation)
- No Flatpak sandbox headaches

To install yourself:
```bash
sudo add-apt-repository ppa:mutlaqja/ppa
sudo apt update
sudo apt install kstars indi-full
```

Or stick with Flatpak if you prefer - both work, you just might need to tweak the start command in load_scheduler.sh.

---

## The Scripts Explained

### pull_jobs.sh - The Fetcher

The first step in our automation dance. Logs into the server and checks if there are any jobs waiting for this telescope.

What it does:
1. Logs into star-server via SSH
2. Has a look in the outgoing queue for jobs destined for this scope
3. Grabs any it finds and saves them locally
4. Marks them as "sent" on the server so they don't get pulled again

How to use:
```bash
./pull_jobs.sh --machine-id scope03
./pull_jobs.sh --machine-id scope03 --dry-run   # just shows what'd happen
```

Paths:
- Server: `/www/bayfordbury/automation/jobs/outgoing/scope03/`
- Local: `/var/lib/ekos-runner/jobs/scope03/incoming/`

### ekos_runner.py - The Translator

This is where the real magic happens. Converts job JSON to EKOS format.

What it does:
1. Reads JSON job files from incoming/
2. Converts coordinates (RA comes in degrees from website, EKOS wants hours: RA_hours = RA_degrees / 15)
3. Creates .esq files (sequence instructions)
4. Creates .esl files (scheduler list)
5. Creates manifest.json for tracking

How to use:
```bash
./ekos_runner.py --machine-id scope03
./ekos_runner.py --machine-id scope03 --dry-run
```

Output: `/var/lib/ekos-runner/jobs/scope03/generated/`

### load_scheduler.sh - The Connector

Bridge between automation and KStars. Uses D-Bus to talk to KStars.

What it does:
1. Checks if KStars is running via D-Bus
2. If not running, starts KStars (unless you say --no-start)
3. Waits up to 30 seconds for KStars to get its D-Bus act together
4. Sends the .esl file to EKOS using loadScheduler method
5. Double-checks jobs actually got loaded

D-Bus details:
- Destination: org.kde.kstars
- Path: /KStars/Ekos/Scheduler
- Method: loadScheduler(string file_path)

How to use:
```bash
./load_scheduler.sh --machine-id scope03
./load_scheduler.sh --machine-id scope03 --no-start   # don't start KStars
./load_scheduler.sh --machine-id scope03 --dry-run    # preview mode
```

Set KSTAR_TIMEOUT=60 to wait longer if KStars is slow.

### push_jobs.sh - The Uploader

Images have been captured. Now we need to get them back to the server.

What it does:
1. Finds all .fit files in captures/
2. Gets the next available DBID from server
3. Injects FITS headers (GUID, USERID, OBSERVER, PRJNAME, PLNNAME, PRJID)
4. Uploads each FITS to fitsin/ as {dbid}.fit
5. Triggers import.php which reads headers and registers in DB
6. Moves files to final home in /fits/{user}/{project}/
7. Updates job status in database

How to use:
```bash
./push_jobs.sh --machine-id scope03
./push_jobs.sh --machine-id scope03 --dry-run
```

Paths:
- Local: `/var/lib/ekos-runner/jobs/scope03/generated/*/captures/`
- Upload: `/www/bayfordbury/automation/control/fitsin/`
- Final: `/www/bayfordbury/automation/fits/{user}/{project}/`

### lx200gps_init.py - Telescope Reset

Some telescopes (LX200GPS like scope03) can be stubborn. After parking, they need a special command to start responding again.

The issue is you can't just unpark them through INDI driver - they need the :I# (Initialize) command via serial port.

How to use:
```bash
python3 lx200gps_init.py --port /dev/ttyUSB0
python3 lx200gps_init.py --port /dev/ttyUSB0 --check   # just check
```

Set up in EKOS Profile Editor as pre-driver script.

### emergency-shutdown.sh - The "Oh Crap" Button

The one you hope you never need but are glad it's there when you do.

What it does:
1. Stops EKOS scheduler immediately
2. Parks the dome (closes shutter)
3. Parks the telescope
4. Turns off cooler/heaters
5. Updates job status in database
6. Logs what happened

How to use:
```bash
~/.ekos-runner/emergency-shutdown.sh
~/.ekos-runner/emergency-shutdown.sh --reason "Heavy rain"
~/.ekos-runner/emergency-shutdown.sh --weather   # called by watchdog
~/.ekos-runner/emergency-shutdown.sh --normal    # normal end of night
```

### weather-watchdog.sh - The Paranoid Guardian

Runs in background, keeps eye on weather. If unsafe, calls emergency-shutdown.sh automatically.

What it does:
1. Checks weather every 30 seconds
2. Needs 2 bad readings before acting (avoids panic on glitches)
3. Has 5-minute cooldown between shutdowns

**Safety limits:**
- Sun altitude: > -10° = dome closed
- Rain rate: > 0.5 mm/h = dome closed
- Wind speed: > 50 km/h = dome closed
- Humidity: > 95% = dome closed
- Temperature: < -10°C or > 40°C = dome closed

Can run as systemd service for automatic start on boot.

---

## How to Run It

### Quick Way (Does Everything)

```bash
cd ~/.ekos-runner
./run.sh scope03
```

Runs pull_jobs -> ekos_runner -> load_scheduler -> push_jobs all in one go.

### Manual Way (Step by Step)

```bash
cd ~/.ekos-runner

# Get the jobs from server
./pull_jobs.sh --machine-id scope03

# Convert to EKOS format
./ekos_runner.py --machine-id scope03

# Load into KStars
./load_scheduler.sh --machine-id scope03

# ... make a cup of tea while KStars does its thing ...

# Upload the results when done
./push_jobs.sh --machine-id scope03
```

### Checking What's Going On

```bash
./status.sh scope03
```

Or manually:
```bash
ls /var/lib/ekos-runner/jobs/scope03/incoming/
ls /var/lib/ekos-runner/jobs/scope03/generated/
ls /var/lib/ekos-runner/jobs/scope03/captures/
```

Where the logs live:
- `/var/log/ekos-runner-scope03.log` - main log
- `/var/lib/ekos-runner/jobs/scope03/logs/` - job logs

---

## Directory Structure

### Local (Telescope)
```
/var/lib/ekos-runner/jobs/scope03/
├── incoming/        # Jobs from server (JSON)
├── claimed/          # Jobs being worked on
├── generated/       # EKOS scheduler files (.esq, .esl)
│   └── {queue_ref}/
│       └── captures/    # FITS images
├── completed/       # Jobs finished
├── failed/          # Jobs failed
├── logs/             # Log files
└── captures/       # Old location, might not be used
```

### Server
```
/www/bayfordbury/automation/jobs/
├── outgoing/scope03/   # Waiting to be pulled
└── sent/               # Already pulled

/www/bayfordbury/automation/fits/
└── {observer_id}/
    └── {project}/
        └── {target}_{filter}_{exp}s_B{bin}_T{tel}_{dbid}.fits
```

---

## The Files

### Job File (JSON) - What Comes In

```json
{
  "queue_ref": "ekos_6163_20260324T165547Z",
  "rtml_id": 6163,
  "job": {
    "submitted_by": 223,
    "project": "Denis Test",
    "plans": [
      {
        "plan_id": "PLN_001",
        "project": "Denis Test",
        "schedule": {"priority": 1},
        "targets": [
          {
            "name": "M13",
            "ra": 250.42,   // degrees, not hours!
            "dec": 28.38,
            "filters": ["R", "G", "B"],
            "exposure": 5.0,
            "count": 3
          }
        ]
      }
    ]
  }
}
```

### EKOS Sequence (.esq)

XML file telling KStars exactly what to do - exposure, filters, counts.

### EKOS Scheduler List (.esl)

Simple text file listing each target and when to image.

### FITS Filename - What Goes Out

Format: `{project}_{target}_{filter}_{exposure}_{timestamp}.fits`
Example: `u223_DENTES_M13_R_5.0_20260324T165547.fits`

Project code: `u{userid}_{project_abbreviated}`
So: `u223_DENTES` = user 223's project "Denis Test"

---

## The Database

The server runs MySQL.

### Key Tables
- **images** - Every image captured
- **rtml** - Job queue and status
- **users** - User accounts

DBID - every image gets a unique ID (around 112,500 now).

### Status Codes (rtml table)
- 0 = Not submitted yet
- 1 = Waiting for approval
- 2 = Approved and queued
- 3 = Completed
- -1 = Cancelled
- -2 = Failed

---

## Things Go Wrong - Troubleshooting

### 1. No Jobs Appearing (pull_jobs runs but incoming/ is empty)

Likely:
- There just aren't any jobs (boring but true)
- SSH's not working

Check:
```bash
ssh robotic@star-server ls /www/bayfordbury/automation/jobs/outgoing/scope03/
ssh -i ~/.ssh/id_rsa_star robotic@star-server echo OK
```

### 2. KStars Won't Play Ball (D-Bus error)

Likely:
- KStars not running or not responding

Fix:
```bash
ps aux | grep kstars
kstars &
dbus-send --session --dest=org.kde.kstars --print-reply /KStars/Ekos/Scheduler org.freedesktop.DBus.Introspectable.Introspect
```

Set KSTAR_TIMEOUT higher if slow to start.

### 3. Images Don't Get Uploaded

Likely:
- SSH issues, wrong paths, import.php didn't run

Check:
```bash
ls /var/lib/ekos-runner/jobs/scope03/generated/*/captures/*.fit
ssh robotic@star-server ls /www/bayfordbury/automation/control/fitsin/
ssh robotic@star-server ls /www/bayfordbury/automation/fits/
```

### 4. Weather Safety Won't Let Anything Run

Likely:
- Weather station not responding or thresholds need tweaking

Check:
```bash
python3 /usr/local/share/indi/scripts/weather_status.py
```

Thresholds might need adjusting for your site conditions.

### 5. LX200GPS Won't Respond (times out, won't unpark)

Likely:
- Serial port wrong or needs initializing

Fix:
```bash
ls /dev/serial/by-id/
python3 ~/.ekos-runner/lx200gps_init.py --port /dev/ttyUSB0
```

Make sure pre-driver script is set in EKOS Profile Editor.

### 6. myqueue.php Shows Nothing

Likely:
- Known issue - myqueue.php expects project name format differently
- Also has PHP 8 timestamp bug

Workaround:
- Check /fits/ directly on server
- Use imagebrowser.php instead

### 7. Targets in Wrong Place

Likely:
- Coordinate conversion issue

Fix:
- Website sends RA in degrees (250.42 for M13)
- EKOS expects RA in hours (16.69 for M13)
- ekos_runner.py handles this automatically now

---

## Quick Reference

```bash
# Run everything
cd ~/.ekos-runner && ./run.sh scope03

# Individual steps
./pull_jobs.sh --machine-id scope03
./ekos_runner.py --machine-id scope03
./load_scheduler.sh --machine-id scope03
./push_jobs.sh --machine-id scope03

# Check status
./status.sh scope03

# Check server
ssh robotic@star-server ls /www/bayfordbury/automation/jobs/outgoing/scope03/
ssh robotic@star-server ls /www/bayfordbury/automation/fits/

# Test SSH
ssh -i ~/.ssh/id_rsa_star robotic@star-server echo OK

# Test KStars D-Bus
dbus-send --session --dest=org.kde.kstars --print-reply /KStars/Ekos/Scheduler org.freedesktop.DBus.Introspectable.Introspect

# Start KStars manually
kstars &
# or Flatpak: flatpak run org.kde.kstars &

# Emergency stop
~/.ekos-runner/emergency-shutdown.sh --reason "Testing"
```

---

Good luck! If in doubt, check the logs first.