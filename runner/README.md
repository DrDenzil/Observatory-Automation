# EKOS Runner Agent

A single-binary Go daemon that runs on each telescope machine. It replaces the
old bash-script automation entirely. The runner:

- **Polls** the observatory web API for queued jobs assigned to its scope
- **Builds** EKOS artifacts (`.esq` sequence + `.esl` scheduler) from each job
- **Drives** KStars/EKOS via D-Bus to execute the observation (or a simulator in dev)
- **Reports** progress and completion back to the web API
- **Heartbeats** its live status so the web dashboard shows each scope in real time
- **Exposes** its own REST API (`/status`, `/logs`, `/jobs`, `/trigger`, `/health`)

```
Web API  ──GET /api/runner/jobs/next──▶  Runner  ──build .esq/.esl──▶  KStars/EKOS
   ▲                                        │
   └──POST /api/runner/jobs/{id}/progress───┘
   └──POST /api/runner/heartbeat────────────┘
```

---

## Build

```bash
cd runner
go build -o ekos-runner .
```

Requires Go 1.26+. The only dependency is `gopkg.in/yaml.v3`.

## Configure

```bash
cp runner.yaml.example runner.yaml
# edit machine.id, web.base_url, web.api_key
```

`web.api_key` must match the backend's `RUNNER_API_KEY` setting.

Key settings (see `runner.yaml.example` for all):

| Setting | Meaning |
|---|---|
| `machine.id` | Scope id, e.g. `scope03`. Identifies this runner to the web API. |
| `web.base_url` | Observatory web API base URL. |
| `runner.simulator` | `true` = fake KStars (dev/CI), `false` = real D-Bus. |
| `runner.poll_interval` | How often to ask the web API for a job. |
| `runner.api_port` | Port for the runner's own REST API (default 9090). |

Environment overrides: `RUNNER_MACHINE_ID`, `RUNNER_WEB_URL`, `RUNNER_API_KEY`.

## Run

```bash
./ekos-runner -config runner.yaml
```

### Simulator mode (no telescope needed)

Set `runner.simulator: true`. The runner fetches real jobs, builds real EKOS
bundles, then simulates execution (`simulator_job_seconds`) instead of calling
D-Bus. Used for development, CI, and the website integration tests.

### Production mode (real KStars)

Set `runner.simulator: false`. The runner calls `qdbus org.kde.kstars
/KStars/Ekos/Scheduler ...` to load the scheduler list, start it, and poll its
state. KStars/EKOS must be running and the `qdbus` binary available.

## REST API

| Endpoint | Description |
|---|---|
| `GET /health` | `{ status, uptime_seconds }` |
| `GET /status` | Current scope state, current job, progress, hardware flags |
| `GET /logs?limit=100&level=info` | Recent structured log entries |
| `GET /jobs` | Current job + recent completed history |
| `POST /trigger` | Force an immediate poll (staff "Run Now") |

## Deploy

### systemd

```bash
sudo cp ekos-runner /usr/local/bin/
sudo mkdir -p /etc/ekos-runner && sudo cp runner.yaml /etc/ekos-runner/
sudo cp ekos-runner.service /etc/systemd/system/
sudo systemctl enable --now ekos-runner
journalctl -u ekos-runner -f
```

### Docker

```bash
docker build -t ekos-runner .
docker run -e RUNNER_WEB_URL=http://web:8000 -e RUNNER_API_KEY=... -p 9090:9090 ekos-runner
```

## Logging

Structured JSON, one object per line, to stdout (and optionally a file via
`logging.file`). Components: `main`, `pipeline`, `heartbeat`, `server`. The last
500 entries are kept in memory and served by `GET /logs`.
