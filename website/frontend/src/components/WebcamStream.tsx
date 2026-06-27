import { useState, useRef, useEffect, useCallback } from 'react';
import styles from './WebcamStream.module.css';

interface Props {
  scopeId: string;
  scopeName?: string;
  arduinoAvailable?: boolean;
  onClose: () => void;
}

export function WebcamStream({ scopeId, scopeName, arduinoAvailable = false, onClose }: Props) {
  const [streaming, setStreaming] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [irLevel, setIrLevel] = useState<number>(0);
  const [irPending, setIrPending] = useState(false);
  const imgRef = useRef<HTMLImageElement>(null);
  const irLevelRef = useRef(0); // tracks current level for cleanup without causing re-runs

  const token = localStorage.getItem('token') ?? '';
  const streamUrl = `/api/scopes/${scopeId}/webcam/stream?token=${encodeURIComponent(token)}`;
  const snapshotUrl = `/api/scopes/${scopeId}/webcam/snapshot?token=${encodeURIComponent(token)}`;

  function popOut() {
    const name = scopeName ? encodeURIComponent(scopeName) : scopeId;
    window.open(
      `/webcam/${scopeId}?name=${name}`,
      `webcam-${scopeId}`,
      'width=1024,height=768,resizable=yes'
    );
  }

  function startStream() {
    setError(null);
    setStreaming(true);
  }

  function stopStream() {
    if (imgRef.current) {
      imgRef.current.src = '';
    }
    setStreaming(false);
  }

  function handleImgError() {
    setStreaming(false);
    setError('Camera unavailable — it may be in use by telescope software, or the runner is offline.');
  }

  const sendIR = useCallback(async (level: number) => {
    setIrPending(true);
    try {
      await fetch(`/api/scopes/${scopeId}/arduino/ir?level=${level}`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}` },
      });
      setIrLevel(level);
      irLevelRef.current = level;
    } catch {
      // silently ignore — hardware may not respond
    } finally {
      setIrPending(false);
    }
  }, [scopeId, token]);

  // Stop stream and turn IR off on unmount only — runs once, reads level via ref
  useEffect(() => {
    return () => {
      if (imgRef.current) {
        imgRef.current.src = '';
      }
      if (arduinoAvailable && irLevelRef.current > 0) {
        fetch(`/api/scopes/${scopeId}/arduino/ir?level=0`, {
          method: 'POST',
          headers: { Authorization: `Bearer ${token}` },
        }).catch(() => {});
      }
    };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div className={styles.panel}>
      <div className={styles.toolbar}>
        <span className={styles.label}>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M23 7l-7 5 7 5V7z"/>
            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
          </svg>
          Webcam
        </span>
        <div className={styles.actions}>
          {streaming ? (
            <button className={styles.stopBtn} onClick={stopStream} title="Stop stream">
              &#9632; Stop
            </button>
          ) : (
            <button className={styles.playBtn} onClick={startStream} title="Start stream">
              &#9654; Live
            </button>
          )}
          <button className={styles.popOutBtn} onClick={popOut} title="Pop out to window">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <polyline points="15 3 21 3 21 9"/>
              <line x1="10" y1="14" x2="21" y2="3"/>
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
            </svg>
          </button>
          <button className={styles.closeBtn} onClick={() => { stopStream(); onClose(); }} title="Close">
            &#10005;
          </button>
        </div>
      </div>

      <div className={styles.viewport}>
        {!streaming && !error && (
          <div className={styles.placeholder} onClick={startStream}>
            <div className={styles.playCircle}>
              <span className={styles.playIcon}>&#9654;</span>
            </div>
            <span className={styles.hint}>Click to start webcam</span>
          </div>
        )}

        {error && (
          <div className={styles.errorState}>
            <span className={styles.errorIcon}>&#9888;</span>
            <span className={styles.errorText}>{error}</span>
            <button className={styles.retryBtn} onClick={() => { setError(null); }}>
              Retry
            </button>
          </div>
        )}

        <img
          ref={imgRef}
          src={streaming ? streamUrl : ''}
          alt="Live webcam feed"
          className={`${styles.stream} ${streaming ? styles.streamVisible : styles.streamHidden}`}
          onError={handleImgError}
        />
      </div>

      {streaming && (
        <div className={styles.statusBar}>
          <span className={styles.liveDot} />
          <span>LIVE</span>
          <a
            href={snapshotUrl}
            download={`${scopeId}-snapshot.jpg`}
            className={styles.snapLink}
            title="Save snapshot"
          >
            &#128247; Snapshot
          </a>
        </div>
      )}

      {arduinoAvailable && (
        <div className={styles.irBar}>
          <span className={styles.irLabel}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
              <circle cx="12" cy="12" r="5"/>
              <line x1="12" y1="1" x2="12" y2="3" stroke="currentColor" strokeWidth="2"/>
              <line x1="12" y1="21" x2="12" y2="23" stroke="currentColor" strokeWidth="2"/>
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" stroke="currentColor" strokeWidth="2"/>
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" stroke="currentColor" strokeWidth="2"/>
              <line x1="1" y1="12" x2="3" y2="12" stroke="currentColor" strokeWidth="2"/>
              <line x1="21" y1="12" x2="23" y2="12" stroke="currentColor" strokeWidth="2"/>
              <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" stroke="currentColor" strokeWidth="2"/>
              <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" stroke="currentColor" strokeWidth="2"/>
            </svg>
            IR
          </span>
          <input
            type="range"
            min={0}
            max={9}
            step={1}
            value={irLevel}
            disabled={irPending}
            className={styles.irSlider}
            onChange={e => sendIR(Number(e.target.value))}
          />
          <span className={styles.irValue}>{irLevel === 0 ? 'Off' : irLevel}</span>
        </div>
      )}
    </div>
  );
}
