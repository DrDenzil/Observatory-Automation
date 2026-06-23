import styles from './Pager.module.css';

interface PagerProps {
  total: number;
  page: number;      // 0-indexed
  pageSize: number;
  onChange: (page: number) => void;
}

export function Pager({ total, page, pageSize, onChange }: PagerProps) {
  const totalPages = Math.ceil(total / pageSize);
  if (totalPages <= 1) return null;

  // Build page number list: always show first, last, current ±1, with ellipsis gaps
  const pages: (number | '…')[] = [];
  const add = (n: number) => { if (!pages.includes(n)) pages.push(n); };

  add(0);
  if (page > 2) pages.push('…');
  if (page > 1) add(page - 1);
  add(page);
  if (page < totalPages - 2) add(page + 1);
  if (page < totalPages - 3) pages.push('…');
  add(totalPages - 1);

  return (
    <div className={styles.pager}>
      <button
        className={styles.btn}
        disabled={page === 0}
        onClick={() => onChange(page - 1)}
      >
        ‹
      </button>

      {pages.map((p, i) =>
        p === '…' ? (
          <span key={`ellipsis-${i}`} className={styles.ellipsis}>…</span>
        ) : (
          <button
            key={p}
            className={`${styles.btn} ${p === page ? styles.active : ''}`}
            onClick={() => onChange(p)}
          >
            {p + 1}
          </button>
        )
      )}

      <button
        className={styles.btn}
        disabled={page === totalPages - 1}
        onClick={() => onChange(page + 1)}
      >
        ›
      </button>

      <span className={styles.info}>
        {page * pageSize + 1}–{Math.min((page + 1) * pageSize, total)} of {total}
      </span>
    </div>
  );
}

/** Slice an array to the current page. */
export function pageSlice<T>(items: T[], page: number, pageSize: number): T[] {
  return items.slice(page * pageSize, (page + 1) * pageSize);
}
