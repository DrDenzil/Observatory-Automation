# Observatory EKOS prototype deployment checklist

## 1. Files to replace or add on the server holding the original RTML PHP files

### Add

Place these into the same PHP application directory as the existing RTML files:

- `ekosjobsubmit.php`

### Replace / patch

Update these existing files so the UI points at the EKOS export path instead of the old ACP/NINA flow:

- `rtmlconfirm.php`
  - change the post-approval queue action from `ninajobsubmit.php` to `ekosjobsubmit.php`
  - change wording from **N.I.N.A** to **EKOS**
- `rtmlread.php`
  - change direct queue action from `rtmlsubmit.php` to `ekosjobsubmit.php`
- `allrtml.php`
  - change direct queue action from `rtmlsubmit.php` to `ekosjobsubmit.php`

### Optional but recommended

Keep these for reference during migration, but they do not need to be active UI targets:

- `ninajobsubmit.php`
- `rtmlsubmit.php`

## 2. Folders to create on the server

Assuming the PHP app root already contains `rtml/` and `logs/`, create:

```text
jobs/
  outgoing/
    scope06/
    scope09/
    ... one directory per telescope machine ...
  sent/
    scope06/
    scope09/
    ... one directory per telescope machine ...
```

Minimum example for current tested scopes:

```bash
mkdir -p jobs/outgoing/scope06 jobs/outgoing/scope09
mkdir -p jobs/sent/scope06 jobs/sent/scope09
```

If you want to prepare all 8 telescope machines up front, create all expected machine IDs now, for example:

```bash
mkdir -p jobs/outgoing/scope01 jobs/outgoing/scope02 jobs/outgoing/scope03 jobs/outgoing/scope04 \
         jobs/outgoing/scope05 jobs/outgoing/scope06 jobs/outgoing/scope07 jobs/outgoing/scope08
mkdir -p jobs/sent/scope01 jobs/sent/scope02 jobs/sent/scope03 jobs/sent/scope04 \
         jobs/sent/scope05 jobs/sent/scope06 jobs/sent/scope07 jobs/sent/scope08
```

Also ensure the web server/PHP user can write to:

- `jobs/outgoing/`
- `jobs/sent/`
- `logs/`
- `rtml/`

## 3. What to put on one Ubuntu 24.04 test machine

Create a small local runner directory, e.g.:

```text
/opt/ekos-runner/
  ekos_runner.py
  pull_jobs.sh
  README.md
```

And create the local queue directories:

```text
/var/lib/ekos-runner/jobs/scope06/
  incoming/
  claimed/
  completed/
  failed/
  generated/
  logs/
```

Example commands:

```bash
sudo mkdir -p /opt/ekos-runner
sudo mkdir -p /var/lib/ekos-runner/jobs/scope06/{incoming,claimed,completed,failed,generated,logs}
```

Copy these files onto the test machine:

- `runner/ekos_runner.py`
- `runner/pull_jobs.sh`
- `runner/README.md`
- optionally:
  - `runner/ekos-runner.service.example`
  - `runner/ekos-pull.service.example`
  - `runner/ekos-pull.timer.example`

Make scripts executable:

```bash
chmod +x /opt/ekos-runner/ekos_runner.py
chmod +x /opt/ekos-runner/pull_jobs.sh
```

## 4. Packages / prerequisites on the test machine

Minimum for the prototype:

```bash
sudo apt update
sudo apt install -y python3 rsync openssh-client
```

For actual EKOS testing later, you will also want your KStars/EKOS stack installed and working on that machine already.

## 5. SSH requirement for pull testing

The test machine needs SSH access to the central server.

Recommended:
- create a dedicated SSH key for the machine
- authorize it on the server
- test a non-interactive login

Example test:

```bash
ssh denis@observatory-server 'hostname && ls -la /var/www/html/jobs/outgoing/scope06'
```

## 6. First prototype workflow test

### On the server
1. Approve an RTML job
2. Queue it with `ekosjobsubmit.php`
3. Confirm a job file appears in:
   - `jobs/outgoing/scope06/`

### On the test machine
1. Pull jobs:

```bash
MACHINE_ID=scope06 REMOTE_USER=denis REMOTE_HOST=observatory-server REMOTE_BASE=/var/www/html/jobs /opt/ekos-runner/pull_jobs.sh
```

2. Run the runner:

```bash
python3 /opt/ekos-runner/ekos_runner.py --machine-id scope06 --dry-run
```

3. Confirm results appear in:
- `/var/lib/ekos-runner/jobs/scope06/completed/`
- `/var/lib/ekos-runner/jobs/scope06/generated/`

## 7. What still remains after this prototype

This prototype proves:
- routing by machine ID
- per-machine server queue directories
- pull-to-local workflow
- job claiming
- local generation of EKOS placeholder artifacts

Still to do:
- real RTML → EKOS scheduler/sequence mapping
- actual EKOS launch or DBus integration
- server acknowledgement / status push-back
- moving processed server jobs from `outgoing/` to `sent/`
- better machine-specific config (camera, filters, profile names)
