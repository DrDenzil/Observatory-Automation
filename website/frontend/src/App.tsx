import { Routes, Route, Navigate } from 'react-router-dom';
import { Layout } from './components/Layout';
import { Login } from './pages/Login';
import { Dashboard } from './pages/Dashboard';
import { NewRequest } from './pages/NewRequest';
import { RequestDetail } from './pages/RequestDetail';
import { StaffDashboard } from './pages/StaffDashboard';
import { Telescopes } from './pages/Telescopes';
import { ExposureCalculator } from './pages/ExposureCalculator';

export function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route element={<Layout />}>
        <Route path="/" element={<Dashboard />} />
        <Route path="/request/new" element={<NewRequest />} />
        <Route path="/request/:id" element={<RequestDetail />} />
        <Route path="/staff" element={<StaffDashboard />} />
        <Route path="/telescopes" element={<Telescopes />} />
        <Route path="/expcalc" element={<ExposureCalculator />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
