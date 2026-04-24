# One-telescope end-to-end EKOS migration test plan

This document defines the first realistic prototype test for the RTML -> neutral JSON -> EKOS flow.

## Objective

Prove the migration approach works for **one telescope machine** before scaling to all 8.

Success means:
- an approved RTML job is converted on the server
- the correct telescope machine pulls only its own job
- the machine generates EKOS-native artifacts locally
- the runner records a clear success/failure outcome
- no other telescope machine would be eligible to run that job

## Scope

This is **not** full fleet deployment.
It is a controlled vertical slice for one telescope.

## Recommended pilot telescope data

Choose one telescope with:
- stable EKOS/KStars installation
- known working camera/filter wheel profile
- a simple fixed-target imaging workflow
- no moving-target/orbital-element dependency

## Test input constraints

Use a deliberately simple RTML job:
- one `Request`
- one `Target`
- RA/Dec target, not MPC orbital elements
- 1 to 3 filters only
- simple exposure counts
- minimum altitude allowed
- no repeat/monitor schedule
- no unusual moon or airmass rules if avoidable

This keeps the first test focused on transport and translation rather than edge cases.

## Test phases

### Phase 1: server-side conversion

Input:
- approved RTML file
- approval sidecar present

Expected actions:
- RTML validated successfully
- neutral JSON job written to machine-specific outgoing location
- job includes `job_id`, `rtml_id`, `telescope_id`, `machine_id`, targets, exposures, constraints, and state

Expected evidence:
- exported JSON file exists
- exported metadata file exists
- logs show successful export

## Phase 2: machine-side retrieval

Expected actions:
- telescope machine pulls only from its own queue path
- runner verifies embedded `machine_id` / `telescope_id`
- runner moves job into a claimed/in-progress state locally

Expected evidence:
- local inbox/claimed file movement visible
- runner log shows claim event
- no unrelated machine could have matched the same job

## Phase 3: local EKOS artifact generation

Expected actions:
- runner loads local profile mapping for that telescope
- runner maps filter names and binning
- runner creates EKOS-native scheduler/sequence artifacts or command payloads

Expected evidence:
- generated artifact files exist
- artifact metadata points back to the source job id
- any unsupported RTML fields are logged clearly

## Phase 4: dry-run execution

Expected actions:
- runner performs validation without starting live capture if possible
- scheduler/job definition is syntactically valid
- target, filters, and exposures appear correctly in generated output

Expected evidence:
- dry-run log
- validation result recorded as pass/fail
- no live observation required for first pass

## Phase 5: controlled live run

Optional after dry-run passes.

Expected actions:
- one safe live execution window
- runner starts the job on the correct machine
- state transitions recorded

Expected evidence:
- started timestamp
- completed or failed timestamp
- output files/logs captured

## Pass criteria

The prototype passes if all of these are true:
- job routed to exactly one intended telescope machine
- runner accepted the job only when IDs matched
- EKOS-native artifacts were generated locally
- generated job matched RTML intent for target/filter/exposure basics
- job state transitions were recorded clearly
- failures, if any, were diagnosable from logs

## Fail criteria

Prototype fails if any of these occur:
- wrong telescope machine can see or claim the job
- machine cannot map filters/profile reliably
- generated EKOS artifacts are incomplete or invalid
- runner cannot distinguish transient vs permanent failure
- state reporting is ambiguous

## Minimal observability requirements

For the pilot, log at least:
- export time
- job id
- RTML id
- telescope id
- machine id
- claim time
- render start/end
- validation result
- execution start/end
- final state
- error message if failed

## Recommended sample job shape

Use a target like:
- one named deep-sky object
- fixed RA/Dec
- filters: L, R, G or similar known-good set on that machine
- exposures: modest and short
- count >= 2 per filter

Avoid for the first pilot:
- comets/asteroids
- monitor jobs
- multi-target plans
- advanced moon avoidance logic
- unusual constraints that may not map directly into EKOS

## Checklist

### Server
- [ ] approved RTML exists
- [ ] approval sidecar exists
- [ ] neutral exporter writes machine-specific output path
- [ ] exported JSON contains routing identity
- [ ] export logged

### Machine
- [ ] runner can pull only its own queue
- [ ] runner verifies machine/telescope identity
- [ ] runner can claim one job safely
- [ ] runner can generate EKOS-native artifacts
- [ ] runner logs render result

### Validation
- [ ] artifact content matches target/filter/exposure intent
- [ ] unsupported fields are logged, not silently dropped
- [ ] state transitions are visible and recoverable

## Next step after success

After one telescope succeeds:
1. add a second telescope with a different filter/profile map
2. compare mapping differences
3. harden claim/report protocol
4. only then scale to all 8 machines
