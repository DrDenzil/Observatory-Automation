import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useTheme } from '../contexts/ThemeContext';
import styles from './Header.module.css';

export function Header() {
  const { user, logout, isStaff } = useAuth();
  const { theme, toggle } = useTheme();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <header className={styles.header}>
      <div className={styles.inner}>
        <Link to="/" className={styles.brand}>
          <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor" className={styles.logo}>
            <circle cx="12" cy="12" r="3" />
            <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm0 18a8 8 0 110-16 8 8 0 010 16z" opacity="0.3" />
            <path d="M12 6a6 6 0 100 12 6 6 0 000-12zm0 10a4 4 0 110-8 4 4 0 010 8z" opacity="0.5" />
          </svg>
          <div>
            <span className={styles.title}>Bayfordbury Observatory</span>
            <span className={styles.subtitle}>University of Hertfordshire</span>
          </div>
        </Link>

        <nav className={styles.nav}>
          {user && (
            <>
              <Link to="/" className={styles.navLink}>Dashboard</Link>
              <Link to="/request/new" className={styles.navLink}>New Request</Link>
              <Link to="/telescopes" className={styles.navLink}>Telescopes</Link>
              <Link to="/expcalc" className={styles.navLink}>Exp Calc</Link>
              {isStaff && <Link to="/staff" className={styles.navLink}>Staff</Link>}
            </>
          )}
        </nav>

        <div className={styles.actions}>
          <button onClick={toggle} className={styles.themeBtn} title={theme === 'dark' ? 'Switch to day mode' : 'Switch to night vision'}>
            {theme === 'dark' ? '☀️' : '🔴'}
          </button>
          {user && (
            <div className={styles.userMenu}>
              <span className={styles.userName}>{user.name}</span>
              <span className={`badge badge-${user.role === 'admin' ? 'approved' : user.role === 'staff' ? 'submitted' : 'draft'}`}>
                {user.role}
              </span>
              <button onClick={handleLogout} className="btn btn-secondary btn-sm">Logout</button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
