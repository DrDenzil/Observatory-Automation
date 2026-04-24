from __future__ import annotations

import json
import tempfile
from datetime import UTC, datetime
from pathlib import Path

import pytest

from ekos_runner import (
    RunnerPaths,
    as_float,
    as_int,
    build_manifest,
    build_scheduler_file,
    build_sequence_file,
    build_target_summaries,
    claim_job,
    ctext,
    ensure_dirs,
    fail_job,
    flatten_targets,
    load_json,
    parse_args,
    pick_next_job,
    pick_target_name,
    prepare_bundle,
    process_job,
    render_sequence_job,
    utc_now,
    write_json,
    write_text,
)


@pytest.fixture
def temp_root(tmp_path: Path) -> RunnerPaths:
    return RunnerPaths(
        incoming=tmp_path / 'incoming',
        claimed=tmp_path / 'claimed',
        completed=tmp_path / 'completed',
        failed=tmp_path / 'failed',
        logs=tmp_path / 'logs',
        generated=tmp_path / 'generated',
    )


@pytest.fixture
def sample_job() -> dict:
    return {
        'queue_ref': 'test_job_001',
        'machine_id': 'scope06',
        'job_type': 'observation',
        'job': {
            'project': 'Test Project',
            'plans': [
                {
                    'plan_id': 'plan_1',
                    'project': 'M42',
                    'schedule': {
                        'time_range': {
                            'earliest': '22:00',
                            'latest': '04:00',
                        },
                        'altitude': 30.0,
                        'moon_separation': 45.0,
                    },
                    'targets': [
                        {
                            'name': 'M42',
                            'ra': '05:35:17',
                            'dec': '-05:23:28',
                            'pictures': [
                                {
                                    'exposure_time': 120.0,
                                    'count': 10,
                                    'binning': 2,
                                    'filter': 'Luminance',
                                    'gain': 139,
                                },
                                {
                                    'exposure_time': 60.0,
                                    'count': 5,
                                    'binning': 1,
                                    'filter': 'Red',
                                },
                            ],
                        },
                    ],
                },
            ],
        },
        'telescope': {
            'id': 'scope06',
            'name': 'Telescope 6',
        },
    }


class TestUtcNow:
    def test_returns_iso_format_string(self):
        result = utc_now()
        assert isinstance(result, str)
        assert 'T' in result
        assert '+00:00' in result or 'Z' in result

    def test_valid_datetime_parseable(self):
        result = utc_now()
        dt = datetime.fromisoformat(result)
        assert dt.tzinfo is not None


class TestEnsureDirs:
    def test_creates_all_directories(self, temp_root: RunnerPaths):
        ensure_dirs(temp_root)
        for path in (temp_root.incoming, temp_root.claimed, temp_root.completed,
                     temp_root.failed, temp_root.logs, temp_root.generated):
            assert path.is_dir()

    def test_does_not_fail_if_exists(self, temp_root: RunnerPaths):
        ensure_dirs(temp_root)
        ensure_dirs(temp_root)
        assert temp_root.incoming.is_dir()


class TestJsonOperations:
    def test_write_and_load_json(self, tmp_path: Path):
        path = tmp_path / 'test.json'
        data = {'key': 'value', 'number': 42}
        write_json(path, data)
        result = load_json(path)
        assert result == data

    def test_load_json_raises_on_missing(self, tmp_path: Path):
        path = tmp_path / 'missing.json'
        with pytest.raises(FileNotFoundError):
            load_json(path)

    def test_write_text(self, tmp_path: Path):
        path = tmp_path / 'test.txt'
        content = 'Hello, World!'
        write_text(path, content)
        assert path.read_text() == content


