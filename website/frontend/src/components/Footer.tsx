import specsTsgLogo from '../assets/specs-tsg-banner.png';
import styles from './Footer.module.css';

export function Footer() {
  return (
    <footer className={styles.footer}>
      <div className={styles.inner}>
        <span className={styles.text}>Built and maintained by</span>
        <a
          href="https://herts365.sharepoint.com/sites/spectra/SitePages/CollabHome.aspx"
          target="_blank"
          rel="noopener noreferrer"
          className={styles.link}
          aria-label="SPECS TSG"
        >
          <img src={specsTsgLogo} alt="SPECS TSG" className={styles.logo} />
        </a>
      </div>
    </footer>
  );
}
