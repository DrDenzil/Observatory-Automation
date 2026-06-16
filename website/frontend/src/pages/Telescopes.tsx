import { useState, useEffect, type FormEvent } from 'react';
import { api } from '../api/client';
import { useAuth } from '../contexts/AuthContext';
import type { TelescopeConfig, TelescopeInput, TelescopeStatus } from '../api/types';
import styles from './Telescopes.module.css';

const AVAILABLE_FILTERS = ['L', 'R', 'G', 'B', 'Ha', 'OIII', 'SII', 'C', 'U', 'V', 'I'];
const STATUSES: TelescopeStatus[] = ['manual', 'maintenance', 'automatic'];

const STATUS_LABEL: Record<TelescopeStatus, string> = {
  manual: 'Not automated',
  maintenance: 'Maintenance / testing',
  automatic: 'Running automatically',
};

const STATUS_BADGE: Record<TelescopeStatus, string> = {
  manual: 'draft',
  maintenance: 'submitted',
  automatic: 'approved',
};

const emptyForm = (): TelescopeInput => ({
  num: 1,
  short_name: '',
  telescope: '',
  aperture_mm: null,
  focal_length_mm: null,
  camera: null,
  pixel_width_um: null,
  fov_w_arcmin: null,
  fov_h_arcmin: null,
  filters: ['L'],
  dec_lower: null,
  dec_upper: null,
  min_binning: 1,
  status: 'manual',
  status_reason: null,
  scope_id: null,
});

const numOrNull = (v: string): number | null => (v === '' ? null : Number(v));

