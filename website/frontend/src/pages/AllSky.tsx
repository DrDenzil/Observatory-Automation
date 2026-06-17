import { useState, useEffect } from 'react';
import { api } from '../api/client';
import styles from './AllSky.module.css';

interface AllSkyData {
  camera_id: string;
  camera_name: string;
  image: string;
  image_url: string;
  timelapse_available: boolean;
  timelapse_date: string | null;
  timelapse_url: string | null;
}

interface CameraInfo {
  id: string;
  name: string;
  emoji: string;
}

const CAMERAS: CameraInfo[] = [
  { id: 'bayfordbury_night', name: 'Bayfordbury Night', emoji: '🌙' },
  { id: 'bayfordbury_day', name: 'Bayfordbury Day', emoji: '☀️' },
  { id: 'hemel', name: 'Hemel', emoji: '📹' },
];

export function AllSky() {
  const [activeCamera, setActiveCamera] = useState(CAMERAS[0].id);
  const [data, setData] = useState<AllSkyData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null);

  const loadData = async () => {
    try {
      setError(null);
      const result = await api.get<AllSkyData>(`/allsky/${activeCamera}/latest`);
      setData(result);
      setLastUpdated(new Date());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load camera data');
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

  const activeCam = CAMERAS.find(c => c.id === activeCamera);

  return (
    <div>
      <h1 className={styles.heading}>All-Sky Cameras</h1>
      <p className={styles.subtext}>Live views from observatory all-sky cameras</p>

      {/* Camera Selector */}
      <div className={styles.cameraSelector}>
        {CAMERAS.map(cam => (
          <button
            key={cam.id}
            onClick={() => setActiveCamera(cam.id)}
            className={`${styles.cameraBtn} ${activeCamera === cam.id ? styles.active : ''}`}
          >
            {cam.emoji} {cam.name}
          </button>
        ))}
      </div>

      {loading && !data ? (
        <p className={styles.loading}>Loading {activeCam?.name} camera...</p>
      ) : data ? (
        <div className={`card ${styles.content}`}>
          {/* Latest Image */}
          <div className={styles.section}>
            <h2 className={styles.sectionTitle}>Latest Image</h2>
            <div className={styles.imageContainer}>
              <img
                key={data.image}
                src={data.image_url}
                alt={`${data.camera_name} latest image`}
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
              <strong>Camera:</strong> {data.camera_name}
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
