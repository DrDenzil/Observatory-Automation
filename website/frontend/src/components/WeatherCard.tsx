import { useState, useEffect } from 'react';
import { api } from '../api/client';
import type { WeatherData } from '../api/types';
import styles from './WeatherCard.module.css';

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className={styles.row}>
      <span className={styles.label}>{label}</span>
      <span className={styles.value}>{value}</span>
    </div>
  );
}

export function WeatherCard() {
  const [data, setData] = useState<WeatherData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [lastFetch, setLastFetch] = useState<Date | null>(null);

  const fetch = async () => {
    try {
      const w = await api.get<WeatherData>('/weather');
      setData(w);
      setError(null);
    } catch {
      setError('Weather station unreachable');
    }
    setLastFetch(new Date());
  };

  useEffect(() => {
    fetch();
    const timer = setInterval(fetch, 60_000);
    return () => clearInterval(timer);
  }, []);

  return (
    <div className={styles.card}>
      <div className={styles.header}>
        <span className={styles.title}>Weather</span>
        {lastFetch && (
          <span className={styles.updated}>
            Updated {Math.round((Date.now() - lastFetch.getTime()) / 1000)}s ago
          </span>
        )}
      </div>

      {error ? (
        <p className={styles.unavailable}>{error}</p>
      ) : !data ? (
        <p className={styles.unavailable}>Loading…</p>
      ) : (
        <>
          <div className={`${styles.badge} ${data.safe ? styles.badgeSafe : styles.badgeUnsafe}`}>
            {data.safe ? 'SAFE' : 'UNSAFE'}
          </div>
          {!data.safe && (
            <p className={styles.reason}>{data.message.replace(/^UNSAFE: /, '')}</p>
          )}
          <div className={styles.grid}>
            <Row label="Wind" value={`${data.wind_kph.toFixed(0)} km/h (gust ${data.wind_gust_kph.toFixed(0)})`} />
            <Row label="Humidity" value={`${data.humidity_pct.toFixed(0)}%`} />
            <Row label="Rain" value={`${data.rain_rate_mmh.toFixed(1)} mm/h`} />
            <Row label="Temperature" value={`${data.temp_c.toFixed(1)} °C`} />
            <Row label="Sun altitude" value={`${data.sun_altitude.toFixed(1)}°`} />
          </div>
        </>
      )}
    </div>
  );
}
