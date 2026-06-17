import { useState, useEffect } from 'react';
import { api } from '../api/client';
import styles from './AllSky.module.css';

interface AllSkyData {
  camera: 'night' | 'day';
  image: string;
  image_url: string;
  timelapse_available: boolean;
  timelapse_date: string | null;
  timelapse_url: string | null;
}

export function AllSky() {
  const [activeCamera, setActiveCamera] = useState<'night' | 'day'>('night');
  const [data, setData] = useState<AllSkyData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null);

  const loadData = async () => {
    try {
      setError(null);
      const endpoint = activeCamera === 'night' ? '/allsky/night/latest' : '/allsky/day/latest';
      const result = await api.get<AllSkyData>(endpoint);
      setData(result);
      setLastUpdated(new Date());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load all-sky data');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [activeCamera]);

  useEffect(() => {
    // Auto-refresh image every 30 seconds
    const timer = setInterval(loadData, 30000);
    return () => clearInterval(timer);
  }, [activeCamera]);

  if (error) {
    return (
      <div>
        <h1 className={styles.heading}>All-Sky Cameras</h1>
        <p className={styles.error}>{error}</p>
      </div>
    );
  }

  return (
    <div>
      <h1 className={styles.heading}>All-Sky Cameras</h1>
      <p className={styles.subtext}>Live views from observatory all-sky cameras</p>

      {/* Camera Selector */}
      <div className={styles.cameraSelector}>
        <button
          onClick={() => setActiveCamera('night')}
          className={`${styles.cameraBtn} ${activeCamera === 'night' ? styles.active : ''}`}
        >
          🌙 Night Camera
        </button>
        <button
          onClick={() => setActiveCamera('day')}
          className={`${styles.cameraBtn} ${activeCamera === 'day' ? styles.active : ''}`}
        >
          ☀️ Day Camera
        </button>
      </div>

      {loading && !data ? (
        <p className={styles.loading}>Loading camera feed...</p>
      ) : data ? (
        <div className={`card ${styles.content}`}>
          {/* Latest Image */}
          <div className={styles.section}>
            <h2 className={styles.sectionTitle}>Latest Image</h2>
            <div className={styles.imageContainer}>
              <img
                key={data.image}
                src={data.image_url}
                alt={`${data.camera} camera latest image`}
                className={styles.image}
              />
              <div className={styles.imageInfo}>
                {lastUpdated && (
                  <p className={styles.timestamp}>
                    Last updated: {lastUpdated.toLocaleTimeString('en-GB')}
                  </p>
                )}
                <p className={styles.filename}>{data.image}</p>
              </div>
            </div>
          </div>

          {/* Timelapse Video */}
          {data.timelapse_available && data.timelapse_url ? (
            <div className={styles.section}>
              <h2 className={styles.sectionTitle}>
                Timelapse — {data.timelapse_date}
              </h2>
              <div className={styles.videoContainer}>
                <video
                  key={data.timelapse_url}
                  controls
                  className={styles.video}
                  controlsList="nodownload"
                >
                  <source src={data.timelapse_url} type="video/mp4" />
                  Your browser does not support the video tag.
                </video>
              </div>
            </div>
          ) : (
            <div className={`card ${styles.infoCard}`}>
              <p className={styles.infoText}>
                ⏳ Timelapse video is generated when the camera switches over (at sunrise/sunset).
              </p>
            </div>
          )}

          {/* Info */}
          <div className={styles.info}>
            <p>
              <strong>Camera:</strong> {data.camera === 'night' ? 'Night (Camera 1)' : 'Day (Camera 7)'}
            </p>
            <p>
              <strong>Image updated:</strong> Every 30 seconds
            </p>
            <p>
              <strong>Video generated:</strong> At day/night switchover
            </p>
          </div>
        </div>
      ) : null}
    </div>
  );
}
