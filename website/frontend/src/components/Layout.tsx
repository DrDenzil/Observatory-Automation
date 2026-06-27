import { Outlet, Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Header } from './Header';
import { Footer } from './Footer';
import { DecorativeRects } from './DecorativeRects';

export function Layout() {
  const { user, loading } = useAuth();

  if (loading) {
    return <div style={{ display: 'flex', justifyContent: 'center', padding: '4rem' }}>Loading...</div>;
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  return (
    <div style={{ position: 'relative', minHeight: '100vh' }}>
      <DecorativeRects />
      <div style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column', position: 'relative', zIndex: 1 }}>
        <Header />
        <main style={{ maxWidth: 1200, width: '100%', margin: '0 auto', padding: '1.5rem', flex: 1 }}>
          <Outlet />
        </main>
        <Footer />
      </div>
    </div>
  );
}
