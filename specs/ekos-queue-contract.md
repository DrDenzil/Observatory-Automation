# EKOS queue contract (prototype)

## Goal

Use per-machine outgoing directories on the server side, with `machine_id` duplicated inside each JSON payload.

## Server-side export layout

```text
jobs/outgoing/
  scope06/
    ekos_12345_20260319T095500Z.json
    ekos_12345_20260319T095500Z.meta.json
  scope09/
    ekos_12346_20260319T095700Z.json
```

## Key routing fields

Each exported job should include:

- `queue_ref`
- `job_type = "ekos"`
- `machine_id`
- `telescope.id`
- `telescope.name`
- `telescope.host`
- `source.path`
- `source.approval_path`
- `approval.machine_id`
- `job.machine_id`

## Why both directory and JSON routing exist

- Directory routing keeps the runner simple and cheap.
- JSON routing gives the runner a second check before it claims/runs a job.
- This avoids a bad copy ending up on the wrong telescope machine unnoticed.

## Runner-side local layout

```text
/var/lib/ekos-runner/jobs/scope06/
  incoming/
  claimed/
  completed/
  failed/
  generated/
  logs/
```

## Current prototype behaviour

- exporter writes one job + one meta file into the correct per-machine outgoing directory
- runner claims the next matching job from `incoming/`
- runner writes placeholder `.esq` and `.esl` files
- runner moves the job into `completed/` or `failed/`

## Next likely improvements

1. Replace placeholder EKOS file generation with real EKOS scheduler/sequence mapping
2. Add a transfer/claim mechanism over SSH/SCP or rsync
3. Add a return-path for run status updates to the central server
4. Add locking / dedupe / retries for multi-runner robustness
