import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import type { ObservationRequest, Job, Scope } from '../api/types';
import { ScopePanel } from '../components/ScopePanel';
import styles from './StaffDashboard.module.css';

export function StaffDashboard() {
  const [pending, setPending] = useState<ObservationRequest[]>([]);
  const [recent, setRecent] = useState<ObservationRequest[]>([]);
  const [jobs, setJobs] = useState<Job[]>([]);
  const [scopes, setScopes] = useState<Scope[]>([]);
  const [loading, setLoading] = useState(true);
  const [dispatchScopeId, setDispatchScopeId] = useState<Record<string, string>>({});
  const [dispatching, setDispatching] = useState<string | null>(null);
  const [dispatchError, setDispatchError] = useState<string | null>(null);

  const load = async () => {
    try {
      const [p, all, j, s] = await Promise.all([
        api.get<ObservationRequest[]>('/requests?status=submitted'),
        api.get<ObservationRequest[]>('/requests?limit=20'),
        api.get<Job[]>('/jobs'),
        api.get<Scope[]>('/scopes'),
      ]);
      setPending(p);
      setRecent(all.filter(r => r.status !== 'submitted'));
      setJobs(j);
      setScopes(s);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  // Refresh scope status (and the rest) on an interval so live runner state shows.
  const refreshScopes = async () => {
    try {
      setScopes(await api.get<Scope[]>('/scopes'));
    } catch (err) {
      console.error(err);
    }
  };

  useEffect(() => {
    load();
    const timer = setInterval(refreshScopes, 5000);
    return () => clearInterval(timer);
  }, []);

  const approve = async (id: string) => {
    await api.post(`/requests/${id}/approve`);
    load();
  };

  const dispatch = async (jobId: string) => {
    const scopeId = dispatchScopeId[jobId];
    if (!scopeId) return;
    setDispatching(jobId);
    setDispatchError(null);
    try {
      await api.post(`/jobs/${jobId}/dispatch`, { scope_id: scopeId });
      await load();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Dispatch failed';
      setDispatchError(msg);
    } finally {
      setDispatching(null);
    }
  };

  if (loading) return <p>Loading...</p>;

  const activeJobs = jobs.filter(j => j.status === 'queued' || j.status === 'running');
  const completedJobs = jobs.filter(j => j.status === 'completed' || j.status === 'failed');
  const idleScopes = scopes.filter(s => s.online && s.state === 'idle');

  return (
    <div>
      <h1 className={styles.heading}>Staff Dashboard</h1>

      <div className={`card ${styles.section}`}>
        <h2 className={styles.sectionTitle}>
          Telescope Control
          {scopes.length > 0 && <span className={styles.count}>{scopes.filter(s => s.online).length}/{scopes.length} online</span>}
        </h2>
        <ScopePanel scopes={scopes} />
      </div>

      <div className={`card ${styles.section}`}>
        <h2 className={styles.sectionTitle}>
          Pending Approval
          {pending.length > 0 && <span className={styles.count}>{pending.length}</span>}
        </h2>

        {pending.length === 0 ? (
          <p className={styles.empty}>No requests awaiting approval.</p>
        ) : (
          <table className={styles.table}>
            <thead>
              <tr>
                <th>Project</th>
                <th>Observer</th>
                <th>Targets</th>
                <th>Submitted</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {pending.map(req => (
                <tr key={req.id}>
                  <td className={styles.projectName}>{req.project_name}</td>
                  <td>{req.user_name}</td>
                  <td>
                    {req.targets.map(t => t.target_name).join(', ')}
                    <span className={styles.targetMeta}> ({req.targets.length})</span>
                  </td>
                  <td className={styles.date}>
                    {req.submitted_at ? new Date(req.submitted_at).toLocaleDateString('en-GB') : '-'}
                  </td>
                  <td>
                    <div className={styles.actions}>
                      <button onClick={() => approve(req.id)} className="btn btn-success btn-sm">Approve</button>
                      <Link to={`/request/${req.id}`} className="btn btn-secondary btn-sm">Review</Link>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <div className={`card ${styles.section}`}>
        <h2 className={styles.sectionTitle}>
          Job Queue
          {activeJobs.length > 0 && <span className={styles.count}>{activeJobs.length}</span>}
        </h2>

        {dispatchError && (
          <p style={{ color: 'var(--danger)', fontSize: '0.88rem', marginBottom: '0.75rem' }}>
            {dispatchError}
          </p>
        )}

        {activeJobs.length === 0 ? (
          <p className={styles.empty}>No active jobs in the queue.</p>
        ) : (
          <table className={styles.table}>
            <thead>
              <tr>
                <th>Project</th>
                <th>Targets</th>
                <th>Scope</th>
                <th>Status</th>
                <th>Queued</th>
                <th>Run Now</th>
              </tr>
            </thead>
            <tbody>
              {activeJobs.map(job => (
                <tr key={job.id}>
                  <td className={styles.projectName}>{job.project_name}</td>
                  <td>{job.target_summary}</td>
                  <td>{job.scope_id || <span className={styles.targetMeta}>unassigned</span>}</td>
                  <td><span className={`badge badge-${job.status}`}>{job.status}</span></td>
                  <td className={styles.date}>
                    {new Date(job.created_at).toLocaleDateString('en-GB')}
                  </td>
                  <td>
                    {job.status === 'queued' && (
                      <div className={styles.actions}>
                        <select
                          value={dispatchScopeId[job.id] ?? ''}
                          onChange={e => setDispatchScopeId(prev => ({ ...prev, [job.id]: e.target.value }))}
                          disabled={dispatching === job.id}
                          style={{ fontSize: '0.82rem' }}
                        >
                          <option value="">
                            {idleScopes.length === 0 ? 'No scopes idle' : 'Select scope…'}
                          </option>
                          {idleScopes.map(s => (
                            <option key={s.id} value={s.id}>{s.name ?? s.id}</option>
                          ))}
                        </select>
                        <button
                          onClick={() => dispatch(job.id)}
                          disabled={!dispatchScopeId[job.id] || dispatching === job.id}
                          className="btn btn-success btn-sm"
                        >
                          {dispatching === job.id ? '…' : 'Run Now'}
                        </button>
                      </div>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {completedJobs.length > 0 && (
        <div className={`card ${styles.section}`}>
          <h2 className={styles.sectionTitle}>Completed Jobs</h2>
          <table className={styles.table}>
            <thead>
              <tr>
                <th>Project</th>
                <th>Targets</th>
                <th>Scope</th>
                <th>Status</th>
                <th>Completed</th>
              </tr>
            </thead>
            <tbody>
              {completedJobs.map(job => (
                <tr key={job.id}>
                  <td className={styles.projectName}>{job.project_name}</td>
                  <td>{job.target_summary}</td>
                  <td>{job.scope_id || '-'}</td>
                  <td><span className={`badge badge-${job.status}`}>{job.status}</span></td>
                  <td className={styles.date}>
                    {job.completed_at ? new Date(job.completed_at).toLocaleDateString('en-GB') : '-'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <div className={`card ${styles.section}`}>
        <h2 className={styles.sectionTitle}>Recent Activity</h2>
        {recent.length === 0 ? (
          <p className={styles.empty}>No recent activity.</p>
        ) : (
          <table className={styles.table}>
            <thead>
              <tr>
                <th>Project</th>
                <th>Observer</th>
                <th>Status</th>
                <th>Date</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {recent.map(req => (
                <tr key={req.id}>
                  <td className={styles.projectName}>{req.project_name}</td>
                  <td>{req.user_name}</td>
                  <td><span className={`badge badge-${req.status}`}>{req.status}</span></td>
                  <td className={styles.date}>
                    {(req.approved_at || req.submitted_at) ? new Date((req.approved_at || req.submitted_at)!).toLocaleDateString('en-GB') : '-'}
                  </td>
                  <td><Link to={`/request/${req.id}`} className="btn btn-secondary btn-sm">View</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
