# EKOS reference notes from local KStars 3.6.2 lab box

Date: 2026-03-21
Host: Ubuntu 24.04.4 local OpenClaw machine
Packages installed for investigation:
- kstars 3.6.2
- indi-bin 1.9.9
- astrometry.net
- xplanet
- astap

## What was confirmed locally

Using the installed KStars docs plus upstream KStars source, the current EKOS file formats in use are:

- `.esq` sequence queue root: `<SequenceQueue version='2.4'>`
- `.esl` scheduler root: `<SchedulerList version='2.2'>`

## Sequence queue structure confirmed from source

Saved by `SequenceQueue::save()` and per-job `SequenceJob::saveTo()`.

Important top-level fields seen in current source:
- `Observer`
- `GuideDeviation`
- `GuideStartDeviation`
- `HFRCheck`
- `RefocusOnTemperatureDelta`
- `RefocusEveryN`
- `RefocusOnMeridianFlip`
- repeated `Job`

Important per-job fields seen in current source:
- `Exposure`
- `Format`
- `Encoding`
- `Binning/X,Y`
- `Frame/X,Y,W,H`
- optional `Temperature`
- optional `Filter`
- `Type`
- `Count`
- `Delay`
- optional `TargetName`
- `GuideDitherPerJob`
- `FITSDirectory`
- `PlaceholderFormat`
- `PlaceholderSuffix`
- `UploadMode`
- optional `RemoteDirectory`
- optional `ISOIndex`
- optional `Rotation`
- `Properties`
- `Calibration`

## Scheduler structure confirmed from source

Saved by `SchedulerProcess::saveScheduler()`.

Important top-level fields seen in current source:
- `Profile`
- optional `Mosaic`
- repeated `Job`
- `SchedulerAlgorithm`
- `ErrorHandlingStrategy`
- `StartupProcedure`
- `ShutdownProcedure`

Important per-job fields seen in current source:
- `JobType lead='true|false'`
- `Name`
- `Group`
- `Coordinates/J2000RA,J2000DE`
- optional `OpticalTrain`
- `FITS` or `PositionAngle`
- `Sequence`
- optional `TileCenter`
- `StartupCondition`
- `Constraints`
- `CompletionCondition`
- `Steps`

## Practical consequence

The earlier prototype runner output was too simplified to be trusted as EKOS-like:
- old `.esq` version was `2.0`
- old `.esl` version was `1.0`
- several required container elements were missing
- scheduler conditions were represented as flat text instead of nested EKOS condition nodes

The runner has now been updated to emit schema-aligned prototypes based on these findings.

## Remaining gap

This is still not the same as validating on the real observatory machine.
We still need:
- a genuine manually-saved `.esq`
- a genuine manually-saved `.esl`
- the pilot machine's actual profile / train / filter naming
- confirmation that generated files import cleanly in that real setup