export function Telescopes() {
  const { isAdmin } = useAuth();
  const [telescopes, setTelescopes] = useState<TelescopeConfig[]>([]);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState<TelescopeConfig | null>(null);
  const [creating, setCreating] = useState(false);

  const load = async () => {
    try {
      setTelescopes(await api.get<TelescopeConfig[]>('/telescopes'));
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const startCreate = () => { setEditing(null); setCreating(true); };
  const startEdit = (t: TelescopeConfig) => { setCreating(false); setEditing(t); };
  const closeForm = () => { setCreating(false); setEditing(null); };

  const remove = async (t: TelescopeConfig) => {
    if (!confirm(`Delete telescope #${t.num} ${t.short_name}?`)) return;
    await api.delete(`/telescopes/${t.id}`);
    load();
  };

  const onSaved = () => { closeForm(); load(); };

  if (loading) return <p>Loading...</p>;

  const formOpen = creating || editing !== null;

  return (
    <div>
      <div className={styles.headerRow}>
        <div>
          <h1 className={styles.heading}>Telescopes</h1>
        </div>
        {isAdmin && !formOpen && (
          <button className="btn btn-primary" onClick={startCreate}>+ Add Telescope</button>
        )}
      </div>
      <p className={styles.subtext}>
        Telescope capabilities and automation status.
        {isAdmin ? ' Edit these to control what observers can request per telescope.' : ''}
      </p>

      {formOpen && isAdmin && (
        <TelescopeForm
          initial={editing}
          onSaved={onSaved}
          onCancel={closeForm}
        />
      )}

      {telescopes.length === 0 ? (
        <p className={styles.empty}>No telescopes configured yet.</p>
      ) : (
        <div className={styles.grid}>
          {telescopes.map(t => (
            <div key={t.id} className={`card ${styles.scope}`}>
              <div className={styles.scopeHead}>
                <span className={styles.scopeTitle}>
                  <span className={styles.num}>#{t.num}</span>{t.short_name}
                </span>
                <span className={`badge badge-${STATUS_BADGE[t.status]}`}>{STATUS_LABEL[t.status]}</span>
              </div>

              <div className={styles.specs}>
                <span className={styles.specLabel}>Telescope</span><span>{t.telescope}</span>
                {t.aperture_mm != null && (<><span className={styles.specLabel}>Aperture</span><span>{t.aperture_mm} mm</span></>)}
                {t.focal_length_mm != null && (<><span className={styles.specLabel}>Focal length</span><span>{t.focal_length_mm} mm</span></>)}
                {t.camera && (<><span className={styles.specLabel}>Camera</span><span>{t.camera}</span></>)}
                {t.pixel_width_um != null && (<><span className={styles.specLabel}>Pixel width</span><span>{t.pixel_width_um} µm</span></>)}
                {t.fov_w_arcmin != null && t.fov_h_arcmin != null && (
                  <><span className={styles.specLabel}>FOV</span><span>{t.fov_w_arcmin}′ × {t.fov_h_arcmin}′</span></>
                )}
                {(t.dec_lower != null || t.dec_upper != null) && (
                  <><span className={styles.specLabel}>Dec limits</span><span>{t.dec_lower ?? '?'}° to {t.dec_upper ?? '?'}°</span></>
                )}
                <span className={styles.specLabel}>Min binning</span><span>{t.min_binning}×{t.min_binning}</span>
                {t.scope_id && (<><span className={styles.specLabel}>Runner</span><span>{t.scope_id}</span></>)}
              </div>

              {t.filters.length > 0 && (
                <div className={styles.filters}>
                  {t.filters.map(f => <span key={f} className={styles.filterChip}>{f}</span>)}
                </div>
              )}

              {t.status_reason && <div className={styles.reason}>{t.status_reason}</div>}

              {isAdmin && (
                <div className={styles.cardActions}>
                  <button className="btn btn-secondary btn-sm" onClick={() => startEdit(t)}>Edit</button>
                  <button className="btn btn-danger btn-sm" onClick={() => remove(t)}>Delete</button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function TelescopeForm({ initial, onSaved, onCancel }: {
  initial: TelescopeConfig | null;
  onSaved: () => void;
  onCancel: () => void;
}) {
  const [form, setForm] = useState<TelescopeInput>(() =>
    initial
      ? {
          num: initial.num, short_name: initial.short_name, telescope: initial.telescope,
          aperture_mm: initial.aperture_mm, focal_length_mm: initial.focal_length_mm,
          camera: initial.camera, pixel_width_um: initial.pixel_width_um,
          fov_w_arcmin: initial.fov_w_arcmin, fov_h_arcmin: initial.fov_h_arcmin,
          filters: initial.filters, dec_lower: initial.dec_lower, dec_upper: initial.dec_upper,
          min_binning: initial.min_binning, status: initial.status,
          status_reason: initial.status_reason, scope_id: initial.scope_id,
        }
      : emptyForm()
  );
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  const set = (patch: Partial<TelescopeInput>) => setForm(prev => ({ ...prev, ...patch }));

  const toggleFilter = (f: string) => {
    setForm(prev => ({
      ...prev,
      filters: prev.filters.includes(f) ? prev.filters.filter(x => x !== f) : [...prev.filters, f],
    }));
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (!form.short_name.trim() || !form.telescope.trim()) {
      setError('Short name and telescope description are required.');
      return;
    }
    setError('');
    setSaving(true);
    try {
      const payload = { ...form, short_name: form.short_name.trim(), telescope: form.telescope.trim() };
      if (initial) {
        await api.patch(`/telescopes/${initial.id}`, payload);
      } else {
        await api.post('/telescopes', payload);
      }
      onSaved();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  return (
    <form className={`card ${styles.form}`} onSubmit={submit}>
      <div className={styles.formTitle}>{initial ? `Edit #${initial.num} ${initial.short_name}` : 'New Telescope'}</div>
      {error && <div className={styles.error}>{error}</div>}

      <div className={styles.row}>
        <label className={styles.field} style={{ maxWidth: 90 }}>
          <span>Number</span>
          <input type="number" value={form.num} onChange={e => set({ num: Number(e.target.value) || 1 })} required />
        </label>
        <label className={styles.field}>
          <span>Short name</span>
          <input type="text" value={form.short_name} onChange={e => set({ short_name: e.target.value })} placeholder="CKT" required />
        </label>
        <label className={styles.field} style={{ flex: 2 }}>
          <span>Telescope description</span>
          <input type="text" value={form.telescope} onChange={e => set({ telescope: e.target.value })} placeholder="Meade LX200GPS 16-inch" required />
        </label>
      </div>

      <div className={styles.row}>
        <label className={styles.field}>
          <span>Aperture (mm)</span>
          <input type="number" step="any" value={form.aperture_mm ?? ''} onChange={e => set({ aperture_mm: numOrNull(e.target.value) })} />
        </label>
        <label className={styles.field}>
          <span>Focal length (mm)</span>
          <input type="number" step="any" value={form.focal_length_mm ?? ''} onChange={e => set({ focal_length_mm: numOrNull(e.target.value) })} />
        </label>
        <label className={styles.field}>
          <span>Camera</span>
          <input type="text" value={form.camera ?? ''} onChange={e => set({ camera: e.target.value || null })} />
        </label>
        <label className={styles.field}>
          <span>Pixel width (µm)</span>
          <input type="number" step="any" value={form.pixel_width_um ?? ''} onChange={e => set({ pixel_width_um: numOrNull(e.target.value) })} />
        </label>
      </div>

      <div className={styles.row}>
        <label className={styles.field}>
          <span>FOV width (′)</span>
          <input type="number" step="any" value={form.fov_w_arcmin ?? ''} onChange={e => set({ fov_w_arcmin: numOrNull(e.target.value) })} />
        </label>
        <label className={styles.field}>
          <span>FOV height (′)</span>
          <input type="number" step="any" value={form.fov_h_arcmin ?? ''} onChange={e => set({ fov_h_arcmin: numOrNull(e.target.value) })} />
        </label>
        <label className={styles.field}>
          <span>Dec lower (°)</span>
          <input type="number" step="any" value={form.dec_lower ?? ''} onChange={e => set({ dec_lower: numOrNull(e.target.value) })} />
        </label>
        <label className={styles.field}>
          <span>Dec upper (°)</span>
          <input type="number" step="any" value={form.dec_upper ?? ''} onChange={e => set({ dec_upper: numOrNull(e.target.value) })} />
        </label>
        <label className={styles.field} style={{ maxWidth: 110 }}>
          <span>Min binning</span>
          <input type="number" min="1" value={form.min_binning} onChange={e => set({ min_binning: Number(e.target.value) || 1 })} />
        </label>
      </div>

      <div className={styles.field}>
        <span>Filters</span>
        <div className={styles.row} style={{ gap: '0.3rem' }}>
          {AVAILABLE_FILTERS.map(f => (
            <button type="button" key={f}
              className={`${styles.filterBtn} ${form.filters.includes(f) ? styles.filterActive : ''}`}
              onClick={() => toggleFilter(f)}>
              {f}
            </button>
          ))}
        </div>
      </div>

      <div className={styles.row}>
        <label className={styles.field}>
          <span>Status</span>
          <select value={form.status} onChange={e => set({ status: e.target.value as TelescopeStatus })}>
            {STATUSES.map(s => <option key={s} value={s}>{STATUS_LABEL[s]}</option>)}
          </select>
        </label>
        <label className={styles.field} style={{ flex: 2 }}>
          <span>Status reason</span>
          <input type="text" value={form.status_reason ?? ''} onChange={e => set({ status_reason: e.target.value || null })} placeholder="e.g. Awaiting commissioning" />
        </label>
        <label className={styles.field}>
          <span>Runner scope id</span>
          <input type="text" value={form.scope_id ?? ''} onChange={e => set({ scope_id: e.target.value || null })} placeholder="scope03" />
        </label>
      </div>

      <div className={styles.formActions}>
        <button type="submit" className="btn btn-primary" disabled={saving}>
          {saving ? 'Saving...' : initial ? 'Save Changes' : 'Create Telescope'}
        </button>
        <button type="button" className="btn btn-secondary" onClick={onCancel}>Cancel</button>
      </div>
    </form>
  );
}
