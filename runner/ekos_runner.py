#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import os
import socket
import sys
from dataclasses import dataclass
from datetime import UTC, datetime
from pathlib import Path
from typing import Any
from xml.sax.saxutils import escape


DEFAULT_EKOS_PROFILE = 'Local'
DEFAULT_SEQUENCE_FORMAT = 'FITS'
DEFAULT_SEQUENCE_ENCODING = 'FITS'
DEFAULT_FRAME_TYPE = 'Light'
DEFAULT_UPLOAD_MODE = 0
DEFAULT_MIN_ALTITUDE = 15


@dataclass
class RunnerPaths:
    incoming: Path
    claimed: Path
    completed: Path
    failed: Path
    logs: Path
    generated: Path


def utc_now() -> str:
    return datetime.now(UTC).isoformat()


def ensure_dirs(paths: RunnerPaths) -> None:
    for path in (paths.incoming, paths.claimed, paths.completed, paths.failed, paths.logs, paths.generated):
        path.mkdir(parents=True, exist_ok=True)


def load_json(path: Path) -> dict[str, Any]:
    with path.open('r', encoding='utf-8') as fh:
        return json.load(fh)


def write_json(path: Path, payload: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(path.suffix + f'.tmp.{os.getpid()}')
    with tmp.open('w', encoding='utf-8') as fh:
        json.dump(payload, fh, indent=2)
    tmp.replace(path)


def write_text(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding='utf-8')


def claim_job(job_path: Path, claimed_dir: Path, runner_id: str) -> Path:
    claimed_path = claimed_dir / job_path.name
    job_path.replace(claimed_path)
    payload = load_json(claimed_path)
    payload.setdefault('runner', {})
    payload['runner']['state'] = 'claimed'
    payload['runner']['claimed_at'] = utc_now()
    payload['runner']['claimed_by'] = runner_id
    write_json(claimed_path, payload)
    return claimed_path


def pick_next_job(incoming_dir: Path) -> Path | None:
    jobs = sorted(p for p in incoming_dir.glob('*.json') if not p.name.endswith('.meta.json'))
    return jobs[0] if jobs else None


def flatten_targets(job: dict[str, Any]) -> list[dict[str, Any]]:
    items: list[dict[str, Any]] = []
    for plan in job.get('job', {}).get('plans', []):
        for target in plan.get('targets', []):
            items.append({
                'plan_id': plan.get('plan_id'),
                'project': plan.get('project'),
                'schedule': plan.get('schedule', {}),
                'target': target,
            })
    return items


def as_float(value: Any, default: float = 0.0) -> float:
    try:
        if value is None or value == '':
            return default
        return float(value)
    except (TypeError, ValueError):
        return default


def as_int(value: Any, default: int = 0) -> int:
    try:
        if value is None or value == '':
            return default
        return int(value)
    except (TypeError, ValueError):
        return default


def ctext(value: Any) -> str:
    if isinstance(value, float):
        return f'{value:g}'
    return str(value)


def pick_target_name(target: dict[str, Any]) -> str:
    return str(target.get('name') or target.get('target_name') or 'Unknown')


def render_sequence_job(target: dict[str, Any], picture: dict[str, Any], output_dir: Path) -> list[str]:
    target_name = pick_target_name(target)
    exposure = as_float(picture.get('exposure_time'), 0.0)
    count = max(as_int(picture.get('count'), 1), 1)
    binning = max(as_int(picture.get('binning'), 1), 1)
    filter_name = str(picture.get('filter') or '')
    gain = picture.get('gain')
    offset = picture.get('offset')
    rotation = picture.get('rotation')
    temperature = picture.get('temperature')
    placeholder = f'%t_%F_%e_%D'

    lines = [
        '  <Job>',
        f'    <Exposure>{ctext(exposure)}</Exposure>',
        f'    <Format>{DEFAULT_SEQUENCE_FORMAT}</Format>',
        f'    <Encoding>{DEFAULT_SEQUENCE_ENCODING}</Encoding>',
        '    <Binning>',
        f'      <X>{binning}</X>',
        f'      <Y>{binning}</Y>',
        '    </Binning>',
        '    <Frame>',
        '      <X>0</X>',
        '      <Y>0</Y>',
        '      <W>0</W>',
        '      <H>0</H>',
        '    </Frame>',
    ]

    if temperature not in (None, ''):
        lines.append(f'    <Temperature force="true">{escape(ctext(temperature))}</Temperature>')
    if filter_name:
        lines.append(f'    <Filter>{escape(filter_name)}</Filter>')

    lines.extend([
        f'    <Type>{DEFAULT_FRAME_TYPE}</Type>',
        f'    <Count>{count}</Count>',
        '    <Delay>0</Delay>',
        f'    <TargetName>{escape(target_name)}</TargetName>',
        '    <GuideDitherPerJob>-1</GuideDitherPerJob>',
        f'    <FITSDirectory>{escape(str(output_dir))}</FITSDirectory>',
        f'    <PlaceholderFormat>{escape(placeholder)}</PlaceholderFormat>',
        '    <PlaceholderSuffix>0</PlaceholderSuffix>',
        f'    <UploadMode>{DEFAULT_UPLOAD_MODE}</UploadMode>',
    ])

    if gain not in (None, '') or offset not in (None, ''):
        lines.append('    <Properties>')
        lines.append('      <PropertyVector name="CCD_CONTROLS">')
        if gain not in (None, ''):
            lines.append(f'        <OneElement name="Gain" type="number">{escape(ctext(gain))}</OneElement>')
        if offset not in (None, ''):
            lines.append(f'        <OneElement name="Offset" type="number">{escape(ctext(offset))}</OneElement>')
        lines.append('      </PropertyVector>')
        lines.append('    </Properties>')
    else:
        lines.append('    <Properties>')
        lines.append('    </Properties>')

    lines.extend([
        '    <Calibration>',
        '      <PreAction>',
        '        <Type>0</Type>',
        '      </PreAction>',
        '      <FlatDuration dark="false">',
        '        <Type>Manual</Type>',
        '      </FlatDuration>',
        '    </Calibration>',
    ])

    if rotation not in (None, ''):
        lines.append(f'    <Rotation>{escape(ctext(rotation))}</Rotation>')

    lines.append('  </Job>')
    return lines


def build_sequence_file(job: dict[str, Any], bundle_dir: Path) -> Path:
    queue_ref = job['queue_ref']
    output_dir = bundle_dir / 'captures'
    output_dir.mkdir(parents=True, exist_ok=True)
    path = bundle_dir / f'{queue_ref}.esq'
    lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        "<SequenceQueue version='2.4'>",
        f'  <!-- Prototype sequence for {escape(queue_ref)} aligned to KStars/Ekos schema -->',
    ]

    observer = job.get('job', {}).get('project') or job.get('telescope', {}).get('id') or ''
    if observer:
        lines.append(f'  <Observer>{escape(str(observer))}</Observer>')
    lines.extend([
        '  <GuideDeviation enabled="false">1.5</GuideDeviation>',
        '  <GuideStartDeviation enabled="false">1.5</GuideStartDeviation>',
        '  <HFRCheck enabled="false">',
        '    <HFRDeviation>0</HFRDeviation>',
        '    <HFRCheckAlgorithm>0</HFRCheckAlgorithm>',
        '    <HFRCheckThreshold>0</HFRCheckThreshold>',
        '    <HFRCheckFrames>0</HFRCheckFrames>',
        '  </HFRCheck>',
        '  <RefocusOnTemperatureDelta enabled="false">0</RefocusOnTemperatureDelta>',
        '  <RefocusEveryN enabled="false">0</RefocusEveryN>',
        '  <RefocusOnMeridianFlip enabled="false"/>',
    ])

    for item in flatten_targets(job):
        target = item['target']
        pictures = target.get('pictures', []) or [{}]
        for pic in pictures:
            lines.extend(render_sequence_job(target, pic, output_dir))

    lines.append('</SequenceQueue>')
    write_text(path, '\n'.join(lines) + '\n')
    return path


