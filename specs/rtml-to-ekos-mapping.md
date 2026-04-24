# RTML to EKOS mapping notes

This is a practical field-by-field mapping guide for moving the legacy RTML workflow toward KStars/EKOS.

## Migration stance

Do **not** make EKOS ingest RTML directly if avoidable.

Preferred path:
1. RTML remains an upstream/legacy input format
2. Approved RTML is converted into a neutral job JSON
3. An EKOS adapter converts the neutral job into EKOS-native artifacts or API actions
4. The telescope machine executes locally

## Core entity mapping

### Job / request level

| RTML source | Neutral job field | EKOS equivalent | Notes |
|---|---|---|---|
| `RTML/Contact/User` | `contact.user` | metadata only | Keep for audit, not scheduling |
| `RTML/Contact/Email` | `contact.email` | metadata only | Useful for provenance only |
| `Request/ID` | `plans[].plan_id` | scheduler job label | Good human-readable identifier |
| `Request/Project` | `plans[].project` | scheduler group / prefix / output dir name | Important for file naming |
| `Request/Description` | `plans[].description` | comment / metadata | Keep in sidecar metadata if EKOS has no direct slot |
| `Request/Observers` | `plans[].observers` | metadata only | Useful for bookkeeping |
| `Request/Telescope` | `plans[].telescope` | machine/telescope binding | Must be used to route to the correct telescope machine |
| `Schedule/Priority` | `priority` | queue ordering outside EKOS | EKOS is unlikely to preserve ACP-like numeric priority exactly |

### Time constraints

| RTML source | Neutral job field | EKOS equivalent | Notes |
|---|---|---|---|
| `Schedule/TimeRange/Earliest` | `schedule.time_range.earliest` | earliest startup / start condition | Straightforward mapping |
| `Schedule/TimeRange/Latest` | `schedule.time_range.latest` | latest startup / completion deadline | May need interpretation depending on EKOS scheduler behavior |
| `Reason` / monitor interval | `schedule.repeat.interval_days` | recurring resubmission outside EKOS | Better implemented by server-side scheduler than by EKOS alone |

### Environmental / observability constraints

| RTML source | Neutral job field | EKOS equivalent | Notes |
|---|---|---|---|
| `Schedule/SkyCondition` | `schedule.sky_condition` | weather quality policy | Likely external policy, not direct EKOS field |
| `Schedule/Airmass` or `AirmassRange` | `schedule.airmass` | min altitude / custom acceptability rule | Needs conversion because EKOS usually thinks in altitude more than airmass |
| `Schedule/HourAngleRange` | `schedule.hour_angle` | custom pre-check or visibility gate | May not map directly |
| `Schedule/Horizon` or altitude limit | `schedule.altitude` | minimum altitude | Clean mapping |
| `Schedule/Moon/Distance` | `schedule.moon.distance` | minimum moon separation | Check EKOS feature support; may need external gate |
| `Schedule/Moon/Width` | `schedule.moon.width` | custom rule | Likely external logic |
| `Schedule/Moon/Phase` | `schedule.moon.phase` | moon illumination limit | May require external decision logic |
| `Schedule/Moon/Down` | `schedule.moon.down` | moon-below-horizon constraint | If unavailable in EKOS directly, evaluate before submission |

### Target / imaging definition

| RTML source | Neutral job field | EKOS equivalent | Notes |
|---|---|---|---|
| `Target/Name` | `targets[].name` | target name | Direct mapping |
| `Coordinates/RightAscension` | `targets[].ra` / `ra_deg` | target RA | Direct mapping |
| `Coordinates/Declination` | `targets[].dec` / `dec_deg` | target Dec | Direct mapping |
| `OrbitalElements` | `targets[].orbital_elements` | moving-target handling | This is a special case and may need a separate adapter path |
| `Picture[count]` | `pictures[].count` | repeat count | Direct mapping into sequence repetitions |
| `Picture/ExposureTime` | `pictures[].exposure_time` | exposure duration | Direct mapping |
| `Picture/Binning` | `pictures[].binning` | binning | Direct mapping if camera supports it |
| `Picture/Filter` | `pictures[].filter` | filter wheel slot/filter name | Needs per-machine filter mapping table |

## What maps cleanly

These should map well into EKOS scheduler + sequence concepts:
- target name
- RA/Dec
- filter
- exposure duration
- repeat count
- binning
- minimum altitude
- start window
- telescope assignment
- project/output naming

## What does not map cleanly

These likely need server-side or runner-side logic rather than pure EKOS import:
- ACP-style numeric priority
- monitor/repeat every N days
- airmass range
- hour angle range
- moon avoidance Lorentzian logic
- some sky condition semantics
- orbital elements / moving-target workflows

## Recommended neutral JSON additions

Add these fields even if RTML does not currently supply them consistently:
- `machine_id`
- `destination_host`
- `camera_profile`
- `mount_profile`
- `filter_map_version`
- `job_state`
- `claimed_by`
- `claimed_at`
- `runner_version`
- `artifact_paths`

These make a multi-machine EKOS fleet much easier to operate.

## EKOS execution strategy options

### Option A: generate EKOS artifacts centrally
Server generates EKOS scheduler/sequence files and ships them to the telescope.

Pros:
- deterministic output
- easier to audit centrally

Cons:
- harder to keep compatible with machine-specific profiles
- less flexible if each machine differs

### Option B: generate EKOS artifacts locally on each telescope machine
Server sends neutral JSON only. Telescope runner builds local EKOS files.

Pros:
- best for 8 machines with small differences
- simpler routing and profile binding
- local machine can validate installed camera/filter names

Cons:
- requires a smarter local runner

For your setup, **Option B is probably better**.

## Suggested routing model for 8 telescope machines

Each outgoing job should contain:
- `job_id`
- `telescope_id`
- `machine_id`
- `project`
- `targets`
- `constraints`
- `created_at`
- `state`

Each telescope machine should:
1. authenticate to the central server
2. request or pull only jobs matching its `machine_id` or `telescope_id`
3. atomically claim the job
4. generate EKOS-native artifacts locally
5. execute
6. report `running`, `done`, or `failed`

## Minimal viable adapter split

- `rtml -> neutral-json` on the server
- `neutral-json -> ekos-artifacts` on the telescope machine
- `runner claim/report` mechanism between them

That gives a workable migration without needing to replace the whole website first.
