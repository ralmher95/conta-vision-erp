import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/hooks/useAuth';
import api from '@/services/api';
import Sidebar from '@/components/layout/Sidebar';
import TopBar from '@/components/layout/TopBar';
import { Plus, Search } from 'lucide-react';
import { formatCurrency } from '@/utils/formatters';
import type { CuentaContable } from '@/types/accounting';

const tipoLabels: Record<string, string> = {
  activo: 'Activo',
  pasivo: 'Pasivo',
  patrimonio_neto: 'Patrimonio Neto',
  ingreso: 'Ingreso',
  gasto: 'Gasto',
};

export default function ChartOfAccountsPage() {
  const { user } = useAuth();
  const [cuentas, setCuentas] = useState<import('@/types/accounting').CuentaContable[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [search, setSearch] = useState('');

  const fetchCuentas = useCallback(async () => {
    if (!user?.empresa_id) return;
    setLoading(true);
    setError(null);
    try {
       const response = await api.get<{ cuentas: CuentaContable[] }>(
         `/api/cuentas?empresa_id=${user.empresa_id}&search=${search}`
       );
      setCuentas(response.data.cuentas || response.data || []);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Error cargando cuentas');
    } finally {
      setLoading(false);
    }
  }, [user?.empresa_id]);

  useEffect(() => {
    fetchCuentas();
  }, [fetchCuentas]);

  const filteredCuentas = cuentas.filter((c) => {
    if (!search) return true;
    const q = search.toLowerCase();
    return (
      c.codigo.toLowerCase().includes(q) ||
      c.descripcion.toLowerCase().includes(q)
    );
  });

  return (
    <div className="flex h-screen">
      <Sidebar />
      <div className="flex flex-1 flex-col overflow-hidden">
        <TopBar
          title="Plan de Cuentas"
          subtitle="Gestión del plan contable de la empresa"
        />

        <main className="flex-1 overflow-y-auto p-6">
          <div className="mx-auto max-w-7xl space-y-6">
            {/* Toolbar */}
            <div className="flex items-center justify-between">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                  type="text"
                  placeholder="Buscar por código o descripción..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  className="input-field pl-10 w-80"
                />
              </div>
              <button className="btn-primary" onClick={() => setShowModal(true)}>
                <Plus className="mr-2 h-4 w-4" />
                Nueva Cuenta
              </button>
            </div>

            {/* Error */}
            {error && (
              <div className="rounded-md bg-red-50 p-3 text-sm text-red-700 ring-1 ring-inset ring-red-600/10">
                {error}
              </div>
            )}

            {/* Tabla */}
            <div className="card p-0">
              <div className="overflow-x-auto">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Código</th>
                      <th>Descripción</th>
                      <th>Tipo</th>
                      <th className="text-right">Saldo</th>
                      <th>Estado</th>
                    </tr>
                  </thead>
                  <tbody>
                    {loading ? (
                      <tr>
                        <td colSpan={5} className="py-8 text-center text-gray-500">
                          Cargando plan de cuentas...
                        </td>
                      </tr>
                    ) : filteredCuentas.length > 0 ? (
                      filteredCuentas.map((cuenta) => (
                        <tr key={cuenta.id} className="hover:bg-gray-50 transition-colors">
                          <td className="font-mono font-medium">{cuenta.codigo}</td>
                          <td className="max-w-xs truncate">{cuenta.descripcion}</td>
                          <td>
                            <span
                              className={`badge ${
                                cuenta.tipo === 'activo'
                                  ? 'badge-info'
                                  : cuenta.tipo === 'pasivo'
                                  ? 'badge-warning'
                                  : cuenta.tipo === 'ingreso'
                                  ? 'badge-success'
                                  : cuenta.tipo === 'gasto'
                                  ? 'badge-danger'
                                  : 'badge-info'
                              }`}
                            >
                              {tipoLabels[cuenta.tipo] || cuenta.tipo}
                            </span>
                          </td>
                          <td className="text-right font-medium">
                            {formatCurrency(cuenta.saldo_actual)}
                          </td>
                          <td>
                            {cuenta.activa ? (
                              <span className="badge badge-success">Activa</span>
                            ) : (
                              <span className="badge badge-danger">Inactiva</span>
                            )}
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan={5} className="py-8 text-center text-gray-500">
                          No hay cuentas registradas. Haz clic en "Nueva Cuenta" para crear una.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Modal: Nueva Cuenta (formulario pendiente de implementación POST) */}
            {showModal && (
              <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                  <h3 className="mb-4 text-lg font-semibold text-gray-900">
                    Nueva Cuenta Contable
                  </h3>
                  <p className="mb-4 text-sm text-gray-500">
                    El endpoint POST /api/cuentas está pendiente de implementación en el backend.
                  </p>
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700">
                        Código
                      </label>
                      <input type="text" className="input-field mt-1" placeholder="Ej: 4300000000" />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700">
                        Descripción
                      </label>
                      <input type="text" className="input-field mt-1" placeholder="Clientes" />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700">
                        Tipo
                      </label>
                      <select className="input-field mt-1">
                        <option value="activo">Activo</option>
                        <option value="pasivo">Pasivo</option>
                        <option value="patrimonio_neto">Patrimonio Neto</option>
                        <option value="ingreso">Ingreso</option>
                        <option value="gasto">Gasto</option>
                      </select>
                    </div>
                  </div>
                  <div className="mt-6 flex justify-end gap-3">
                    <button
                      className="btn-secondary"
                      onClick={() => setShowModal(false)}
                    >
                      Cancelar
                    </button>
                    <button
                      className="btn-primary"
                      onClick={() => {
                        setShowModal(false);
                        alert('Funcionalidad de creación pendiente de implementación del endpoint POST /api/cuentas');
                      }}
                    >
                      Guardar
                    </button>
                  </div>
                </div>
              </div>
            )}
          </div>
        </main>
      </div>
    </div>
  );
}