def build_scheduler_file(job: dict[str, Any], bundle_dir: Path, sequence_path: Path) -> Path:
    queue_ref = job['queue_ref']
    path = bundle_dir / f'{queue_ref}.esl'
    profile = str(job.get('ekos_profile') or job.get('machine_id') or DEFAULT_EKOS_PROFILE)
    lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        "<SchedulerList version='2.2'>",
        f'  <!-- Prototype scheduler for {escape(queue_ref)} aligned to KStars/Ekos schema -->',
        f'  <Profile>{escape(profile)}</Profile>',
    ]

    for item in flatten_targets(job):
        target = item['target']
        schedule = item.get('schedule', {}) or {}
        time_range = schedule.get('time_range', {}) or {}
        earliest = time_range.get('earliest')
        latest = time_range.get('latest')
        min_altitude = as_float(schedule.get('altitude'), DEFAULT_MIN_ALTITUDE)
        moon_sep = as_float(schedule.get('moon_separation'), 0.0)
        steps = ['Track', 'Focus', 'Align', 'Guide']
        target_name = pick_target_name(target)
        ra_value = target.get('ra') or target.get('ra_hours') or target.get('ra_deg') or ''
        dec_value = target.get('dec') or target.get('dec_degs') or target.get('dec_deg') or ''

        lines.extend([
            '  <Job>',
            '    <JobType lead="true"/>',
            f'    <Name>{escape(target_name)}</Name>',
            f'    <Group>{escape(str(item.get("project") or job.get("queue_ref") or "default"))}</Group>',
            '    <Coordinates>',
            f'      <J2000RA>{escape(ctext(ra_value))}</J2000RA>',
            f'      <J2000DE>{escape(ctext(dec_value))}</J2000DE>',
            '    </Coordinates>',
            '    <PositionAngle>0</PositionAngle>',
            f'    <Sequence>{escape(str(sequence_path))}</Sequence>',
            '    <StartupCondition>',
        ])

        if earliest:
            lines.append(f'      <Condition value="{escape(str(earliest))}">At</Condition>')
        else:
            lines.append('      <Condition>ASAP</Condition>')

        lines.extend([
            '    </StartupCondition>',
            '    <Constraints>',
            f'      <Constraint value="{ctext(min_altitude)}">MinimumAltitude</Constraint>',
        ])
        if moon_sep > 0:
            lines.append(f'      <Constraint value="{ctext(moon_sep)}">MoonSeparation</Constraint>')
        lines.extend([
            '      <Constraint>EnforceTwilight</Constraint>',
            '    </Constraints>',
            '    <CompletionCondition>',
        ])

        if latest:
            lines.append(f'      <Condition value="{escape(str(latest))}">At</Condition>')
        else:
            lines.append('      <Condition>Sequence</Condition>')

        lines.extend([
            '    </CompletionCondition>',
            '    <Steps>',
        ])
        for step in steps:
            lines.append(f'      <Step>{step}</Step>')
        lines.extend([
            '    </Steps>',
            '  </Job>',
        ])

    lines.extend([
        '  <SchedulerAlgorithm value="1"/>',
        '  <ErrorHandlingStrategy value="0">',
        '    <delay>0</delay>',
        '  </ErrorHandlingStrategy>',
        '  <StartupProcedure enabled="false">',
        '  </StartupProcedure>',
        '  <ShutdownProcedure enabled="false">',
        '  </ShutdownProcedure>',
        '</SchedulerList>',
    ])
    write_text(path, '\n'.join(lines) + '\n')
    return path


