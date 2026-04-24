# Copy this to the server

Copy these files from the repo into the live PHP application directory that currently holds the RTML workflow.

## New file to add

- `source/PHP files/ekosjobsubmit.php`

## Existing files to replace with the patched project versions

- `source/PHP files/rtmlconfirm.php`
- `source/PHP files/rtmlread.php`
- `source/PHP files/allrtml.php`

## Folders to create on the server

Create these under the PHP app root:

```text
jobs/
  outgoing/
    scope01/
    scope02/
    scope03/
    scope04/
    scope05/
    scope06/
    scope07/
    scope08/
  sent/
    scope01/
    scope02/
    scope03/
    scope04/
    scope05/
    scope06/
    scope07/
    scope08/
```

## Required writable paths

Ensure the PHP/web user can write to:

- `rtml/`
- `logs/`
- `jobs/outgoing/`
- `jobs/sent/`

## Suggested backup-before-replace list

Before replacing anything on the live server, make a backup copy of:

- `rtmlconfirm.php`
- `rtmlread.php`
- `allrtml.php`
- `ninajobsubmit.php`
- `rtmlsubmit.php`

## First server-side check after copy

After deploying the PHP files:

1. approve an RTML job
2. use the new **Queue for EKOS** button
3. verify a file appears in the matching per-machine directory, e.g.:

```text
jobs/outgoing/scope06/ekos_<rtmlid>_<timestamp>.json
```
