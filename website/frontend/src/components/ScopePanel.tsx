import type { Scope } from '../api/types';
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

function dotClass(scope: Scope): string {
  if (!scope.online) return styles.offline;
  if (BUSY_STATES.has(scope.state)) return styles.busy;
  return styles.online;
}

function HwChip({ label, on }: { label: string; on: boolean }) {
  return <span className={`${styles.chip} ${on ? styles.chipOn : ''}`}>{label}</span>;
}

export function ScopePanel({ scopes }: { scopes: Scope[] }) {
  if (scopes.length === 0) {
    return <p className={styles.empty}>No telescope runners have checked in yet.</p>;
  }

  return (
    <div className={styles.grid}>
      {scopes.map(scope => (
        <div key={scope.id} className={styles.scope}>
          <div className={styles.scopeHeader}>
            <span className={styles.scopeName}>{scope.name || scope.id}</span>
            <span className={styles.scopeId}>{scope.id}</span>
          </div>

          <div className={styles.state}>
            <span className={`${styles.dot} ${dotClass(scope)}`} />
            {scope.online ? scope.state : 'offline'}
          </div>

          <div className={styles.progress}>
            {scope.online && scope.progress_message ? scope.progress_message : ' '}
          </div>

          <div className={styles.hw}>
            <HwChip label="KStars" on={scope.kstars_running} />
            <HwChip label="INDI" on={scope.indi_running} />
            <HwChip label="Network" on={scope.network_connected} />
          </div>

          <div className={styles.heartbeat}>
            Last heartbeat: {relativeTime(scope.last_heartbeat)}
          </div>
        </div>
      ))}
    </div>
  );
}
