import { useState, useEffect } from 'react';
import { api } from '../api/client';
import type { User } from '../api/types';
import styles from './Users.module.css';

interface UserForm {
  email: string;
  name: string;
  role: string;
  user_type: string;
  legacy_id: string;
  department: string;
}

const INITIAL_FORM: UserForm = {
  email: '',
  name: '',
  role: 'observer',
  user_type: 'student',
  legacy_id: '',
  department: '',
};

export function Users() {
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [searching, setSearching] = useState('');
  const [roleFilter, setRoleFilter] = useState<string | null>(null);
  const [typeFilter, setTypeFilter] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<UserForm>(INITIAL_FORM);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showImport, setShowImport] = useState(false);
  const [importFile, setImportFile] = useState<File | null>(null);
  const [importing, setImporting] = useState(false);
  const [importResult, setImportResult] = useState<any>(null);

  const load = async () => {
    try {
      setLoading(true);
      const params = new URLSearchParams();
      if (searching) params.set('search', searching);
      if (roleFilter) params.set('role', roleFilter);
      if (typeFilter) params.set('user_type', typeFilter);
      const result = await api.get<User[]>(`/users?${params}`);
      setUsers(result);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load users');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, [searching, roleFilter, typeFilter]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    try {
      const payload = {
        email: form.email,
        name: form.name,
        role: form.role,
        user_type: form.user_type,
        legacy_id: form.legacy_id ? parseInt(form.legacy_id) : null,
        department: form.department || null,
      };
      await api.post('/users', payload);
      setForm(INITIAL_FORM);
      setShowForm(false);
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create user');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDeactivate = async (userId: string) => {
    if (!confirm('Deactivate this user?')) return;
    try {
      await api.patch(`/users/${userId}`, { is_active: false });
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to deactivate user');
    }
  };

  const handleImport = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!importFile) return;
    setImporting(true);
    setError(null);
    try {
      const formData = new FormData();
      formData.append('file', importFile);
      const response = await fetch('/api/admin/import/users-csv', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
        },
        body: formData,
      });
      if (!response.ok) {
        const err = await response.json();
        throw new Error(err.detail || 'Import failed');
      }
      const result = await response.json();
      setImportResult(result);
      setImportFile(null);
      await new Promise(r => setTimeout(r, 1000));
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Import failed');
    } finally {
      setImporting(false);
    }
  };

  if (loading && users.length === 0) return <p>Loading...</p>;

  return (
    <div>
      <div className={styles.header}>
        <h1>User Management</h1>
        <div className={styles.headerActions}>
          <button onClick={() => setShowImport(!showImport)} className="btn btn-secondary">
            {showImport ? 'Cancel' : 'Import CSV'}
          </button>
          <button onClick={() => setShowForm(!showForm)} className="btn btn-primary">
            {showForm ? 'Cancel' : 'Add User'}
          </button>
        </div>
      </div>

      {error && <p className={styles.error}>{error}</p>}

      {importResult && (
        <div className={`card ${styles.resultCard}`}>
          <h3>Import Results</h3>
          <p>✓ Imported: <strong>{importResult.imported}</strong></p>
          <p>— Skipped: <strong>{importResult.skipped}</strong></p>
          {importResult.errors.length > 0 && (
            <>
              <p>⚠ Errors:</p>
              <ul className={styles.errorList}>
                {importResult.errors.map((err: string, i: number) => (
                  <li key={i}>{err}</li>
                ))}
              </ul>
            </>
          )}
          <button onClick={() => setImportResult(null)} className="btn btn-secondary btn-sm">
            Dismiss
          </button>
        </div>
      )}

      {showImport && (
        <div className={`card ${styles.formCard}`}>
          <h2>Import Users from CSV</h2>
          <form onSubmit={handleImport} className={styles.form}>
            <div className={styles.field}>
              <label>CSV File *</label>
              <input
                type="file"
                accept=".csv"
                required
                onChange={e => setImportFile(e.target.files?.[0] ?? null)}
              />
              <small>Expected columns: user_id, name, email, (optional) account_level, user_type</small>
            </div>
            <div className={styles.actions}>
              <button type="submit" disabled={!importFile || importing} className="btn btn-success">
                {importing ? 'Importing...' : 'Import'}
              </button>
            </div>
          </form>
        </div>
      )}

      {showForm && (
        <div className={`card ${styles.formCard}`}>
          <h2>Add New User</h2>
          <form onSubmit={handleSubmit} className={styles.form}>
            <div className={styles.field}>
              <label>Email *</label>
              <input
                type="email"
                required
                value={form.email}
                onChange={e => setForm(p => ({ ...p, email: e.target.value }))}
                placeholder="user@herts.ac.uk"
              />
            </div>

            <div className={styles.field}>
              <label>Name *</label>
              <input
                type="text"
                required
                value={form.name}
                onChange={e => setForm(p => ({ ...p, name: e.target.value }))}
                placeholder="John Doe"
              />
            </div>

            <div className={styles.row}>
              <div className={styles.field}>
                <label>Role *</label>
                <select value={form.role} onChange={e => setForm(p => ({ ...p, role: e.target.value }))}>
                  <option value="observer">Observer</option>
                  <option value="staff">Staff</option>
                  <option value="admin">Admin</option>
                </select>
              </div>

              <div className={styles.field}>
                <label>User Type *</label>
                <select value={form.user_type} onChange={e => setForm(p => ({ ...p, user_type: e.target.value }))}>
                  <option value="student">Student</option>
                  <option value="staff">Staff</option>
                  <option value="external">External Partner</option>
                </select>
              </div>
            </div>

            <div className={styles.row}>
              <div className={styles.field}>
                <label>Legacy ID (optional)</label>
                <input
                  type="number"
                  value={form.legacy_id}
                  onChange={e => setForm(p => ({ ...p, legacy_id: e.target.value }))}
                  placeholder="e.g. 33"
                />
                <small>For importing from old system</small>
              </div>

              <div className={styles.field}>
                <label>Department (optional)</label>
                <input
                  type="text"
                  value={form.department}
                  onChange={e => setForm(p => ({ ...p, department: e.target.value }))}
                  placeholder="Astronomy, Physics, etc."
                />
              </div>
            </div>

            <div className={styles.actions}>
              <button type="submit" disabled={submitting} className="btn btn-success">
                {submitting ? 'Creating...' : 'Create User'}
              </button>
            </div>
          </form>
        </div>
      )}

      <div className={`card ${styles.section}`}>
        <h2>Filters</h2>
        <div className={styles.filterRow}>
          <input
            type="text"
            placeholder="Search by name or email..."
            value={searching}
            onChange={e => setSearching(e.target.value)}
            className={styles.searchInput}
          />
          <select value={roleFilter ?? ''} onChange={e => setRoleFilter(e.target.value || null)}>
            <option value="">All Roles</option>
            <option value="observer">Observer</option>
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
          </select>
          <select value={typeFilter ?? ''} onChange={e => setTypeFilter(e.target.value || null)}>
            <option value="">All Types</option>
            <option value="student">Student</option>
            <option value="staff">Staff</option>
            <option value="external">External Partner</option>
          </select>
        </div>
      </div>

      <div className={`card ${styles.section}`}>
        <h2>Users ({users.length})</h2>
        {users.length === 0 ? (
          <p className={styles.empty}>No users found.</p>
        ) : (
          <table className={styles.table}>
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Type</th>
                <th>Legacy ID</th>
                <th>Department</th>
                <th>Registered</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {users.map(u => (
                <tr key={u.id}>
                  <td className={styles.name}>{u.name}</td>
                  <td>{u.email}</td>
                  <td><span className={`badge badge-${u.role}`}>{u.role}</span></td>
                  <td className={styles.type}>{u.user_type}</td>
                  <td className={styles.legacy}>{u.legacy_id ?? '-'}</td>
                  <td className={styles.dept}>{u.department ?? '-'}</td>
                  <td className={styles.date}>{new Date(u.created_at).toLocaleDateString('en-GB')}</td>
                  <td>
                    <span className={`badge ${u.is_active ? 'badge-success' : 'badge-danger'}`}>
                      {u.is_active ? 'active' : 'inactive'}
                    </span>
                  </td>
                  <td>
                    <div className={styles.cellActions}>
                      {u.is_active && (
                        <button onClick={() => handleDeactivate(u.id)} className="btn btn-danger btn-xs">
                          Deactivate
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
