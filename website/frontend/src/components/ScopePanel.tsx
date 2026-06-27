import { useState } from 'react';
import type { Scope } from '../api/types';
import { api } from '../api/client';
import { WebcamStream } from './WebcamStream';
import styles from './ScopePanel.module.css';

function relativeTime(iso: string | null): string {
  if (!iso) return 'never';
  const then = new Date(iso).getTime();
  const secs = Math.max(0, Math.round((Date.now() - then) / 1000));
  if (secs < 60) return `${secs}s ago`;
  if (secs < 3600) return `${Math.round(secs / 60)}m ago`;
  return `${Math.round(secs / 3600)}h ago`;
}

const BUSY_STATES = new Set(['fetching', 'processing', 'executing', 'uploading']);
const WEATHER_HOLD = 'weather_hold';

function dotClass(scope: Scope): string {
  if (!scope.online) return styles.offline;
  if (scope.state === WEATHER_HOLD) return styles.busy;
  if (BUSY_STATES.has(scope.state)) return styles.busy;
  return styles.online;
}

function HwChip({ label, on }: { label: string; on: boolean }) {
  return <span className={`${styles.chip} ${on ? styles.chipOn : ''}`}>{label}</span>;
}

function AutomationToggle({ scopeId, enabled, onToggled }: {
  scopeId: string;
  enabled: boolean;
  onToggled: (enabled: boolean) => void;
}) {
  const [busy, setBusy] = useState(false);

  async function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
    const next = e.target.checked;
    setBusy(true);
    try {
      await api.patch(`/scopes/${scopeId}/automation`, { enabled: next });
      onToggled(next);
    } catch {
      // revert — the input will reflect the original state on next render
    } finally {
      setBusy(false);
    }
  }

  return (
    <label className={`${styles.toggle} ${busy ? styles.toggleBusy : ''}`}>
      <input
        type="checkbox"
        checked={enabled}
        disabled={busy}
        onChange={handleChange}
      />
      <span className={styles.toggleTrack}>
        <span className={styles.toggleThumb} />
      </span>
      <span className={`${styles.toggleLabel} ${enabled ? styles.toggleOn : styles.toggleOff}`}>
        {enabled ? 'Automation ON' : 'Automation OFF'}
      </span>
    </label>
  );
}

function CameraIcon() {
  return (
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"
         strokeLinecap="round" strokeLinejoin="round">
      <path d="M23 7l-7 5 7 5V7z"/>
      <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
    </svg>
  );
}

function ScopeCard({ scope, onScopeUpdated }: {
  scope: Scope;
  onScopeUpdated?: (scopeId: string, patch: Partial<Scope>) => void;
}) {
  const [webcamOpen, setWebcamOpen] = useState(false);

  return (
    <div className={`${styles.scope} ${!scope.automation_enabled ? styles.scopeManual : ''}`}>
      <div className={styles.scopeHeader}>
        <span className={styles.scopeName}>{scope.name || scope.id}</span>
        <div className={styles.headerRight}>
          {scope.webcam_available && (
            <button
              className={`${styles.camBtn} ${webcamOpen ? styles.camBtnActive : ''}`}
              onClick={() => setWebcamOpen(o => !o)}
              title={webcamOpen ? 'Hide webcam' : 'Show webcam'}
            >
              <CameraIcon />
            </button>
          )}
          <span className={styles.scopeId}>{scope.id}</span>
        </div>
      </div>

      <div className={styles.state}>
        <span className={`${styles.dot} ${dotClass(scope)}`} />
        {scope.online ? scope.state : 'offline'}
      </div>

      <div className={styles.progress}>
        {scope.online && scope.progress_message ? scope.progress_message : ' '}
      </div>

      <div className={styles.hw}>
        <HwChip label="KStars" on={scope.kstars_running} />
        <HwChip label="INDI" on={scope.indi_running} />
        <HwChip label="Network" on={scope.network_connected} />
        {scope.online && scope.weather_safe !== null && scope.weather_safe !== undefined && (
          <span className={`${styles.chip} ${scope.weather_safe ? styles.chipOn : styles.chipDanger}`}>
            {scope.weather_safe ? 'Weather OK' : 'Weather Hold'}
          </span>
        )}
      </div>

      <div className={styles.footer}>
        <span className={styles.heartbeat}>
          Last heartbeat: {relativeTime(scope.last_heartbeat)}
        </span>
        <AutomationToggle
          scopeId={scope.id}
          enabled={scope.automation_enabled}
          onToggled={enabled => onScopeUpdated?.(scope.id, { automation_enabled: enabled })}
        />
      </div>

      {webcamOpen && (
        <WebcamStream scopeId={scope.id} scopeName={scope.name ?? scope.id} arduinoAvailable={scope.arduino_available} onClose={() => setWebcamOpen(false)} />
      )}
    </div>
  );
}

export function ScopePanel({ scopes, onScopeUpdated }: {
  scopes: Scope[];
  onScopeUpdated?: (scopeId: string, patch: Partial<Scope>) => void;
}) {
  if (scopes.length === 0) {
    return <p className={styles.empty}>No telescope runners have checked in yet.</p>;
  }

  return (
    <div className={styles.grid}>
      {scopes.map(scope => (
        <ScopeCard key={scope.id} scope={scope} onScopeUpdated={onScopeUpdated} />
      ))}
    </div>
  );
}
