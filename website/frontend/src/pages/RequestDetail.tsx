import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../contexts/AuthContext';
import type { ObservationRequest } from '../api/types';
import styles from './RequestDetail.module.css';

export function RequestDetail() {
  const { id } = useParams<{ id: string }>();
  const { isStaff } = useAuth();
  const navigate = useNavigate();
  const [req, setReq] = useState<ObservationRequest | null>(null);
  const [loading, setLoading] = useState(true);
  const [rejectReason, setRejectReason] = useState('');
  const [showReject, setShowReject] = useState(false);
  const [acting, setActing] = useState(false);

  useEffect(() => {
    api.get<ObservationRequest>(`/requests/${id}`)
      .then(setReq)
      .catch(() => navigate('/'))
      .finally(() => setLoading(false));
  }, [id, navigate]);

  const approve = async () => {
    setActing(true);
    try {
      const updated = await api.post<ObservationRequest>(`/requests/${id}/approve`);
      setReq(updated);
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed');
    } finally {
      setActing(false);
    }
  };

  const reject = async () => {
    if (!rejectReason.trim()) return;
    setActing(true);
    try {
      const updated = await api.post<ObservationRequest>(`/requests/${id}/reject`, { reason: rejectReason });
      setReq(updated);
      setShowReject(false);
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed');
    } finally {
      setActing(false);
    }
  };

  if (loading || !req) return <p>Loading...</p>;

  return (
    <div className={styles.page}>
      <button onClick={() => navigate(-1)} className="btn btn-secondary btn-sm" style={{ marginBottom: '1rem' }}>
        Back
      </button>

      <div className={styles.headerRow}>
        <div>
          <h1 className={styles.heading}>{req.project_name}</h1>
          <p className={styles.meta}>
            By {req.user_name || 'Unknown'} &middot; {req.submitted_at ? new Date(req.submitted_at).toLocaleString('en-GB') : '-'}
            {req.telescope_name && <> &middot; {req.telescope_name}</>}
          </p>
        </div>
        <span className={`badge badge-${req.status}`}>{req.status}</span>
      </div>

      {req.description && (
        <div className={`card ${styles.section}`}>
          <h2 className={styles.sectionTitle}>Description</h2>
          <p>{req.description}</p>
        </div>
      )}

      <div className={`card ${styles.section}`}>
        <h2 className={styles.sectionTitle}>Targets ({req.targets.length})</h2>
        <div className={styles.targets}>
          {req.targets.map(t => (
            <div key={t.id} className={styles.target}>
              <div className={styles.targetName}>{t.target_name}</div>
              <div className={styles.targetDetail}>
                <span>RA: {t.ra.toFixed(4)}&deg;</span>
                <span>Dec: {t.dec.toFixed(4)}&deg;</span>
                <span>Exp: {t.exposure_seconds}s &times; {t.count}</span>
                <span>Bin: {t.binning}x{t.binning}</span>
              </div>
              <div className={styles.targetFilters}>
                {t.filters.map(f => <span key={f} className={styles.filterChip}>{f}</span>)}
              </div>
            </div>
          ))}
        </div>
      </div>

      {req.status === 'rejected' && req.rejected_reason && (
        <div className={`card ${styles.section}`} style={{ borderColor: 'var(--danger)' }}>
          <h2 className={styles.sectionTitle} style={{ color: 'var(--danger)' }}>Rejection Reason</h2>
          <p>{req.rejected_reason}</p>
          {req.approver_name && <p className={styles.meta}>By {req.approver_name}</p>}
        </div>
      )}

      {req.status === 'approved' && (
        <div className={`card ${styles.section}`} style={{ borderColor: 'var(--success)' }}>
          <h2 className={styles.sectionTitle} style={{ color: 'var(--success)' }}>Approved</h2>
          <p className={styles.meta}>
            By {req.approver_name || 'Unknown'} on {req.approved_at ? new Date(req.approved_at).toLocaleString('en-GB') : '-'}
          </p>
        </div>
      )}

      {isStaff && req.status === 'submitted' && (
        <div className={`card ${styles.section}`}>
          <h2 className={styles.sectionTitle}>Review</h2>
          <div className={styles.reviewActions}>
            <button onClick={approve} className="btn btn-success" disabled={acting}>
              {acting ? 'Processing...' : 'Approve'}
            </button>
            <button onClick={() => setShowReject(!showReject)} className="btn btn-danger" disabled={acting}>
              Reject
            </button>
          </div>
          {showReject && (
            <div className={styles.rejectForm}>
              <textarea value={rejectReason} onChange={e => setRejectReason(e.target.value)} placeholder="Reason for rejection..." rows={3} />
              <button onClick={reject} className="btn btn-danger btn-sm" disabled={acting || !rejectReason.trim()}>
                Confirm Rejection
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
