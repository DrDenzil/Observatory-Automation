# Project status

## Goal

Replace the legacy ACP/N.I.N.A-adjacent RTML execution path with an EKOS-oriented workflow that can route jobs safely to the correct telescope machine.

## Where things stand now

### Confirmed

- The legacy site builds, validates, approves, and stores RTML plans.
- `rtmlconfirm.php` writes approval sidecars like `rtml/<id>.approved.json`.
- `ninajobsubmit.php` already represents a partial migration seam away from direct ACP upload.
- A prototype `ekosjobsubmit.php` now exists in this project copy.
- The EKOS prototype writes jobs into per-machine directories such as `jobs/outgoing/scope06/`.
- A prototype Ubuntu 24.04 runner exists to claim and prepare jobs locally.

### Prototype pieces built

- RTML/PHP relationship map
- RTML → EKOS mapping notes
- EKOS queue contract
- `ekosjobsubmit.php`
- `ekos_runner.py`
- `pull_jobs.sh`
- systemd example units/timer
- test deployment checklist

## What is still prototype-only

- generated `.esq` and `.esl` files are placeholders
- no live KStars/EKOS control yet
- no runner callback to central server yet
- no production auth/locking/retry scheme yet
- no final machine-specific filter/profile mapping yet

## Recommended next work items

### High priority

1. test queue export on the real server
2. test pull + runner flow on one Ubuntu 24.04 telescope machine
3. decide how machine-specific camera/filter/profile config should be stored
4. replace prototype EKOS XML placeholders with known-good EKOS-native structure from a real machine

### Medium priority

5. implement status reporting back to the server
6. move processed jobs from server `outgoing/` to `sent/`
7. add stronger claim/deduplication logic

## If work gets interrupted

Resume from this order:

1. `README.md`
2. `STATUS.md`
3. `deployment/test-deployment-checklist.md`
4. `specs/rtml-to-ekos-mapping.md`
5. `runner/README.md`

Then continue with the next high-priority item above.
/rtml-to-ekos-mapping.md`
5. `runner/README.md`

Then continue with the next high-priority item above.
