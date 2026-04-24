# PHP relationship map

## Filesystem layout note

Recovered path information suggests the legacy app is split across two roots:

- `/www/bayfordbury/constants.php` — site-wide bootstrap/auth/session file
- `/www/bayfordbury/automation/` — most of the observatory automation PHP pages analysed in this project

That matters because some runtime dependencies are **outside** the automation directory, so the automation pages are not fully self-contained.

## Cross-root/shared bootstrap files

- **constants.php**
  - recovered reference path: `/www/bayfordbury/constants.php`
  - role: site-wide bootstrap for sessions, DB connection, SAML auth, IP classification, and shared globals
  - likely consumed by parent-level include files such as `mHeader.php` / shared site bootstrap
  - notes: original location is outside `/automation`, which is important for reconstruction and migration

## RTML-focused files

- **allrtml.php**
  - calls/links/includes: rtmlread.php
- **constants.php**
  - referenced indirectly as shared site bootstrap outside `/automation`
- **dlrtml.php**
  - referenced by: json2rtml.php, myrtml.php
- **editorsubmitrtml.php**
  - calls/links/includes: rtmldetails.php
- **json2rtml.php**
  - calls/links/includes: dlrtml.php
- **myrtml.php**
  - calls/links/includes: dlrtml.php, reloadplan.php, rtmlread.php
- **ninajobsubmit.php**
- **quicksubmitrtml.php**
  - calls/links/includes: config.php, rtmldetails.php
- **quickuploadrtml.php**
- **rtmlconfirm.php**
- **rtmldetails.php**
  - referenced by: editorsubmitrtml.php, quicksubmitrtml.php, rtmlread.php
- **rtmleditor.php**
  - calls/links/includes: condition.php
- **rtmlread.php**
  - calls/links/includes: rtmldetails.php
  - referenced by: allrtml.php, myrtml.php, reloadplan.php, rtmlreject1.php
- **rtmlreject1.php**
  - calls/links/includes: rtmlread.php
- **rtmlreject2.php**
- **rtmlsubmit.php**
  - calls/links/includes: config.php

## Architecture notes from `constants.php`

`constants.php` adds important context not obvious from the `/automation` files alone:

- centralises site/session defaults
- opens the MySQL connection
- loads SimpleSAML authentication
- derives user identity attributes (`uhid`, email, given name, surname)
- flags university/private-network access with `ipIsPrivate()`

This strongly suggests the automation app relied on a shared parent-site authentication/bootstrap layer rather than owning auth entirely within `/automation`.

## Full outbound map

- **accounts.php** → (none)
- **allrtml.php** → rtmlread.php
- **condition.php** → (none)
- **config.php** → (none)
- **constants.php** → external deps: `/www/bayfordbury/private/db.php`, `/var/simplesamlphp/lib/_autoload.php`
- **contact.php** → (none)
- **dlrtml.php** → (none)
- **dss.php** → (none)
- **editorsubmitrtml.php** → rtmldetails.php
- **expcalc.php** → (none)
- **functions2.php** → (none)
- **getfit.php** → (none)
- **header.php** → config.php
- **imagebrowser.php** → imagedetails.php
- **imagedetails-old.php** → getfit.php, searchresults.php
- **imagedetails.php** → getfit.php, searchresults.php
- **imagesearch.php** → imagedetails.php, searchresults.php
- **index.php** → accounts.php, moonbits.php, moontimes.php
- **json2rtml.php** → dlrtml.php
- **login.php** → (none)
- **logout.php** → (none)
- **mdwarfs.php** → (none)
- **moonavoidancecalc.php** → (none)
- **moonbits.php** → (none)
- **moontimes.php** → (none)
- **myaccount.php** → (none)
- **myprojects.php** → viewproject.php
- **myqueue.php** → (none)
- **myrtml.php** → dlrtml.php, reloadplan.php, rtmlread.php
- **ninajobsubmit.php** → (none)
- **obssetup.php** → config.php
- **projects.php** → viewproject.php
- **ptearth.php** → (none)
- **queue.php** → (none)
- **queuebrowser.php** → (none)
- **quicksubmitrtml.php** → config.php, rtmldetails.php
- **quickuploadrtml.php** → (none)
- **reloadplan.php** → rtmlread.php
- **rtmlconfirm.php** → (none)
- **rtmldetails.php** → (none)
- **rtmleditor.php** → condition.php
- **rtmlread.php** → rtmldetails.php
- **rtmlreject1.php** → rtmlread.php
- **rtmlreject2.php** → (none)
- **rtmlsubmit.php** → config.php
- **searchresults.php** → imagedetails.php
- **standardstars.php** → (none)
- **targetcheck.php** → (none)
- **viewproject.php** → viewtarget.php
- **viewtarget.php** → imagedetails.php