class TestClaimJob:
    def test_claim_job_moves_file(self, temp_root: RunnerPaths):
        ensure_dirs(temp_root)
        job_path = temp_root.incoming / 'job1.json'
        write_json(job_path, {'queue_ref': 'job1'})

        claimed = claim_job(job_path, temp_root.claimed, 'scope06')
        assert not job_path.exists()
        assert claimed.exists()
        assert temp_root.incoming.exists()  # parent dir still exists

    def test_claim_job_sets_metadata(self, temp_root: RunnerPaths):
        ensure_dirs(temp_root)
        job_path = temp_root.incoming / 'job2.json'
        write_json(job_path, {'queue_ref': 'job2'})

        claimed = claim_job(job_path, temp_root.claimed, 'scope06')
        data = load_json(claimed)
        assert data['runner']['state'] == 'claimed'
        assert data['runner']['claimed_by'] == 'scope06'
        assert 'claimed_at' in data['runner']

    def test_claim_job_preserves_existing_data(self, temp_root: RunnerPaths):
        ensure_dirs(temp_root)
        job_path = temp_root.incoming / 'job3.json'
        write_json(job_path, {'queue_ref': 'job3', 'existing_key': 'existing_value'})

        claimed = claim_job(job_path, temp_root.claimed, 'scope06')
        data = load_json(claimed)
        assert data['existing_key'] == 'existing_value'


class TestPickNextJob:
    def test_returns_none_when_empty(self, temp_root: RunnerPaths):
        ensure_dirs(temp_root)
        assert pick_next_job(temp_root.incoming) is None

    def test_returns_oldest_job(self, temp_root: RunnerPaths):
        ensure_dirs(temp_root)
        write_json(temp_root.incoming / 'a_job.json', {'queue_ref': 'a'})
        write_json(temp_root.incoming / 'b_job.json', {'queue_ref': 'b'})
        write_json(temp_root.incoming / 'c_job.json', {'queue_ref': 'c'})

        result = pick_next_job(temp_root.incoming)
        assert result is not None
        assert result.name == 'a_job.json'

    def test_ignores_meta_files(self, temp_root: RunnerPaths):
        ensure_dirs(temp_root)
        write_json(temp_root.incoming / 'real_job.json', {'queue_ref': 'real'})
        write_json(temp_root.incoming / 'real_job.meta.json', {'meta': True})

        result = pick_next_job(temp_root.incoming)
        assert result is not None
        assert result.name == 'real_job.json'


class TestFlattenTargets:
    def test_flatten_empty_job(self):
        result = flatten_targets({})
        assert result == []

    def test_flatten_single_target(self, sample_job: dict):
        result = flatten_targets(sample_job)
        assert len(result) == 1  # one target (pictures are grouped under target)
        assert result[0]['plan_id'] == 'plan_1'
        assert result[0]['project'] == 'M42'
        assert result[0]['target']['name'] == 'M42'

    def test_flatten_multiple_plans(self):
        job = {
            'job': {
                'plans': [
                    {'plan_id': 'p1', 'targets': [{'name': 'A'}]},
                    {'plan_id': 'p2', 'targets': [{'name': 'B'}, {'name': 'C'}]},
                ]
            }
        }
        result = flatten_targets(job)
        assert len(result) == 3


class TestTypeConversion:
    def test_as_float_valid(self):
        assert as_float(3.14) == 3.14
        assert as_float('42.5') == 42.5
        assert as_float(100) == 100.0

    def test_as_float_default(self):
        assert as_float(None) == 0.0
        assert as_float('') == 0.0
        assert as_float('invalid') == 0.0
        assert as_float(None, default=5.0) == 5.0

    def test_as_int_valid(self):
        assert as_int(5) == 5
        assert as_int(5.9) == 5
        assert as_int('10') == 10

    def test_as_int_default(self):
        assert as_int(None) == 0
        assert as_int('') == 0
        assert as_int('invalid') == 0
        assert as_int(None, default=99) == 99


class TestPickTargetName:
    def test_prefers_name(self):
        target = {'name': 'M42', 'target_name': 'Orion'}
        assert pick_target_name(target) == 'M42'

    def test_falls_back_to_target_name(self):
        target = {'target_name': 'Orion'}
        assert pick_target_name(target) == 'Orion'

    def test_returns_unknown_when_missing(self):
        assert pick_target_name({}) == 'Unknown'


