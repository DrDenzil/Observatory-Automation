# Bayfordbury Observatory - EKOS Automation

Automated telescope job queue system for Bayfordbury Observatory.

## Quick Start

### Telescope Machines
```bash
git clone https://github.com/DrDenzil/Observatory-Automation.git
cd Observatory-Automation/runner

./deploy.sh --machine-id scope03
```

Options:
- `--machine-id scopeXX` - Which telescope (required)
- `--install-weather` - Add weather safety system and install KStars/INDI natively
- `--install-lx200gps` - Add LX200GPS telescope init
- `--install-cron` - Run automation every 2 hours
- `--install-systemd` - Install systemd user service/timer files

## Production Scope09 Test

```bash
cd Observatory-Automation/runner
./deploy.sh --machine-id scope09 --install-weather --install-watchdog --install-systemd

# Before unattended operation, confirm the real EKOS profile and INDI drivers:
nano ~/.ekos-runner/ekos-runner.env

# Run one supervised cycle first:
cd ~/.ekos-runner
./run.sh scope09

# Only enable/start the timer after the supervised test is safe:
systemctl --user enable ekos-runner.timer
systemctl --user start ekos-runner.timer

# Enable/start the watchdog after hardware parking is confirmed:
systemctl --user enable weather-watchdog.service
systemctl --user start weather-watchdog.service
```

`deploy.sh` installs the systemd timer and watchdog service but does not enable or start them automatically. This prevents a production scope from beginning unattended work before `EKOS_PROFILE`, `INDI_DRIVERS`, weather safety, and hardware parking have been checked.

## Run After Deployment

```bash
# Manual run:
cd ~/.ekos-runner
./run.sh scope09

# Or supervised systemd start after configuration:
systemctl --user start ekos-runner.timer
```

Or step-by-step:
```bash
./pull_jobs.sh --machine-id scope09
./ekos_runner.py --machine-id scope09
./load_scheduler.sh --machine-id scope09
# wait for captures...
./push_jobs.sh --machine-id scope09
```

## What It Does

1. Pulls jobs from star-server
2. Converts to EKOS format (`.esq`, `.esl`)
3. Loads into KStars scheduler
4. Captures images
5. Uploads back to server with FITS headers

## Folder Structure

```text
runner/           -> Copy to telescope machines
backend/          -> Copy to star-server web root
  source/PHP files/   website + import/thumbnail PHP files
```

## Key Files (runner/)

| File | Purpose |
|------|---------|
| `deploy.sh` | Deploy to a telescope machine |
| `pull_jobs.sh` | Download jobs from star-server |
| `ekos_runner.py` | Convert jobs to EKOS format |
| `push_jobs.sh` | Upload captures and inject headers |
| `load_scheduler.sh` | Load jobs into KStars |
| `run.sh` | Main pipeline script |
| `run-continuous.sh` | Continuous operation wrapper |
| `emergency-shutdown.sh` | Emergency stop |
| `weather-watchdog.sh` | Weather monitoring |
| `scripts/notify.sh` | Error notifications via webhook |
| `ekos-runner.service` | systemd user service |
| `ekos-runner.timer` | systemd user timer |

## Server Access

- **Host:** star-server (147.197.221.254)
- **SSH User:** ds
- **Key:** `~/.ssh/id_rsa_star`

## Documentation

See [GUIDE.md](GUIDE.md) for the full Observatory Automation technical guide.

## Troubleshooting

```bash
# systemd service
journalctl --user -u ekos-runner.service -f

# systemd timer
systemctl --user status ekos-runner.timer
```

Common issues:
- **Notification not working**: Check `scripts/notify.sh` and API key.
- **KStars window not visible**: `killall -9 gnome-shell` restarts the session.
- **Weather safety says unsafe**: Check sun altitude calculation in `weather_status.p`.
