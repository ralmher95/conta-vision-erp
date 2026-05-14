import { useState, useCallback } from 'react';
import { useAuth } from '@/hooks/useAuth';
import { useApi } from '@/hooks/useApi';
import api from '@/services/api';
import Sidebar from '@/components/layout/Sidebar';
import TopBar from '@/components/layout/TopBar';
import JournalEntryForm from '@/components/accounting/JournalEntryForm';
import type { AsientoContable } from '@/types/accounting';
import { formatDate, formatCurrency } from '@/utils/formatters';
import { Plus, Search, Filter } from 'lucide-react';

const tipoLabels: Record<string, string> = {
  ordinario: 'Ordinario',
  apertura: 'Apertura',
  cierre: 'Cierre',
  regularizacion: 'Regularización',
  nomina: 'Nómina',
  banco: 'Banco',
};

export default function JournalPage() {
  const { user, hasPermission } = useAuth();
  const { data, loading, error, execute } = useApi<{ asientos: AsientoContable[] }>();
  const [showForm, setShowForm] = useState(false);

  const fetchAsientos = useCallback(async () => {
    if (!user?.empresa_id) return;
    await execute(() =>
      api.get(`/api/asientos?empresa_id=${user.empresa_id}&pagina=1&por_pagina=50`)
    );
  }, [user?.empresa_id, execute]);

  const handleCreated = () => {
    setShowForm(false);
    fetchAsientos();
  };

  return (
    <div className="flex h-screen">
      <Sidebar />
      <div className="flex flex-1 flex-col overflow-hidden">
        <TopBar title="Libro Diario" subtitle="Registro de asientos contables con partida doble" />

        <main className="flex-1 overflow-y-auto p-6">
          <div className="mx-auto max-w-7xl space-y-6">
            {/* Toolbar */}
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                  <input
                    type="text"
                    placeholder="Buscar asientos..."
                    className="input-field pl-10 w-64"
                  />
                </div>
                <button className="btn-secondary">
                  <Filter className="mr-2 h-4 w-4" />
                  Filtros
                </button>
              </div>

              {hasPermission('accounting.write') && (
                <button className="btn-primary" onClick={() => setShowForm(true)}>
                  <Plus className="mr-2 h-4 w-4" />
                  Nuevo Asiento
                </button>
              )}
            </div>

            {/* Error */}
            {error && (
              <div className="rounded-md bg-red-50 p-3 text-sm text-red-700 ring-1 ring-inset ring-red-600/10">
                {error}
              </div>
            )}

            {/* Table */}
            <div className="card p-0">
              <div className="overflow-x-auto">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Nº</th>
                      <th>Fecha</th>
                      <th>Descripción</th>
                      <th>Tipo</th>
                      <th className="text-right">Debe</th>
                      <th className="text-right">Haber</th>
                      <th>Estado</th>
                      <th>Creado por</th>
                    </tr>
                  </thead>
                  <tbody>
                    {loading ? (
                      <tr>
                        <td colSpan={8} className="py-8 text-center text-gray-500">
                          Cargando asientos...
                        </td>
                      </tr>
                    ) : data?.asientos && data.asientos.length > 0 ? (
                      data.asientos.map((asiento) => (
                        <tr
                          key={asiento.id}
                          className="cursor-pointer hover:bg-gray-50 transition-colors"
                        >
                          <td className="font-medium">{asiento.numero}</td>
                          <td>{formatDate(asiento.fecha)}</td>
                          <td className="max-w-xs truncate">{asiento.descripcion}</td>
                          <td>
                            <span className="badge badge-info">
                              {tipoLabels[asiento.tipo] || asiento.tipo}
                            </span>
                          </td>
                          <td className="text-right font-medium text-red-600">
                            {formatCurrency(asiento.total_debe)}
                          </td>
                          <td className="text-right font-medium text-green-600">
                            {formatCurrency(asiento.total_haber)}
                          </td>
                          <td>
                            {asiento.conciliado ? (
                              <span className="badge badge-success">Conciliado</span>
                            ) : (
                              <span className="badge badge-warning">Pendiente</span>
                            )}
                          </td>
                          <td className="text-sm text-gray-500">{asiento.creado_por_nombre}</td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan={8} className="py-8 text-center text-gray-500">
                          No hay asientos registrados. Haz clic en "Nuevo Asiento" para crear uno.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Modal: Nuevo Asiento */}
            {showForm && (
              <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div className="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-xl bg-white shadow-xl">
                  <JournalEntryForm
                    onSuccess={handleCreated}
                    onCancel={() => setShowForm(false)}
                  />
                </div>
              </div>
            )}
          </div>
        </main>
      </div>
    </div>
  );
}