class TestCtext:
    def test_float_formatting(self):
        assert ctext(3.0) == '3'
        assert ctext(3.14) == '3.14'
        assert ctext(0.0001234) == '0.0001234'

    def test_string_passthrough(self):
        assert ctext('hello') == 'hello'
        assert ctext('05:35:17') == '05:35:17'


class TestRenderSequenceJob:
    def test_generates_valid_xml_lines(self, tmp_path: Path):
        target = {'name': 'M42'}
        picture = {
            'exposure_time': 120.0,
            'count': 10,
            'binning': 2,
            'filter': 'Luminance',
        }
        output_dir = tmp_path / 'captures'
        lines = render_sequence_job(target, picture, output_dir)
        xml = '\n'.join(lines)

        assert '  <Job>' in xml
        assert '<Exposure>120</Exposure>' in xml
        assert '<Count>10</Count>' in xml
        assert '<Binning>' in xml
        assert '<Filter>Luminance</Filter>' in xml
        assert '<TargetName>M42</TargetName>' in xml
        assert '  </Job>' in xml

    def test_includes_gain_and_offset(self, tmp_path: Path):
        target = {'name': 'Test'}
        picture = {'exposure_time': 60, 'count': 1, 'gain': 139, 'offset': 50}
        lines = render_sequence_job(target, picture, tmp_path)

        assert 'Gain' in ''.join(lines)
        assert 'Offset' in ''.join(lines)

    def test_omits_optional_when_empty(self, tmp_path: Path):
        target = {'name': 'Test'}
        picture = {'exposure_time': 60, 'count': 1}
        lines = render_sequence_job(target, picture, tmp_path)

        xml = ''.join(lines)
        assert '<Filter>' not in xml
        assert '<Properties>' in xml


class TestBuildSequenceFile:
    def test_creates_esq_file(self, sample_job: dict, tmp_path: Path):
        path = build_sequence_file(sample_job, tmp_path)
        assert path.suffix == '.esq'
        assert path.exists()

    def test_esq_contains_sequence_jobs(self, sample_job: dict, tmp_path: Path):
        build_sequence_file(sample_job, tmp_path)
        esq_files = list(tmp_path.glob('*.esq'))
        assert len(esq_files) == 1

        content = esq_files[0].read_text()
        assert '<?xml version="1.0"' in content
        assert '<Job>' in content
        assert '</SequenceQueue>' in content

    def test_creates_captures_subdirectory(self, sample_job: dict, tmp_path: Path):
        build_sequence_file(sample_job, tmp_path)
        assert (tmp_path / 'captures').is_dir()


class TestBuildSchedulerFile:
    def test_creates_esl_file(self, sample_job: dict, tmp_path: Path):
        seq_path = tmp_path / 'test.esq'
        path = build_scheduler_file(sample_job, tmp_path, seq_path)
        assert path.suffix == '.esl'
        assert path.exists()

    def test_esl_contains_target_info(self, sample_job: dict, tmp_path: Path):
        seq_path = tmp_path / 'test.esq'
        build_scheduler_file(sample_job, tmp_path, seq_path)

        esl_files = list(tmp_path.glob('*.esl'))
        content = esl_files[0].read_text()

        assert '<Name>M42</Name>' in content
        assert '<Profile>' in content
        assert '<Steps>' in content

    def test_includes_constraints(self, sample_job: dict, tmp_path: Path):
        seq_path = tmp_path / 'test.esq'
        build_scheduler_file(sample_job, tmp_path, seq_path)

        esl_files = list(tmp_path.glob('*.esl'))
        content = esl_files[0].read_text()

        assert 'MinimumAltitude' in content
        assert 'MoonSeparation' in content
        assert 'EnforceTwilight' in content


class TestBuildTargetSummaries:
    def test_creates_target_files(self, sample_job: dict, tmp_path: Path):
        targets_dir = tmp_path / 'targets'
        files = build_target_summaries(sample_job, targets_dir)

        assert len(files) == 1  # one target (pictures grouped under target)
        for f in files:
            assert Path(f).exists()

    def test_target_files_contain_data(self, sample_job: dict, tmp_path: Path):
        targets_dir = tmp_path / 'targets'
        build_target_summaries(sample_job, targets_dir)

        target_files = sorted(targets_dir.glob('target-*.json'))
        data = load_json(target_files[0])
        assert data['name'] == 'M42'
        assert data['plan_id'] == 'plan_1'


