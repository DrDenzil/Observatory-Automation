import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import type { Job, ObservationRequest } from '../api/types';
import styles from './JobDetail.module.css';

interface JobWithRequest extends Job {
  request: ObservationRequest;
}

export function JobDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [job, setJob] = useState<JobWithRequest | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!id) return;
    api.get<Job>(`/jobs/${id}`)
      .then(j => {
        api.get<ObservationRequest>(`/requests/${j.request_id}`)
          .then(req => setJob({ ...j, request: req }))
          .catch(err => setError(err instanceof Error ? err.message : 'Failed to load request'));
      })
      .catch(err => setError(err instanceof Error ? err.message : 'Failed to load job'))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) return <p>Loading...</p>;
  if (error) return <p className={styles.error}>{error}</p>;
  if (!job) return <p>Job not found</p>;

  const duration = job.completed_at && job.started_at
    ? Math.round((new Date(job.completed_at).getTime() - new Date(job.started_at).getTime()) / 1000)
    : undefined;

  return (
    <div>
      <button onClick={() => navigate(-1)} className="btn btn-secondary btn-sm" style={{ marginBottom: '1rem' }}>
        ← Back
      </button>

      {/* Header */}
      <div className={styles.header}>
        <div>
          <h1 className={styles.heading}>{job.request.project_name}</h1>
          <p className={styles.meta}>
            Job {job.id.slice(0, 8)} · Request {job.request_id.slice(0, 8)}
            {job.queue_ref && <> · Queue Ref {job.queue_ref}</>}
          </p>
        </div>
        <div style={{ textAlign: 'right' }}>
          <span className={`badge badge-${job.status}`} style={{ fontSize: '1rem', padding: '0.5rem 0.8rem' }}>
            {job.status}
          </span>
        </div>
      </div>

      {/* Status Timeline */}
      <div className={`card ${styles.section}`}>
        <h2 className={styles.sectionTitle}>Timeline</h2>
        <div className={styles.timeline}>
          <div className={styles.timelineItem}>
            <span className={styles.timelineLabel}>Queued</span>
            <span className={styles.timelineDate}>{new Date(job.created_at).toLocaleString('en-GB')}</span>
          </div>
          {job.started_at && (
            <div className={styles.timelineItem}>
              <span className={styles.timelineLabel}>Started</span>
              <span className={styles.timelineDate}>{new Date(job.started_at).toLocaleString('en-GB')}</span>
            </div>
          )}
          {job.completed_at && (
            <div className={styles.timelineItem}>
              <span className={styles.timelineLabel}>Completed</span>
              <span className={styles.timelineDate}>{new Date(job.completed_at).toLocaleString('en-GB')}</span>
            </div>
          )}
          {duration && (
            <div className={styles.timelineItem}>
              <span className={styles.timelineLabel}>Duration</span>
              <span className={styles.timelineDate}>{formatDuration(duration)}</span>
            </div>
          )}
        </div>
      </div>

      {/* Error Message */}
      {job.error_message && (
        <div className={`card ${styles.errorCard}`}>
          <h2 className={styles.sectionTitle} style={{ color: 'var(--danger)' }}>⚠ Error</h2>
          <p className={styles.errorMessage}>{job.error_message}</p>
        </div>
      )}

      {/* Job Details Grid */}
      <div className={styles.detailsGrid}>
        {/* Request */}
        <div className={`card ${styles.detailCard}`}>
          <h3 className={styles.detailTitle}>Request</h3>
          <div className={styles.detailRow}>
            <span className={styles.label}>Observer</span>
            <span>{job.request.user_name}</span>
          </div>
          <div className={styles.detailRow}>
            <span className={styles.label}>Telescope</span>
            <span>{job.request.telescope_name || 'Unknown'}</span>
          </div>
          <div className={styles.detailRow}>
            <span className={styles.label}>Targets</span>
            <span>{job.request.targets.map(t => t.target_name).join(', ')}</span>
          </div>
          {job.request.description && (
            <div className={styles.detailRow}>
              <span className={styles.label}>Description</span>
              <span>{job.request.description}</span>
            </div>
          )}
        </div>

        {/* Execution */}
        <div className={`card ${styles.detailCard}`}>
          <h3 className={styles.detailTitle}>Execution</h3>
          <div className={styles.detailRow}>
            <span className={styles.label}>Scope</span>
            <span>{job.scope_id || '—'}</span>
          </div>
          <div className={styles.detailRow}>
            <span className={styles.label}>Status</span>
            <span>
              <span className={`badge badge-${job.status}`}>{job.status}</span>
            </span>
          </div>
          {job.started_at && (
            <div className={styles.detailRow}>
              <span className={styles.label}>Started</span>
              <span className={styles.date}>{new Date(job.started_at).toLocaleTimeString('en-GB')}</span>
            </div>
          )}
          {job.completed_at && (
            <div className={styles.detailRow}>
              <span className={styles.label}>Completed</span>
              <span className={styles.date}>{new Date(job.completed_at).toLocaleTimeString('en-GB')}</span>
            </div>
          )}
        </div>

        {/* Targets */}
        <div className={`card ${styles.detailCard}`}>
          <h3 className={styles.detailTitle}>Targets ({job.request.targets.length})</h3>
          <div className={styles.targetsList}>
            {job.request.targets.map(t => (
              <div key={t.id} className={styles.targetItem}>
                <div className={styles.targetName}>{t.target_name}</div>
                <div className={styles.targetMeta}>
                  <span>RA {t.ra.toFixed(4)}°</span>
                  <span>Dec {t.dec.toFixed(4)}°</span>
                  <span>Exp {t.exposure_seconds}s × {t.count}</span>
                </div>
                <div className={styles.targetFilters}>
                  {t.filters.map(f => (
                    <span key={f} className={styles.filterChip}>{f}</span>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Status Message */}
      {job.status === 'queued' && (
        <div className={`card ${styles.infoCard}`}>
          <p className={styles.infoText}>
            ⏱ Waiting in queue. A runner will pick up this job when ready.
          </p>
        </div>
      )}

      {job.status === 'running' && (
        <div className={`card ${styles.infoCard}`}>
          <p className={styles.infoText}>
            🔄 Capturing data on {job.scope_id || 'a scope'}. Check back soon for updates.
          </p>
        </div>
      )}

      {job.status === 'completed' && (
        <div className={`card ${styles.infoCard}`} style={{ borderColor: 'var(--success)', background: 'color-mix(in srgb, var(--success) 8%, transparent)' }}>
          <p className={styles.infoText}>
            ✓ Job completed successfully! Images are being processed and added to the archive.
          </p>
        </div>
      )}

      {job.status === 'failed' && (
        <div className={`card ${styles.errorCard}`}>
          <p className={styles.infoText}>
            ✗ Job failed. Please review the error above and contact support if needed.
          </p>
        </div>
      )}
    </div>
  );
}

function formatDuration(seconds: number): string {
  if (seconds < 60) return `${seconds}s`;
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
}
