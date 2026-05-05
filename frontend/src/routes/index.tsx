import { Routes, Route } from 'react-router-dom';
import ProtectedRoute from './ProtectedRoute';
import LoginPage from './pages/auth/LoginPage';
import DashboardPage from './pages/dashboard/DashboardPage';
import JournalPage from './pages/accounting/JournalPage';
import ChartOfAccountsPage from './pages/accounting/ChartOfAccountsPage';
import TreasuryProjectionPage from './pages/treasury/TreasuryProjectionPage';

export default function AppRoutes() {
  return (
    <Routes>
      {/* Public routes */}
      <Route path="/login" element={<LoginPage />} />

      {/* Protected routes */}
      <Route
        path="/dashboard"
        element={
          <ProtectedRoute>
            <DashboardPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/contabilidad/diario"
        element={
          <ProtectedRoute requiredPermission="accounting.read">
            <JournalPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/contabilidad/plan-cuentas"
        element={
          <ProtectedRoute requiredPermission="accounting.read">
            <ChartOfAccountsPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/tesoreria"
        element={
          <ProtectedRoute requiredPermission="treasury.read">
            <TreasuryProjectionPage />
          </ProtectedRoute>
        }
      />

      {/* Catch-all: redirect to dashboard */}
      <Route path="*" element={<DashboardPage />} />
    </Routes>
  );
}