def build_target_summaries(job: dict[str, Any], targets_dir: Path) -> list[str]:
    written: list[str] = []
    for index, item in enumerate(flatten_targets(job), start=1):
        target = item['target']
        summary = {
            'index': index,
            'plan_id': item.get('plan_id'),
            'project': item.get('project'),
            'name': target.get('name'),
            'ra': target.get('ra'),
            'dec': target.get('dec'),
            'ra_deg': target.get('ra_deg'),
            'dec_deg': target.get('dec_deg'),
            'pictures': target.get('pictures', []),
            'exposure_seconds_total': target.get('exposure_seconds_total'),
            'schedule': item.get('schedule', {}),
        }
        path = targets_dir / f'target-{index:02d}.json'
        write_json(path, summary)
        written.append(str(path))
    return written


def build_manifest(job: dict[str, Any], bundle_dir: Path, sequence_path: Path, scheduler_path: Path, target_files: list[str]) -> Path:
    manifest = {
        'queue_ref': job.get('queue_ref'),
        'machine_id': job.get('machine_id'),
        'generated_at': utc_now(),
        'job_type': job.get('job_type'),
        'project': job.get('job', {}).get('project'),
        'telescope': job.get('telescope'),
        'warnings': job.get('warnings', []),
        'artifacts': {
            'sequence_file': str(sequence_path),
            'scheduler_file': str(scheduler_path),
            'target_files': target_files,
        },
    }
    path = bundle_dir / 'manifest.json'
    write_json(path, manifest)
    return path


