import { useState, useCallback } from 'react';
import api from '@/services/api';

interface UseApiState<T> {
  data: T | null;
  loading: boolean;
  error: string | null;
}

/**
 * Custom hook para llamadas a la API con manejo de estados.
 *
 * Uso:
 *   const { data, loading, error, execute } = useApi<AsientoContable>();
 *
 *   const handleFetch = async () => {
 *     await execute(() => api.get('/api/asientos/1'));
 *   };
 */
export function useApi<T = unknown>() {
  const [state, setState] = useState<UseApiState<T>>({
    data: null,
    loading: false,
    error: null,
  });

  const execute = useCallback(
    async (requestFn: () => Promise<{ data: T }>): Promise<T | null> => {
      setState({ data: null, loading: true, error: null });

      try {
        const response = await requestFn();
        setState({ data: response.data, loading: false, error: null });
        return response.data;
      } catch (err) {
        const message = err instanceof Error ? err.message : 'Error desconocido';
        setState({ data: null, loading: false, error: message });
        return null;
      }
    },
    []
  );

  const reset = useCallback(() => {
    setState({ data: null, loading: false, error: null });
  }, []);

  return { ...state, execute, reset };
}
