import { useState, useEffect, useRef, useCallback, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import type { TargetInput, ObservationRequest, TelescopeConfig } from '../api/types';
import styles from './NewRequest.module.css';

interface CatalogueResult {
  name: string;
  common_name: string | null;
  ra_deg: number;
  dec_deg: number;
  type: string | null;
  source: 'local' | 'simbad';
}

function TargetNameInput({ value, onChange, onResolve }: {
  value: string;
  onChange: (v: string) => void;
  onResolve: (name: string, ra: number, dec: number) => void;
}) {
  const [suggestions, setSuggestions] = useState<CatalogueResult[]>([]);
  const [open, setOpen] = useState(false);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const wrapRef = useRef<HTMLDivElement>(null);

  const lookup = useCallback(async (q: string) => {
    if (q.length < 2) { setSuggestions([]); setOpen(false); return; }
    try {
      const res = await api.get<CatalogueResult[]>(`/catalogue/resolve?name=${encodeURIComponent(q)}`);
      setSuggestions(res);
      setOpen(res.length > 0);
    } catch {
      setSuggestions([]); setOpen(false);
    }
  }, []);

  const handleChange = (v: string) => {
    onChange(v);
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => lookup(v), 300);
  };

  const pick = (r: CatalogueResult) => {
    onChange(r.name);
    onResolve(r.name, r.ra_deg, r.dec_deg);
    setSuggestions([]); setOpen(false);
  };

  // Close on outside click
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  return (
    <div ref={wrapRef} className={styles.autocompleteWrap}>
      <input
        type="text"
        value={value}
        onChange={e => handleChange(e.target.value)}
        onFocus={() => suggestions.length > 0 && setOpen(true)}
        placeholder="e.g. M42, NGC 7000, Vega"
        required
        autoComplete="off"
      />
      {open && (
        <ul className={styles.suggestions}>
          {suggestions.map(r => (
            <li key={r.name} onMouseDown={() => pick(r)} className={styles.suggestion}>
              <span className={styles.suggestName}>
                {r.name}{r.common_name ? <span className={styles.suggestCommon}> — {r.common_name}</span> : null}
              </span>
              <span className={styles.suggestMeta}>
                {r.type && <span className={styles.suggestType}>{r.type}</span>}
                <span>{r.ra_deg.toFixed(4)}°, {r.dec_deg.toFixed(4)}°</span>
                {r.source === 'simbad' && <span className={styles.simbadBadge}>SIMBAD</span>}
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

const ALL_FILTERS = ['L', 'R', 'G', 'B', 'Ha', 'OIII', 'SII', 'C', 'U', 'V', 'I'];
const ALL_BINNINGS = [1, 2, 4];

const emptyTarget = (tel?: TelescopeConfig): TargetInput => ({
  target_name: '',
  ra: 0,
  dec: 0,
  filters: tel?.filters.length ? [tel.filters[0]] : ['L'],
  exposure_seconds: 5,
  count: 1,
  binning: tel?.min_binning ?? 1,
});

export function NewRequest() {
  const navigate = useNavigate();
  const [projectName, setProjectName] = useState('');
  const [description, setDescription] = useState('');
  const [telescopeId, setTelescopeId] = useState('');
  const [telescopes, setTelescopes] = useState<TelescopeConfig[]>([]);
  const [targets, setTargets] = useState<TargetInput[]>([emptyTarget()]);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    api.get<TelescopeConfig[]>('/telescopes').then(setTelescopes).catch(console.error);
  }, []);

  const selectedTelescope = telescopes.find(t => t.id === telescopeId);
  const availableFilters = selectedTelescope?.filters.length ? selectedTelescope.filters : ALL_FILTERS;
  const availableBinnings = ALL_BINNINGS.filter(b => b >= (selectedTelescope?.min_binning ?? 1));

  // When telescope changes, clamp existing targets to its constraints
  useEffect(() => {
    if (!selectedTelescope) return;
    const minBin = selectedTelescope.min_binning;
    const telFilters = selectedTelescope.filters;
    setTargets(prev => prev.map(t => {
      const validFilters = t.filters.filter(f => telFilters.includes(f));
      return {
        ...t,
        filters: validFilters.length ? validFilters : [telFilters[0]],
        binning: t.binning < minBin ? minBin : t.binning,
      };
    }));
  }, [telescopeId]); // eslint-disable-line react-hooks/exhaustive-deps

  const updateTarget = (idx: number, patch: Partial<TargetInput>) => {
    setTargets(prev => prev.map((t, i) => i === idx ? { ...t, ...patch } : t));
  };

  const toggleFilter = (idx: number, filter: string) => {
    setTargets(prev => prev.map((t, i) => {
      if (i !== idx) return t;
      const has = t.filters.includes(filter);
      const next = has ? t.filters.filter(f => f !== filter) : [...t.filters, filter];
      return { ...t, filters: next.length ? next : t.filters };
    }));
  };

  const removeTarget = (idx: number) => {
    if (targets.length <= 1) return;
    setTargets(prev => prev.filter((_, i) => i !== idx));
  };

  const validate = (): string | null => {
    if (!projectName.trim()) return 'Project name is required.';
    if (!telescopeId) return 'Please select a telescope.';
    for (let i = 0; i < targets.length; i++) {
      const t = targets[i];
      const label = targets.length > 1 ? `Target ${i + 1}: ` : '';
      if (!t.target_name.trim()) return `${label}Target name is required.`;
      if (t.ra < 0 || t.ra > 360) return `${label}RA must be between 0 and 360 degrees.`;
      if (t.dec < -90 || t.dec > 90) return `${label}Dec must be between -90 and 90 degrees.`;
      if (t.exposure_seconds < 0.1 || t.exposure_seconds > 3600) return `${label}Exposure must be between 0.1 and 3600 seconds.`;
      if (t.count < 1 || t.count > 1000) return `${label}Count must be between 1 and 1000.`;
      if (t.filters.length === 0) return `${label}At least one filter must be selected.`;
    }
    return null;
  };

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    const validationError = validate();
    if (validationError) {
      setError(validationError);
      return;
    }
    setError('');
    setSubmitting(true);
    try {
      const result = await api.post<ObservationRequest>('/requests', {
        project_name: projectName.trim(),
        description: description.trim() || undefined,
        telescope_id: telescopeId,
        targets: targets.map(t => ({ ...t, target_name: t.target_name.trim() })),
      });
      navigate(`/request/${result.id}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Submission failed');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div>
      <h1 className={styles.heading}>New Observation Request</h1>
      <p className={styles.subtext}>Define your targets and imaging parameters. Staff will review before scheduling.</p>

      <form onSubmit={handleSubmit} className={styles.form}>
        {error && <div className={styles.error}>{error}</div>}

        <div className={`card ${styles.section}`}>
          <h2 className={styles.sectionTitle}>Project Details</h2>
          <label className={styles.field}>
            <span>Project Name</span>
            <input type="text" value={projectName} onChange={e => setProjectName(e.target.value)} required placeholder="e.g. M13 RGB Imaging" />
          </label>
          <label className={styles.field}>
            <span>Description (optional)</span>
            <textarea value={description} onChange={e => setDescription(e.target.value)} rows={3} placeholder="Brief description of your observation goals..." />
          </label>
          <label className={styles.field}>
            <span>Telescope <span className={styles.required}>*</span></span>
            <select value={telescopeId} onChange={e => setTelescopeId(e.target.value)} required>
              <option value="">— Select a telescope —</option>
              {telescopes.map(t => (
                <option key={t.id} value={t.id}>
                  #{t.num} {t.short_name} — {t.telescope}{t.camera ? ` + ${t.camera}` : ''}
                </option>
              ))}
            </select>
            {telescopeId && (() => {
              const tel = telescopes.find(t => t.id === telescopeId);
              if (!tel) return null;
              return (
                <div className={styles.telescopeHint}>
                  {tel.fov_w_arcmin != null && <span>FOV {tel.fov_w_arcmin}′ × {tel.fov_h_arcmin}′</span>}
                  {tel.filters.length > 0 && <span>Filters: {tel.filters.join(', ')}</span>}
                  {(tel.dec_lower != null || tel.dec_upper != null) && <span>Dec: {tel.dec_lower ?? '?'}° to {tel.dec_upper ?? '?'}°</span>}
                </div>
              );
            })()}
          </label>
        </div>

        {targets.map((target, idx) => (
          <div key={idx} className={`card ${styles.section}`}>
            <div className={styles.sectionHeader}>
              <h2 className={styles.sectionTitle}>Target {idx + 1}</h2>
              {targets.length > 1 && (
                <button type="button" onClick={() => removeTarget(idx)} className="btn btn-danger btn-sm">Remove</button>
              )}
            </div>

            <div className={styles.field}>
              <span>Target Name</span>
              <TargetNameInput
                value={target.target_name}
                onChange={v => updateTarget(idx, { target_name: v })}
                onResolve={(_name, ra, dec) => updateTarget(idx, { ra, dec })}
              />
            </div>

            <div className={styles.row}>
              <label className={styles.field}>
                <span>RA (degrees)</span>
                <input type="number" step="any" value={target.ra} onChange={e => updateTarget(idx, { ra: parseFloat(e.target.value) || 0 })} required />
              </label>
              <label className={styles.field}>
                <span>Dec (degrees)</span>
                <input type="number" step="any" value={target.dec} onChange={e => updateTarget(idx, { dec: parseFloat(e.target.value) || 0 })} required />
              </label>
            </div>

            <div className={styles.field}>
              <span>Filters</span>
              <div className={styles.filters}>
                {availableFilters.map(f => (
                  <button key={f} type="button" onClick={() => toggleFilter(idx, f)}
                    className={`${styles.filterBtn} ${target.filters.includes(f) ? styles.filterActive : ''}`}>
                    {f}
                  </button>
                ))}
              </div>
            </div>

            <div className={styles.row}>
              <label className={styles.field}>
                <span>Exposure (seconds)</span>
                <input type="number" step="0.1" min="0.1" value={target.exposure_seconds} onChange={e => updateTarget(idx, { exposure_seconds: parseFloat(e.target.value) || 5 })} />
              </label>
              <label className={styles.field}>
                <span>Count per filter</span>
                <input type="number" min="1" value={target.count} onChange={e => updateTarget(idx, { count: parseInt(e.target.value) || 1 })} />
              </label>
              <label className={styles.field}>
                <span>Binning</span>
                <select value={target.binning} onChange={e => updateTarget(idx, { binning: parseInt(e.target.value) })}>
                  {availableBinnings.map(b => (
                    <option key={b} value={b}>{b}x{b}</option>
                  ))}
                </select>
              </label>
            </div>
          </div>
        ))}

        <button type="button" onClick={() => setTargets(prev => [...prev, emptyTarget(selectedTelescope)])} className="btn btn-secondary" style={{ width: '100%', justifyContent: 'center' }}>
          + Add Another Target
        </button>

        <button type="submit" className="btn btn-primary" disabled={submitting} style={{ width: '100%', justifyContent: 'center', marginTop: '0.5rem' }}>
          {submitting ? 'Submitting...' : 'Submit Request for Review'}
        </button>
      </form>
    </div>
  );
}
