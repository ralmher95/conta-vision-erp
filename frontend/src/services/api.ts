import axios, { AxiosError, AxiosInstance } from 'axios';

const API_BASE_URL = (import.meta as any).env.VITE_API_URL || 'http://localhost:8080';

/**
 * Instancia de Axios configurada para la API de ContaVisión ERP.
 *
 * Interceptores:
 * - Request: Añade automáticamente el token JWT del localStorage
 * - Response: Captura 401 (token expirado) y redirige a login
 */
const api: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 15000,
});

// Interceptor de request: inyectar JWT token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('contavision_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Interceptor de response: manejar errores globalmente
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      // Token expirado o inválido → limpiar y redirigir
      localStorage.removeItem('contavision_token');
      localStorage.removeItem('contavision_user');
      window.location.href = '/login';
    }

    const message =
      (error.response?.data as { error?: string })?.error ||
      error.message ||
      'Error de conexión con el servidor';

    return Promise.reject(new Error(message));
  }
);

export default api;
