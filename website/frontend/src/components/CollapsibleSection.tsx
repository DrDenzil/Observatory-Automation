import { useState, ReactNode } from 'react';
import styles from './CollapsibleSection.module.css';

interface Props {
  title: ReactNode;
  storageKey: string;
  defaultOpen?: boolean;
  children: ReactNode;
  className?: string;
}

export function CollapsibleSection({ title, storageKey, defaultOpen = true, children, className }: Props) {
  const [open, setOpen] = useState(() => {
    const stored = localStorage.getItem(`section:${storageKey}`);
    return stored === null ? defaultOpen : stored === 'true';
  });

  function toggle() {
    const next = !open;
    setOpen(next);
    localStorage.setItem(`section:${storageKey}`, String(next));
  }

  return (
    <div className={`card ${styles.section} ${className ?? ''}`}>
      <button className={styles.header} onClick={toggle} aria-expanded={open}>
        <span className={styles.title}>{title}</span>
        <svg
          className={`${styles.chevron} ${open ? styles.chevronOpen : ''}`}
          width="16" height="16" viewBox="0 0 24 24"
          fill="none" stroke="currentColor" strokeWidth="2.5"
        >
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </button>
      {open && <div className={styles.body}>{children}</div>}
    </div>
  );
}
