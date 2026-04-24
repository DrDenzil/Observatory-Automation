# Server ↔ machine flow

## Intended prototype flow

### Server side

1. User uploads or builds RTML in the legacy PHP app
2. RTML is approved
3. `rtmlconfirm.php` writes `rtml/<id>.approved.json`
4. `ekosjobsubmit.php` validates and converts RTML into a neutral EKOS job JSON
5. Exported job lands in:

```text
jobs/outgoing/<machine_id>/ekos_<rtmlid>_<timestamp>.json
```

### Telescope machine side

1. Machine pulls only its own directory over SSH/rsync
2. Job lands in local runner `incoming/`
3. `ekos_runner.py` claims it
4. Runner checks `machine_id`
5. Runner generates local EKOS artifacts
6. Runner moves the job to `completed/` or `failed/`

## Why this shape was chosen

- avoids one giant shared queue
- makes routing visible from directory structure alone
- still keeps machine identity duplicated inside JSON for safety
- lets each telescope machine own its local EKOS translation step

## Current weak spots

- no server-side acknowledgement from machine yet
- no real EKOS launch yet
- placeholder artifact generation only
- transfer is pull-based and simple, not a full queue protocol

## Planned next evolution

1. make EKOS output more realistic
2. add machine-specific profiles/config maps
3. add completion/failure reporting back to central server
4. optionally move from simple rsync pull to a stronger claim/report model
