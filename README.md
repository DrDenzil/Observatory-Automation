# Observatory Automation

Migration workspace for moving a legacy RTML/ACP observatory workflow toward a machine-routed KStars/EKOS workflow.

## What this repo is for

This repo is the working project space for:

- analysing the original PHP/RTML system
- documenting how the existing workflow behaves
- designing a neutral queue/job format
- prototyping an EKOS-oriented export path
- prototyping a runner for Ubuntu 24.04 telescope machines
- keeping enough notes that the project is easy to resume after interruptions

## Current status

Working prototype pieces exist for:

- **legacy workflow analysis**
- **PHP relationship mapping**
- **RTML → neutral job export**
- **EKOS queue routing by machine ID**
- **Ubuntu runner prototype**
- **server/test-machine deployment checklist**

Not finished yet:

- real EKOS-native scheduler/sequence generation
- actual KStars/EKOS launch or DBus integration
- return-path for job status back to the central server
- machine-specific config for cameras, filters, and profiles

## Repo layout

This repository root should contain the observatory automation project itself.
It is fine for the project files to live directly at the repo root.
Do not mix in unrelated OpenClaw workspace files, assistant memory/task files, or other repos.

- `analysis/` — findings, maps, and architecture notes from the legacy PHP code
- `deployment/` — practical deployment and testing checklists
- `runner/` — Ubuntu 24.04 EKOS runner prototype and service examples
- `source/` — source copies of the legacy PHP app under study
- `specs/` — contract docs, field mappings, and design notes
- `uploads/` — original uploaded source artifacts for traceability

## Best starting points

If you are picking this project up fresh, read these first:

1. `analysis/initial-findings.md`
2. `analysis/php-relationship-map.md`
3. `specs/rtml-to-ekos-mapping.md`
4. `specs/ekos-queue-contract.md`
5. `deployment/test-deployment-checklist.md`
6. `STATUS.md`

## Prototype workflow in one sentence

Approved RTML is exported by the PHP app into a **per-machine JSON queue**, then an Ubuntu runner on the correct telescope machine **pulls, claims, and prepares EKOS artifacts locally**.

## Near-term plan

1. tighten the RTML → EKOS field mapping
2. replace placeholder `.esq/.esl` output with more realistic EKOS artifacts
3. test end-to-end on one observatory machine
4. add status reporting back to the server
5. expand machine-specific configuration for the full telescope fleet

## Notes

This repo is intentionally a working engineering notebook as well as a codebase.

The goal is not just to build the migration, but to make it easy to stop and restart work without losing context.
