import { useState, useEffect, useRef } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useTheme } from '../contexts/ThemeContext';
import { HertsLogo } from './HertsLogo';
import styles from './Header.module.css';

export function Header() {
  const { user, logout, isStaff } = useAuth();
  const { theme, toggle } = useTheme();
  const navigate = useNavigate();
  const location = useLocation();
  const [open, setOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  const handleLogout = () => {
    logout();
    navigate('/login');
    setOpen(false);
  };

  // Close on outside click
  useEffect(() => {
    function onMouseDown(e: MouseEvent) {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    if (open) document.addEventListener('mousedown', onMouseDown);
    return () => document.removeEventListener('mousedown', onMouseDown);
  }, [open]);

  // Close on route change
  useEffect(() => { setOpen(false); }, [location.pathname]);

  const isActive = (path: string) =>
    path === '/' ? location.pathname === '/' : location.pathname.startsWith(path);

  const close = () => setOpen(false);

  return (
    <header className={styles.header}>
      <div className={styles.inner}>
        <Link to="/" className={styles.brand} onClick={close}>
          <HertsLogo className={styles.logo} />
          <div className={styles.divider} />
          <span className={styles.title}>Bayfordbury Observatory</span>
        </Link>

        <div className={styles.right}>
          <label className={styles.nightToggle} title={theme === 'dark' ? 'Switch to day mode' : 'Switch to night vision'}>
            <input
              type="checkbox"
              checked={theme === 'dark'}
              onChange={toggle}
            />
            <span className={styles.nightTrack}>
              <span className={styles.nightThumb} />
            </span>
            <span className={styles.nightLabel}>Night Mode</span>
          </label>

          {user && (
            <div className={styles.menuWrap} ref={menuRef}>
              <button
                className={`${styles.burger} ${open ? styles.burgerOpen : ''}`}
                onClick={() => setOpen(o => !o)}
                aria-label={open ? 'Close menu' : 'Open menu'}
                aria-expanded={open}
              >
                <span />
                <span />
                <span />
              </button>

              {open && (
                <div className={styles.dropdown}>
                  <nav className={styles.dropNav}>
                    <Link to="/" className={`${styles.dropLink} ${isActive('/') ? styles.dropLinkActive : ''}`} onClick={close}>
                      Dashboard
                    </Link>
                    <Link to="/request/new" className={`${styles.dropLink} ${isActive('/request') ? styles.dropLinkActive : ''}`} onClick={close}>
                      New Request
                    </Link>
                    <Link to="/telescopes" className={`${styles.dropLink} ${isActive('/telescopes') ? styles.dropLinkActive : ''}`} onClick={close}>
                      Telescopes
                    </Link>
                    <Link to="/expcalc" className={`${styles.dropLink} ${isActive('/expcalc') ? styles.dropLinkActive : ''}`} onClick={close}>
                      Exposure Calculator
                    </Link>
                    <Link to="/allsky" className={`${styles.dropLink} ${isActive('/allsky') ? styles.dropLinkActive : ''}`} onClick={close}>
                      All-Sky Camera
                    </Link>
                    {isStaff && (
                      <Link to="/staff" className={`${styles.dropLink} ${isActive('/staff') ? styles.dropLinkActive : ''}`} onClick={close}>
                        Staff Dashboard
                      </Link>
                    )}
                    {user.role === 'admin' && (
                      <Link to="/users" className={`${styles.dropLink} ${isActive('/users') ? styles.dropLinkActive : ''}`} onClick={close}>
                        User Management
                      </Link>
                    )}
                  </nav>

                  <div className={styles.dropUser}>
                    <div className={styles.dropUserInfo}>
                      <span className={styles.dropUserName}>{user.name}</span>
                      <span className={`badge badge-${user.role === 'admin' ? 'approved' : user.role === 'staff' ? 'submitted' : 'draft'}`}>
                        {user.role}
                      </span>
                    </div>
                    <button onClick={handleLogout} className="btn btn-secondary btn-sm">
                      Logout
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
