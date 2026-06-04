# Mininet Lab Container

Docker container for running Mininet networking lab exercises.

## Quick Start

1. Clone the repository:
```bash
git clone https://github.com/DrDenzil/Mininet-Lab.git ~/docker/mininet
```

2. Run the setup script:
```bash
cd ~/docker/mininet
bash setup.sh
```

3. Double-click Mininet-Lab.desktop on your Desktop

## Windows / Docker Desktop Users

**Important:** Run these commands in **WSL2 (Ubuntu terminal)**, not Windows CMD/PowerShell.

### Opening WSL2 Terminal

**Method 1:** Press `Win + R`, type `wsl`, press Enter

**Method 2:** Open Start Menu, search "Ubuntu" or "WSL"

**Method 3:** In VS Code, open terminal and select "Ubuntu (WSL)"

### Then run:

```bash
cd ~
git clone https://github.com/DrDenzil/Mininet-Lab.git ~/docker/mininet
```
Follow Quick Start from there.

## Lab Files

| File | Description | Requirements |
|------|------------|--------------|
| Lab04Ex3.py | SIP Protocol Testing | None |
| Lab05_1.py | VLAN/ONOS | ONOS SDN controller |
| Lab05_1_fixed.py | VLAN (standalone) | None |
| Lab03_2.py | WiFi Mobility | mac80211_hwsim on host |

## About the Lab Files

### Lab05_1_fixed.py
This is a fixed version of Lab05_1.py that works without ONOS.
- The original Lab05_1.py requires an ONOS SDN controller
- The fixed version uses OVS standalone mode
- Use this version unless you have ONOS set up

### Lab03_2.py (WiFi Mobility)
This lab creates a real WiFi network using the mac80211_hwsim kernel module.
- On Linux host: `sudo modprobe mac80211_hwsim` before running
- Not available on Windows/WSL2 - runs without WiFi features but OK for testing

---

# Bayfordbury Observatory - EKOS Automation

Automated telescope job queue system for Bayfordbury Observatory.

## Telescope Deployment

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
