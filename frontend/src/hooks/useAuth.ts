import { useState, useCallback, useEffect } from 'react';
import type { User, AuthResponse } from '@/types/accounting';
import api from '@/services/api';

/**
 * Hook de autenticación. Gestiona login, logout y estado del usuario.
 */
export function useAuth() {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  // Al montar, verificar si hay sesión guardada
  useEffect(() => {
    const stored = localStorage.getItem('contavision_user');
    const token = localStorage.getItem('contavision_token');

    if (stored && token) {
      try {
        setUser(JSON.parse(stored));
      } catch {
        // Corrupt data → clear
        localStorage.removeItem('contavision_user');
        localStorage.removeItem('contavision_token');
      }
    }

    setLoading(false);
  }, []);

  const login = useCallback(async (email: string, password: string): Promise<boolean> => {
    try {
      const response = await api.post<AuthResponse>('/api/auth/login', {
        email,
        password,
      });

      const { token, user: userData } = response.data;

      localStorage.setItem('contavision_token', token);
      localStorage.setItem('contavision_user', JSON.stringify(userData));
      setUser(userData);

      return true;
    } catch {
      return false;
    }
  }, []);

  const logout = useCallback(() => {
    localStorage.removeItem('contavision_token');
    localStorage.removeItem('contavision_user');
    setUser(null);
    window.location.href = '/login';
  }, []);

  const hasPermission = useCallback(
    (permission: string): boolean => {
      if (!user) return false;
      if (user.rol === 'admin') return true;
      return user.permisos.includes(permission);
    },
    [user]
  );

  return { user, loading, login, logout, hasPermission, isAuthenticated: !!user };
}
