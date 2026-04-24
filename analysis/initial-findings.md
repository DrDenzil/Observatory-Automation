# Initial findings: legacy RTML workflow and migration direction

## Big picture

This PHP bundle is a web front-end for building, validating, approving, viewing, downloading, and submitting RTML observation plans.

The legacy production path is ACP-centric:
1. Build or upload RTML
2. Validate RTML
3. Save RTML in `rtml/<id>.rtml`
4. Approve the plan
5. Submit the RTML file to a telescope ACP endpoint over HTTP
6. Parse ACP response and write project/plan IDs back to the database

There is also a newer migration path already present in the code:
1. Approve RTML
2. `rtmlconfirm.php` writes `rtml/<id>.approved.json`
3. `ninajobsubmit.php` reads approved RTML
4. It converts RTML into a neutral JSON job payload
5. It writes queue files into `jobs/outgoing/`
6. A separate Windows/N.I.N.A runner is expected to pull those JSON files and execute them

That newer path is the best foundation for an EKOS migration too.

## Filesystem/layout note

The recovered code appears to have been split across at least two filesystem roots:
- `/www/bayfordbury/constants.php` — parent-site bootstrap/auth/session file
- `/www/bayfordbury/automation/` — most of the observatory automation pages in this project bundle

That is worth preserving because it means the automation code depended on shared site-level bootstrap/auth code outside the automation directory.

## Files most relevant to RTML workflow

- `rtmleditor.php` — browser UI for constructing plan data in JSON
- `json2rtml.php` — converts editor JSON into RTML XML and saves `rtml/editor/<userid>.rtml`
- `dlrtml.php` — downloads generated RTML
- `quickuploadrtml.php` — uploads an RTML file, validates it, inserts DB record, saves `rtml/<id>.rtml`
- `rtmldetails.php` — central RTML validator/inspector used by other pages
- `rtmlread.php` — shows details for an RTML record and exposes approve/reject actions
- `rtmlconfirm.php` — changes RTML status and writes approval sidecar JSON
- `rtmlsubmit.php` — legacy ACP uploader using `uploadrtml.asp`
- `ninajobsubmit.php` — newer file-based export replacing ACP submission with JSON queue output
- `myrtml.php`, `allrtml.php`, `rtmldetails.php`, `reloadplan.php` — browse/read existing RTML jobs

## Legacy ACP submission path

The old end of the pipeline is in `rtmlsubmit.php`:
- Loads `rtml/<id>.rtml`
- Normalises/sanitises request fields
- Forces ACP user to `Robotic`
- Looks up telescope hostname from `config.php`
- POSTs the file to `http://<scope>/server/uploadrtml.asp`
- Parses ACP HTML response text
- Updates DB status plus project/plan tables

That means the current system is tightly coupled to:
- RTML XML as the interchange format
- ACP upload endpoint semantics
- ACP response parsing
- ACP queue/project identifiers

## Newer queue-export path already present

`ninajobsubmit.php` is the key file for migration:
- Reads an approved RTML file
- Parses schedule/targets/pictures into structured JSON
- Validates against telescope config embedded in the file
- Writes `jobs/outgoing/job_<rtmlid>_<timestamp>.json`
- Writes companion metadata JSON
- Updates RTML status in DB
- Mentions that a Windows runner can pull JSON over SCP

This is already the architectural seam you want.

## Practical migration interpretation for EKOS

Instead of trying to make EKOS consume RTML directly, the safer route is:

1. Keep legacy UI + RTML generation temporarily
2. Treat RTML as an internal source format only
3. Convert approved RTML into a neutral intermediate job format
4. Add an EKOS-specific runner/converter on each telescope machine
5. Have each telescope machine only fetch jobs addressed to itself
6. Convert the neutral job into EKOS scheduler / sequence artifacts locally

## Telescope routing concern

Your note about 8 telescope machines is exactly the right concern.

The queue payload must include at least:
- telescope ID / machine ID
- machine hostname or destination key
- job ID
- project name
- target list
- filters/exposures
- scheduling constraints
- state (`outgoing`, `claimed`, `running`, `done`, `failed`)

Each machine should only claim jobs that match its own telescope ID. Do not let every machine pull a shared undifferentiated folder.

## Recommended next step

Do the migration in two layers:

### Layer 1: canonical intermediate format
Define one JSON schema that represents:
- project metadata
- telescope assignment
- one or more targets
- coordinates/orbital elements
- exposures by filter
- timing constraints
- moon/altitude/airmass constraints
- repeat/monitor rules

### Layer 2: per-platform adapters
- Legacy adapter: RTML -> ACP upload (existing old path)
- Existing experimental adapter: RTML -> neutral JSON (`ninajobsubmit.php`)
- New adapter: neutral JSON -> EKOS scheduler/sequence files

This keeps the migration manageable and avoids locking the new system to RTML forever.

## Suggested project structure

- `projects/observatory-automations/source/` — extracted legacy PHP source
- `projects/observatory-automations/analysis/` — architecture notes and maps
- `projects/observatory-automations/specs/job-schema.md` — neutral schema definition
- `projects/observatory-automations/specs/rtml-to-ekos-mapping.md` — field-by-field mapping
- `projects/observatory-automations/runner/` — future poller/claimer/downloader logic
- `projects/observatory-automations/examples/` — sample RTML, JSON jobs, EKOS outputs

## Main risks spotted early

- RTML constraints do not map 1:1 to EKOS scheduler concepts
- Existing code stores some telescope rules in DB (`obssetup`) and some in PHP config
- `rtmlsubmit.php` parses ACP HTML, which will need replacing completely
- Current code appears to assume a central DB-backed web workflow, while EKOS execution may be more decentralized
- Multi-machine claiming must be designed carefully to avoid two machines running the same job
