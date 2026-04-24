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
- `--install-weather` - Add weather safety system
- `--install-lx200gps` - Add LX200GPS telescope init
- `--install-cron` - Run automation every 2 hours

### Run
```bash
cd ~/.ekos-runner
./run.sh scope03
```

Or step-by-step:
```bash
./pull_jobs.sh --machine-id scope03
./ekos_runner.py --machine-id scope03
./load_scheduler.sh --machine-id scope03
# wait for captures...
./push_jobs.sh --machine-id scope03
```

## What It Does

1. Pulls jobs from star-server
2. Converts to EKOS format (.esq, .esl)
3. Loads into KStars scheduler
4. Captures images
5. Uploads back to server with FITS headers

## Folder Structure

```
runner/           → Copy to telescope machines
backend/         → Copy to star-server web root
  └── source/PHP files/   (website + import.php)
```

## Key Files (runner/)

| File | Purpose |
|------|---------|
| `deploy.sh` | Deploy to new machine |
| `pull_jobs.sh` | Download jobs from server |
| `ekos_runner.py` | Convert to EKOS format |
| `push_jobs.sh` | Upload captures + inject headers |
| `load_scheduler.sh` | Load jobs into KStars |
| `emergency-shutdown.sh` | Emergency stop |
| `weather-watchdog.sh` | Weather monitoring |

## Server Access

- **Host:** star-server (147.197.221.254)
- **SSH User:** robotic
- **Key:** `~/.ssh/id_rsa_star`

## Documentation

See [GUIDE.md](GUIDE.md) for the full technical guide.

## Troubleshooting

Check the logs:
```bash
/var/log/ekos-runner-scope03.log
```