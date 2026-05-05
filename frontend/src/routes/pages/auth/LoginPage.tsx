import { useState, FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import { Eye, EyeOff, Loader2 } from 'lucide-react';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    const success = await login(email, password);

    if (success) {
      navigate('/dashboard');
    } else {
      setError('Credenciales inválidas. Inténtalo de nuevo.');
    }

    setLoading(false);
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-100 px-4">
      <div className="w-full max-w-md">
        {/* Logo */}
        <div className="mb-8 text-center">
          <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-erp-600 text-2xl font-bold text-white shadow-lg">
            CV
          </div>
          <h1 className="mt-4 text-2xl font-bold text-gray-900">ContaVisión ERP</h1>
          <p className="mt-1 text-sm text-gray-500">
            Gestión contable inteligente con simulaciones de Monte Carlo
          </p>
        </div>

        {/* Form */}
        <div className="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200">
          <h2 className="text-lg font-semibold text-gray-900">Iniciar Sesión</h2>
          <p className="mt-1 text-sm text-gray-500">Introduce tus credenciales de acceso</p>

          {error && (
            <div className="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700 ring-1 ring-inset ring-red-600/10">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="mt-6 space-y-5">
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                Email
              </label>
              <input
                id="email"
                type="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="input-field mt-1"
                placeholder="usuario@empresa.com"
              />
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                Contraseña
              </label>
              <div className="relative mt-1">
                <input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="input-field pr-10"
                  placeholder="••••••••"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-500"
                >
                  {showPassword ? (
                    <EyeOff className="h-4 w-4" />
                  ) : (
                    <Eye className="h-4 w-4" />
                  )}
                </button>
              </div>
            </div>

            <button type="submit" className="btn-primary w-full" disabled={loading}>
              {loading ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Iniciando sesión...
                </>
              ) : (
                'Iniciar Sesión'
              )}
            </button>
          </form>

          <div className="mt-6 rounded-md bg-gray-50 p-3 text-xs text-gray-500">
            <p className="font-medium">Demo credentials:</p>
            <p className="mt-1 font-mono">admin@contavision.local / admin123</p>
          </div>
        </div>

        <p className="mt-6 text-center text-xs text-gray-400">
          © 2025 ContaVisión ERP — Proyecto de portafolio profesional
        </p>
      </div>
    </div>
  );
}
