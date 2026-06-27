import { useEffect, useRef, useState } from 'react';
import { useParams, useSearchParams } from 'react-router-dom';
import styles from './WebcamPopout.module.css';

export function WebcamPopout() {
  const { scopeId } = useParams<{ scopeId: string }>();
  const [searchParams] = useSearchParams();
  const scopeName = searchParams.get('name') || scopeId;

  const token = localStorage.getItem('token') ?? '';
  const streamUrl = `/api/scopes/${scopeId}/webcam/stream?token=${encodeURIComponent(token)}`;
  const snapshotUrl = `/api/scopes/${scopeId}/webcam/snapshot?token=${encodeURIComponent(token)}`;

  const imgRef = useRef<HTMLImageElement>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    document.title = `Webcam – ${scopeName}`;
    return () => {
      if (imgRef.current) imgRef.current.src = '';
    };
  }, [scopeName]);

  return (
    <div className={styles.root}>
      <div className={styles.bar}>
        <span className={styles.title}>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M23 7l-7 5 7 5V7z"/>
            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
          </svg>
          {scopeName}
        </span>
        <div className={styles.barRight}>
          {!error && (
            <>
              <span className={styles.live}><span className={styles.dot}/>LIVE</span>
              <a
                href={snapshotUrl}
                download={`${scopeId}-snapshot.jpg`}
                className={styles.snap}
                title="Save snapshot"
              >
                &#128247; Snapshot
              </a>
            </>
          )}
          <button className={styles.close} onClick={() => window.close()} title="Close">&#10005;</button>
        </div>
      </div>

      <div className={styles.viewport}>
        {error ? (
          <div className={styles.error}>
            <span>&#9888; {error}</span>
            <button onClick={() => { setError(null); if (imgRef.current) imgRef.current.src = streamUrl; }}>
              Retry
            </button>
          </div>
        ) : (
          <img
            ref={imgRef}
            src={streamUrl}
            alt="Live webcam feed"
            className={styles.stream}
            onError={() => setError('Camera unavailable — may be in use or runner offline.')}
          />
        )}
      </div>
    </div>
  );
}