class TestBuildManifest:
    def test_creates_manifest(self, sample_job: dict, tmp_path: Path):
        seq_path = tmp_path / 'test.esq'
        sch_path = tmp_path / 'test.esl'
        target_files = ['file1.json', 'file2.json']

        manifest_path = build_manifest(sample_job, tmp_path, seq_path, sch_path, target_files)
        assert manifest_path.name == 'manifest.json'
        assert manifest_path.exists()

    def test_manifest_contains_required_fields(self, sample_job: dict, tmp_path: Path):
        seq_path = tmp_path / 'test.esq'
        sch_path = tmp_path / 'test.esl'

        manifest_path = build_manifest(sample_job, tmp_path, seq_path, sch_path, [])
        data = load_json(manifest_path)

        assert 'queue_ref' in data
        assert 'generated_at' in data
        assert 'artifacts' in data
        assert 'sequence_file' in data['artifacts']
        assert 'scheduler_file' in data['artifacts']


class TestPrepareBundle:
    def test_prepare_bundle_creates_all_artifacts(self, sample_job: dict, temp_root: RunnerPaths):
        ensure_dirs(temp_root)
        artifacts = prepare_bundle(sample_job, temp_root)

        assert 'bundle_dir' in artifacts
        assert Path(artifacts['bundle_dir']).is_dir()
        assert Path(artifacts['sequence_file']).exists()
        assert Path(artifacts['scheduler_file']).exists()
        assert Path(artifacts['manifest_file']).exists()
        assert Path(artifacts['readme_file']).exists()


class TestProcessJob:
    def test_process_job_succeeds_with_matching_machine(self, sample_job: dict, temp_root: RunnerPaths):
        ensure_dirs(temp_root)
        write_json(temp_root.incoming / 'job.json', sample_job)

        claimed_path = claim_job(temp_root.incoming / 'job.json', temp_root.claimed, 'scope06')
        result = process_job(claimed_path, temp_root, 'scope06', dry_run=True)

        assert result.exists()
        data = load_json(result)
        assert data['runner']['state'] == 'prepared'  # dry_run

    def test_process_job_fails_with_mismatched_machine(self, sample_job: dict, temp_root: RunnerPaths):
        ensure_dirs(temp_root)
        write_json(temp_root.incoming / 'job.json', sample_job)

        claimed_path = claim_job(temp_root.incoming / 'job.json', temp_root.claimed, 'scope06')

        with pytest.raises(RuntimeError, match='targets .* not'):
            process_job(claimed_path, temp_root, 'wrong_machine', dry_run=True)


class TestFailJob:
    def test_fail_job_moves_to_failed_dir(self, temp_root: RunnerPaths):
        ensure_dirs(temp_root)
        write_json(temp_root.incoming / 'job.json', {'queue_ref': 'job'})
        claimed_path = claim_job(temp_root.incoming / 'job.json', temp_root.claimed, 'scope06')

        result = fail_job(claimed_path, temp_root, 'Test error')

        assert result.parent == temp_root.failed
        data = load_json(result)
        assert data['runner']['state'] == 'failed'
        assert data['runner']['error'] == 'Test error'
        assert 'failed_at' in data['runner']


class TestParseArgs:
    def test_machine_id_required(self, monkeypatch):
        monkeypatch.setattr('sys.argv', ['ekos_runner.py'])
        with pytest.raises(SystemExit):
            parse_args()

    def test_queue_root_has_default(self, monkeypatch):
        monkeypatch.setattr('sys.argv', ['ekos_runner.py', '--machine-id', 'scope06'])
        args = parse_args()
        assert args.queue_root == '/var/lib/ekos-runner/jobs'

    def test_dry_run_flag(self, monkeypatch):
        monkeypatch.setattr('sys.argv', ['ekos_runner.py', '--machine-id', 'scope06', '--dry-run'])
        args = parse_args()
        assert args.dry_run is True
