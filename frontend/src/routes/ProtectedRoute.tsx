import { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';

interface ProtectedRouteProps {
  children: ReactNode;
  requiredPermission?: string;
}

/**
 * Wrapper de ruta protegida.
 *
 * - Si no hay autenticación → redirige a /login
 * - Si no tiene el permiso requerido → redirige a /no-permission
 * - Si pasa ambas comprobaciones → renderiza los children
 */
export default function ProtectedRoute({ children, requiredPermission }: ProtectedRouteProps) {
  const { isAuthenticated, loading, hasPermission } = useAuth();

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <div className="text-center">
          <div className="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-erp-600 border-t-transparent"></div>
          <p className="mt-3 text-sm text-gray-500">Verificando sesión...</p>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  if (requiredPermission && !hasPermission(requiredPermission)) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <div className="text-center">
          <h1 className="text-4xl font-bold text-gray-900">403</h1>
          <p className="mt-2 text-lg text-gray-500">
            No tienes permisos para acceder a esta sección.
          </p>
          <a href="/dashboard" className="mt-4 inline-block text-erp-600 hover:text-erp-700">
            Volver al dashboard
          </a>
        </div>
      </div>
    );
  }

  return <>{children}</>;
}
