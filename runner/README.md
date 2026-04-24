# EKOS runner prototype

Prototype runner for Ubuntu 24.04 telescope machines.

## What it does

- watches a per-machine local queue directory
- claims the next JSON job for that machine
- verifies `machine_id` matches the local runner
- builds a per-job artifact bundle under `generated/<queue_ref>/`
- generates prototype EKOS-oriented files:
  - `.esq` sequence file
  - `.esl` scheduler file
  - `manifest.json`
  - `targets/target-*.json` summaries
- moves jobs through `incoming/`, `claimed/`, `completed/`, `failed/`

## Queue layout

Example for `scope06`:

```text
/var/lib/ekos-runner/jobs/scope06/
  incoming/
  claimed/
  completed/
  failed/
  generated/
  logs/
```

## Run manually

```bash
python3 ekos_runner.py --machine-id scope06 --dry-run
```

## How to feed it jobs

The server-side exporter should place jobs into a matching directory, e.g.:

```text
jobs/outgoing/scope06/ekos_12345_20260319T095500Z.json
```

Then your transfer step can copy that into the local machine's:

```text
/var/lib/ekos-runner/jobs/scope06/incoming/
```

## Generated artifact bundle

Each prepared job gets its own bundle:

```text
generated/<queue_ref>/
  <queue_ref>.esq
  <queue_ref>.esl
  manifest.json
  README.txt
  targets/
    target-01.json
    target-02.json
```

This is still a prototype, but it is much closer to a useful first observatory test because it lets you inspect:

- what target data reached the machine
- what exposure/filter data survived the conversion
- what EKOS-oriented placeholders were produced

## Important note

This still does **not** yet:
- launch KStars/EKOS
- drive DBus
- push scheduler jobs into a live EKOS session
- report completion back to the server
- handle multiple queued jobs in a daemon loop

It is meant to prove the routing, payload, and artifact-generation contract first.
