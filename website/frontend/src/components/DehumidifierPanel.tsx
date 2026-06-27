import { useState, useEffect, useCallback } from 'react';
import { api } from '../api/client';
import type { DehumidifierStatus, DomeStatus } from '../api/types';
import { useAuth } from '../contexts/AuthContext';
import styles from './DehumidifierPanel.module.css';

const REFRESH_MS = 30_000;

export function DehumidifierPanel() {
  const { user } = useAuth();
  const isStaff = user?.role === 'staff' || user?.role === 'admin';

  const [data, setData] = useState<DehumidifierStatus | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState<Record<string, boolean>>({});

  const load = useCallback(async () => {
    try {
      const res = await api.get<DehumidifierStatus>('/dehumidifiers');
      setData(res);
      setError(null);
    } catch {
      setError('Could not reach BMS — check observatory-server connectivity');
    }
  }, []);

  useEffect(() => {
    load();
    const timer = setInterval(load, REFRESH_MS);
    return () => clearInterval(timer);
  }, [load]);

  const sendCommand = async (key: string, path: string) => {
    setPending(p => ({ ...p, [key]: true }));
    try {
      await api.post(path);
      await load();
    } catch {
      // silently refresh — the status load will show current state
    } finally {
      setPending(p => ({ ...p, [key]: false }));
    }
  };

  const toggleDome = (dome: DomeStatus) => {
    const cmd = dome.enabled ? 'off' : 'on';
    sendCommand(`dome-${dome.dome}`, `/dehumidifiers/${dome.dome}/${cmd}`);
  };

  const allOn = () => sendCommand('all-on', '/dehumidifiers/all/on');
  const allOff = () => sendCommand('all-off', '/dehumidifiers/all/off');

  if (error) return <p className={styles.error}>{error}</p>;
  if (!data) return <p className={styles.loading}>Loading dehumidifier status…</p>;

  return (
    <div>
      {isStaff && (
        <div className={styles.bulkBar}>
          <button
            className="btn btn-success btn-sm"
            onClick={allOn}
            disabled={!!pending['all-on']}
          >
            {pending['all-on'] ? '…' : 'All On'}
          </button>
          <button
            className="btn btn-secondary btn-sm"
            onClick={allOff}
            disabled={!!pending['all-off']}
          >
            {pending['all-off'] ? '…' : 'All Off'}
          </button>
          <span className={styles.updated}>
            Updated {new Date(data.checked_at).toLocaleTimeString('en-GB')}
          </span>
        </div>
      )}

      <div className={styles.grid}>
        {data.domes.map(dome => (
          <div key={dome.dome} className={`${styles.card} ${dome.enabled ? styles.cardOn : ''}`}>
            <div className={styles.cardHeader}>
              <span className={styles.domeName}>Dome {dome.dome}</span>
              <div className={styles.badges}>
                <span className={`${styles.status} ${dome.enabled ? styles.statusOn : styles.statusOff}`}>
                  {dome.enabled ? 'Enabled' : 'Disabled'}
                </span>
                {dome.enabled && (
                  <span className={`${styles.status} ${dome.running ? styles.statusRunning : styles.statusIdle}`}>
                    {dome.running ? 'Running' : 'Idle'}
                  </span>
                )}
              </div>
            </div>

            <div className={styles.readings}>
              <div className={styles.reading}>
                <span className={styles.readingLabel}>Humidity</span>
                <span className={styles.readingValue}>{dome.humidity_pct.toFixed(1)}%</span>
              </div>
              <div className={styles.reading}>
                <span className={styles.readingLabel}>Air temp</span>
                <span className={styles.readingValue}>{dome.air_temp_c.toFixed(1)}°C</span>
              </div>
              <div className={styles.reading}>
                <span className={styles.readingLabel}>Mount temp</span>
                <span className={styles.readingValue}>{dome.mount_temp_c.toFixed(1)}°C</span>
              </div>
              <div className={styles.reading}>
                <span className={styles.readingLabel}>Dew point</span>
                <span className={styles.readingValue}>{dome.dew_point_c.toFixed(1)}°C</span>
              </div>
            </div>

            {isStaff && (
              <button
                className={`btn btn-sm ${dome.enabled ? 'btn-secondary' : 'btn-success'} ${styles.toggle}`}
                onClick={() => toggleDome(dome)}
                disabled={!!pending[`dome-${dome.dome}`]}
              >
                {pending[`dome-${dome.dome}`] ? '…' : dome.enabled ? 'Turn Off' : 'Turn On'}
              </button>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
