# Bayfordbury Observatory - EKOS Automation

Automated telescope job queue system for Bayfordbury Observatory.

## Quick Start

### 1. Clone the repo
```bash
git clone https://github.com/DrDenzil/Observatory-Automation.git
cd Observatory-Automation/runner
```

### 2. Deploy to a telescope machine
```bash
./deploy.sh --machine-id scope03
```

Options:
- `--machine-id scopeXX` - Which telescope (required)
- `--install-weather` - Add weather safety system
- `--install-lx200gps` - Add LX200GPS telescope init
- `--install-cron` - Run automation every 2 hours

### 3. Run the automation
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

## What it does

1. Pulls jobs from star-server
2. Converts RTML to EKOS format
3. Loads into KStars scheduler
4. Captures images
5. Uploads back to server with metadata

## Key Files

| File | Purpose |
|------|---------|
| `pull_jobs.sh` | Download jobs from server |
| `ekos_runner.py` | Convert to EKOS format |
| `push_jobs.sh` | Upload captures + inject headers |
| `load_scheduler.sh` | Load jobs into KStars |
| `deploy.sh` | Deploy to new machine |

## Server Access

- **Host:** star-server (147.197.221.254)
- **SSH User:** ds
- **Key:** `~/.ssh/id_rsa_star`

## Job Directories

- **Local:** `/var/lib/ekos-runner/jobs/scope03/`
- **Server:** `/www/bayfordbury/automation/jobs/`

## Documentation

- `HOW_IT_WORKS.md` - How the system works
- `MEMORY.md` - Technical notes and troubleshooting

## Issues?

Check the logs:
```bash
/var/log/ekos-runner-scope03.log
```
