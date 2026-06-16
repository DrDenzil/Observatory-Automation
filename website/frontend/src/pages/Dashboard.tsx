import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../contexts/AuthContext';
import type { ObservationRequest } from '../api/types';
import styles from './Dashboard.module.css';

export function Dashboard() {
  const { user, isStaff } = useAuth();
  const [requests, setRequests] = useState<ObservationRequest[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get<ObservationRequest[]>('/requests')
      .then(setRequests)
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  const counts = {
    submitted: requests.filter(r => r.status === 'submitted').length,
    approved: requests.filter(r => r.status === 'approved').length,
    rejected: requests.filter(r => r.status === 'rejected').length,
    total: requests.length,
  };

  if (loading) return <p>Loading...</p>;

  return (
    <div>
      <div className={styles.topBar}>
        <div>
          <h1 className={styles.heading}>
            {isStaff ? 'Observatory Dashboard' : 'My Observations'}
          </h1>
          <p className={styles.subheading}>
            Welcome back, {user?.name}
          </p>
        </div>
        <Link to="/request/new" className="btn btn-primary">New Request</Link>
      </div>

      <div className={styles.stats}>
        <div className={`card ${styles.stat}`}>
          <span className={styles.statValue}>{counts.total}</span>
          <span className={styles.statLabel}>Total</span>
        </div>
        <div className={`card ${styles.stat}`}>
          <span className={styles.statValue} style={{ color: 'var(--warning)' }}>{counts.submitted}</span>
          <span className={styles.statLabel}>Pending</span>
        </div>
        <div className={`card ${styles.stat}`}>
          <span className={styles.statValue} style={{ color: 'var(--success)' }}>{counts.approved}</span>
          <span className={styles.statLabel}>Approved</span>
        </div>
        <div className={`card ${styles.stat}`}>
          <span className={styles.statValue} style={{ color: 'var(--danger)' }}>{counts.rejected}</span>
          <span className={styles.statLabel}>Rejected</span>
        </div>
      </div>

      {requests.length === 0 ? (
        <div className={`card ${styles.empty}`}>
          <p>No observation requests yet.</p>
          <Link to="/request/new" className="btn btn-primary">Submit your first request</Link>
        </div>
      ) : (
        <div className={`card ${styles.table}`}>
          <table>
            <thead>
              <tr>
                <th>Project</th>
                <th>Targets</th>
                <th>Status</th>
                <th>Submitted</th>
                {isStaff && <th>Observer</th>}
                <th></th>
              </tr>
            </thead>
            <tbody>
              {requests.map(req => (
                <tr key={req.id}>
                  <td className={styles.projectName}>{req.project_name}</td>
                  <td>
                    {req.targets.map(t => t.target_name).join(', ')}
                    <span className={styles.targetCount}> ({req.targets.length})</span>
                  </td>
                  <td><span className={`badge badge-${req.status}`}>{req.status}</span></td>
                  <td className={styles.date}>
                    {req.submitted_at ? new Date(req.submitted_at).toLocaleDateString('en-GB') : '-'}
                  </td>
                  {isStaff && <td>{req.user_name || '-'}</td>}
                  <td>
                    <Link to={`/request/${req.id}`} className="btn btn-secondary btn-sm">View</Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
