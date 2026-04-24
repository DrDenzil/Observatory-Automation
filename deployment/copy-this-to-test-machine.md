# Copy this to one Ubuntu 24.04 test machine

## Put these files in `/opt/ekos-runner/`

- `runner/ekos_runner.py`
- `runner/pull_jobs.sh`
- `runner/README.md`
- `runner/ekos-runner.service.example`
- `runner/ekos-pull.service.example`
- `runner/ekos-pull.timer.example`

## Create local directories

For example, for machine `scope06`:

```bash
sudo mkdir -p /opt/ekos-runner
sudo mkdir -p /var/lib/ekos-runner/jobs/scope06/{incoming,claimed,completed,failed,generated,logs}
```

## Install minimum packages

```bash
sudo apt update
sudo apt install -y python3 rsync openssh-client
```

## Make scripts executable

```bash
chmod +x /opt/ekos-runner/ekos_runner.py
chmod +x /opt/ekos-runner/pull_jobs.sh
```

## Configure SSH pull access

The machine needs SSH access to the central server and read access to its queue directory.

## First test commands

Pull jobs:

```bash
MACHINE_ID=scope06 REMOTE_USER=denis REMOTE_HOST=observatory-server REMOTE_BASE=/var/www/html/jobs /opt/ekos-runner/pull_jobs.sh
```

Prepare the local EKOS job bundle:

```bash
python3 /opt/ekos-runner/ekos_runner.py --machine-id scope06 --dry-run
```

## Expected output locations

```text
/var/lib/ekos-runner/jobs/scope06/completed/
/var/lib/ekos-runner/jobs/scope06/generated/
```

The generated directory should contain a per-job bundle with:

- scheduler placeholder (`.esl`)
- sequence placeholder (`.esq`)
- job manifest (`manifest.json`)
- per-target summary files (`targets/*.json`)
