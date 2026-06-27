import styles from './DecorativeRects.module.css';

export function DecorativeRects() {
  return (
    <div className={styles.container} aria-hidden="true">
      <div className={`${styles.rect} ${styles.orange}`} />
      <div className={`${styles.rect} ${styles.pinkRed}`} />
      <div className={`${styles.rect} ${styles.cyan}`} />
      <div className={`${styles.rect} ${styles.blue}`} />
      <div className={`${styles.rect} ${styles.pink}`} />
      <div className={`${styles.rect} ${styles.clusterA1}`} />
      <div className={`${styles.rect} ${styles.clusterA2}`} />
      <div className={`${styles.rect} ${styles.clusterB1}`} />
      <div className={`${styles.rect} ${styles.clusterB2}`} />
    </div>
  );
}
