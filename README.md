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