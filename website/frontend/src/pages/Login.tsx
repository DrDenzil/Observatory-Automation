import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useTheme } from '../contexts/ThemeContext';
import styles from './Login.module.css';

export function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const { login } = useAuth();
  const { theme, toggle } = useTheme();
  const navigate = useNavigate();

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    setSubmitting(true);
    try {
      await login(email, password);
      navigate('/');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className={styles.page}>
      <div className={styles.card}>
        <button onClick={toggle} className={styles.themeBtn} title="Toggle theme">
          {theme === 'dark' ? '☀️' : '🔴'}
        </button>

        <div className={styles.brandBar} />

        <div className={styles.content}>
          <svg viewBox="0 0 24 24" width="48" height="48" fill="var(--accent)" style={{ margin: '0 auto', display: 'block' }}>
            <circle cx="12" cy="12" r="3" />
            <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm0 18a8 8 0 110-16 8 8 0 010 16z" opacity="0.3" />
            <path d="M12 6a6 6 0 100 12 6 6 0 000-12zm0 10a4 4 0 110-8 4 4 0 010 8z" opacity="0.5" />
          </svg>
          <h1 className={styles.title}>Bayfordbury Observatory</h1>
          <p className={styles.subtitle}>University of Hertfordshire</p>

          <form onSubmit={handleSubmit} className={styles.form}>
            {error && <div className={styles.error}>{error}</div>}

            <label className={styles.label}>
              Email
              <input type="email" value={email} onChange={e => setEmail(e.target.value)} required autoFocus />
            </label>

            <label className={styles.label}>
              Password
              <input type="password" value={password} onChange={e => setPassword(e.target.value)} required />
            </label>

            <button type="submit" className="btn btn-primary" disabled={submitting} style={{ width: '100%', justifyContent: 'center' }}>
              {submitting ? 'Signing in...' : 'Sign In'}
            </button>
          </form>

          <p className={styles.demo}>
            Demo: denis@herts.ac.uk / admin
          </p>
        </div>
      </div>
    </div>
  );
}