def prepare_bundle(job: dict[str, Any], paths: RunnerPaths) -> dict[str, str | list[str]]:
    queue_ref = job['queue_ref']
    bundle_dir = paths.generated / queue_ref
    targets_dir = bundle_dir / 'targets'
    bundle_dir.mkdir(parents=True, exist_ok=True)
    targets_dir.mkdir(parents=True, exist_ok=True)

    sequence_path = build_sequence_file(job, bundle_dir)
    scheduler_path = build_scheduler_file(job, bundle_dir, sequence_path)
    target_files = build_target_summaries(job, targets_dir)
    manifest_path = build_manifest(job, bundle_dir, sequence_path, scheduler_path, target_files)

    readme = bundle_dir / 'README.txt'
    write_text(
        readme,
        (
            f'Prototype EKOS prep bundle for {queue_ref}\n\n'
            'Contains placeholder EKOS-oriented artifacts plus per-target summaries.\n'
            'Use this bundle to inspect whether the server-side export contains enough information\n'
            'for the target machine before attempting live EKOS execution.\n'
        ),
    )

    return {
        'bundle_dir': str(bundle_dir),
        'sequence_file': str(sequence_path),
        'scheduler_file': str(scheduler_path),
        'manifest_file': str(manifest_path),
        'target_files': target_files,
        'readme_file': str(readme),
    }


def process_job(claimed_path: Path, paths: RunnerPaths, runner_id: str, dry_run: bool) -> Path:
    job = load_json(claimed_path)
    expected_machine = job.get('machine_id')
    if expected_machine != runner_id:
        raise RuntimeError(f'Job {claimed_path.name} targets {expected_machine}, not {runner_id}')

    artifacts = prepare_bundle(job, paths)

    job.setdefault('runner', {})
    job['runner']['state'] = 'prepared' if dry_run else 'ready_to_run'
    job['runner']['prepared_at'] = utc_now()
    job['runner']['prepared_by'] = runner_id
    job['runner']['artifact_bundle'] = artifacts.get('bundle_dir')
    job['runner']['ekos_sequence_file'] = artifacts.get('sequence_file')
    job['runner']['ekos_scheduler_file'] = artifacts.get('scheduler_file')
    job['runner']['manifest_file'] = artifacts.get('manifest_file')
    job['runner']['target_files'] = artifacts.get('target_files')
    write_json(claimed_path, job)

    destination = paths.completed / claimed_path.name
    claimed_path.replace(destination)
    return destination


def fail_job(claimed_path: Path, paths: RunnerPaths, error: str) -> Path:
    payload = load_json(claimed_path)
    payload.setdefault('runner', {})
    payload['runner']['state'] = 'failed'
    payload['runner']['failed_at'] = utc_now()
    payload['runner']['error'] = error
    write_json(claimed_path, payload)
    destination = paths.failed / claimed_path.name
    claimed_path.replace(destination)
    return destination


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description='Prototype EKOS job runner for Ubuntu 24.04 telescope machines')
    parser.add_argument('--machine-id', required=True, help='Machine ID, e.g. scope06')
    parser.add_argument('--queue-root', default='/var/lib/ekos-runner/jobs', help='Local queue root')
    parser.add_argument('--dry-run', action='store_true', help='Prepare EKOS artifact bundle but do not launch EKOS')
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    machine_id = args.machine_id.strip()
    if not machine_id:
        print('machine-id must not be empty', file=sys.stderr)
        return 2

    root = Path(args.queue_root).expanduser().resolve() / machine_id
    paths = RunnerPaths(
        incoming=root / 'incoming',
        claimed=root / 'claimed',
        completed=root / 'completed',
        failed=root / 'failed',
        logs=root / 'logs',
        generated=root / 'generated',
    )
    ensure_dirs(paths)

    runner_id = machine_id
    job_path = pick_next_job(paths.incoming)
    if job_path is None:
        print(f'[{utc_now()}] No jobs waiting for {machine_id}')
        return 0

    print(f'[{utc_now()}] Claiming {job_path.name} on {socket.gethostname()} as {runner_id}')
    claimed_path = claim_job(job_path, paths.claimed, runner_id)

    try:
        completed_path = process_job(claimed_path, paths, runner_id, args.dry_run)
        print(f'[{utc_now()}] Prepared {completed_path.name}')
        return 0
    except Exception as exc:
        failed_path = fail_job(claimed_path, paths, str(exc))
        print(f'[{utc_now()}] Failed {failed_path.name}: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
