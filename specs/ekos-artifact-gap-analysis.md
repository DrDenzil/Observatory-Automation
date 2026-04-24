# EKOS artifact gap analysis

This note captures the gap between the current prototype artifacts and what is needed for a realistic EKOS pilot.

## Current prototype status

The current pipeline already has two important working pieces:
- `source/PHP files/ekosjobsubmit.php` exports machine-routed JSON jobs
- `runner/ekos_runner.py` claims those jobs locally and writes prototype `.esq` and `.esl` files

That means routing and payload handoff are no longer the main unknown.
The main unknown is now **real EKOS artifact fidelity**.

## What the runner generates today

### Sequence file (`.esq`)
Current output is a simple XML placeholder with repeated blocks like:
- target name
- filter
- exposure
- count
- binning

### Scheduler file (`.esl`)
Current output is also a simplified placeholder containing:
- target name
- RA/Dec
- path to sequence file
- startup condition string
- completion condition string
- minimum altitude

### Supporting files
The runner also produces:
- `manifest.json`
- per-target JSON summaries
- a short README

These are useful for inspection, but they are not yet confirmed to be valid importable EKOS files.

## Biggest gap

The project does **not yet have known-good reference `.esq` and `.esl` files from a real EKOS machine**.

Without those reference files, it is easy to generate XML that looks plausible but is not actually accepted by EKOS.

## What needs to be collected from a real EKOS setup

From one pilot telescope machine, capture:
- one real saved EKOS scheduler file for a simple fixed target
- one real saved EKOS sequence file for that same target
- any related profile/settings needed to understand naming and field expectations
- filter names exactly as EKOS sees them
- any machine-specific capture defaults that matter

## What to compare

Compare prototype output from `ekos_runner.py` against real EKOS files for:

### Sequence structure
- root element name and version
- required child elements
- representation of exposure duration
- representation of filter selection
- representation of count/repeats
- representation of binning
- frame type / gain / offset / temperature fields if required
- storage/output naming fields

### Scheduler structure
- root element name and version
- how target coordinates are represented
- how startup constraints are expressed
- how altitude/moon constraints are expressed
- how sequence-file references are represented
- required scheduler job metadata
- whether startup/completion condition text in the prototype matches actual EKOS schema

## Likely missing fields

Based on the current placeholder generator, these may need adding:
- capture profile name
- camera name / optical train / equipment binding
- frame type (light/dark/etc.)
- file naming template
- upload/storage mode
- gain / offset / ISO-like settings if camera requires them
- temperature settings / cooler policy
- dithering / guiding flags
- plate solving / alignment policy
- park / unpark / startup / shutdown behavior
- twilight / astronomical darkness constraints

Not all of these must be driven from RTML, but EKOS may still require defaults.

## Good next implementation pattern

Do not try to solve all machines at once.

Use one pilot machine and do this:
1. save a real minimal EKOS sequence and scheduler job manually in KStars/EKOS
2. store sanitized reference copies in this project
3. update `ekos_runner.py` so generated output matches those references structurally
4. only then test whether EKOS can load the generated files

## Suggested reference storage

Add a local reference area such as:
- `projects/observatory-automations/examples/ekos-reference/sequence-minimal.esq`
- `projects/observatory-automations/examples/ekos-reference/scheduler-minimal.esl`
- `projects/observatory-automations/examples/ekos-reference/notes.md`

## Definition of done for the next step

The next step should be considered complete only when:
- a real EKOS-generated `.esq` and `.esl` pair has been collected
- the runner output has been compared against them
- the remaining unmapped fields are listed explicitly
- it is clear whether generated files are valid enough for dry-run import on the pilot machine

## Practical conclusion

The project is past the vague architecture stage.
The immediate blocker is now very specific:

**obtain a real EKOS scheduler/sequence reference pair from the pilot machine and make the runner match it structurally.**
