import { useState, FormEvent } from 'react';
import { useAuth } from '@/hooks/useAuth';
import Sidebar from '@/components/layout/Sidebar';
import TopBar from '@/components/layout/TopBar';
import MonteCarloBandChart from '@/components/charts/MonteCarloBandChart';
import type { MesProyeccion, ProyeccionGlobal } from '@/types/accounting';
import { Loader2, Play, TrendingUp, AlertTriangle, CheckCircle2 } from 'lucide-react';
import { formatCurrency } from '@/utils/formatters';

export default function TreasuryProjectionPage() {
  const { user } = useAuth();

  const [horizonte, setHorizonte] = useState(12);
  const [simulaciones, setSimulaciones] = useState(10000);
  const [loading, setLoading] = useState(false);
  const [resultados, setResultados] = useState<{
    meses: MesProyeccion[];
    global: ProyeccionGlobal;
  } | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleSimulate = async (e: FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setResultados(null);

    try {
      const response = await api.post('/api/treasury/simulate', {
        empresa_id: user?.empresa_id || 1,
        horizonte_meses: horizonte,
        num_simulaciones: simulaciones,
      });

      const data = response.data;
      setResultados({
        meses: data.meses,
        global: data.global,
      });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Error desconocido');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex h-screen">
      <Sidebar />
      <div className="flex flex-1 flex-col overflow-hidden">
        <TopBar
          title="Proyección de Tesorería"
          subtitle="Simulación Monte Carlo — Análisis probabilístico de flujo de caja"
        />

        <main className="flex-1 overflow-y-auto p-6">
          <div className="mx-auto max-w-7xl space-y-6">
            {/* Formulario de configuración */}
            <div className="card">
              <h3 className="mb-4 flex items-center gap-2 text-base font-semibold text-gray-900">
                <TrendingUp className="h-5 w-5 text-erp-600" />
                Configurar Simulación
              </h3>

              <form onSubmit={handleSimulate} className="flex flex-wrap items-end gap-4">
                <div>
                  <label htmlFor="horizonte" className="block text-sm font-medium text-gray-700">
                    Horizonte temporal
                  </label>
                  <select
                    id="horizonte"
                    value={horizonte}
                    onChange={(e) => setHorizonte(Number(e.target.value))}
                    className="input-field mt-1 w-40"
                  >
                    <option value={3}>3 meses</option>
                    <option value={6}>6 meses</option>
                    <option value={12}>12 meses</option>
                    <option value={24}>24 meses</option>
                  </select>
                </div>

                <div>
                  <label htmlFor="simulaciones" className="block text-sm font-medium text-gray-700">
                    Nº simulaciones
                  </label>
                  <select
                    id="simulaciones"
                    value={simulaciones}
                    onChange={(e) => setSimulaciones(Number(e.target.value))}
                    className="input-field mt-1 w-40"
                  >
                    <option value={1000}>1.000</option>
                    <option value={5000}>5.000</option>
                    <option value={10000}>10.000</option>
                    <option value={50000}>50.000</option>
                  </select>
                </div>

                <button type="submit" className="btn-primary" disabled={loading}>
                  {loading ? (
                    <>
                      <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                      Simulando...
                    </>
                  ) : (
                    <>
                      <Play className="mr-2 h-4 w-4" />
                      Ejecutar Simulación
                    </>
                  )}
                </button>
              </form>
            </div>

            {/* Error */}
            {error && (
              <div className="flex items-center gap-3 rounded-md bg-red-50 p-4 text-sm text-red-700 ring-1 ring-inset ring-red-600/10">
                <AlertTriangle className="h-5 w-5 flex-shrink-0" />
                <div>
                  <p className="font-medium">Error en la simulación</p>
                  <p className="mt-1">{error}</p>
                </div>
              </div>
            )}

            {/* Resultados */}
            {resultados && resultados.meses.length > 0 && (
              <>
                {/* Resumen global */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                  <div className="card text-center">
                    <p className="text-xs font-medium text-gray-500">Probabilidad de déficit</p>
                    <p
                      className={`mt-2 text-3xl font-bold ${
                        resultados.global.prob_deficit_total > 0.3
                          ? 'text-red-600'
                          : resultados.global.prob_deficit_total > 0.1
                          ? 'text-yellow-600'
                          : 'text-green-600'
                      }`}
                    >
                      {(resultados.global.prob_deficit_total * 100).toFixed(1)}%
                    </p>
                    {resultados.global.prob_deficit_total < 0.1 ? (
                      <p className="mt-1 text-xs text-green-500">
                        <CheckCircle2 className="inline h-3 w-3" /> Riesgo bajo
                      </p>
                    ) : (
                      <p className="mt-1 text-xs text-red-500">
                        <AlertTriangle className="inline h-3 w-3" /> Riesgo elevado
                      </p>
                    )}
                  </div>

                  <div className="card text-center">
                    <p className="text-xs font-medium text-gray-500">Mejor escenario (P90)</p>
                    <p className="mt-2 text-3xl font-bold text-green-600">
                      {formatCurrency(resultados.global.mejor_escenario)}
                    </p>
                  </div>

                  <div className="card text-center">
                    <p className="text-xs font-medium text-gray-500">Peor escenario (P10)</p>
                    <p className="mt-2 text-3xl font-bold text-red-600">
                      {formatCurrency(resultados.global.peor_escenario)}
                    </p>
                  </div>
                </div>

                {/* Gráfico de bandas */}
                <MonteCarloBandChart
                  meses={resultados.meses}
                  global={resultados.global}
                />

                {/* Tabla de percentiles por mes */}
                <div className="card">
                  <h3 className="mb-4 text-base font-semibold text-gray-900">
                    Detalle por Mes
                  </h3>
                  <div className="overflow-x-auto">
                    <table className="data-table">
                      <thead>
                        <tr>
                          <th>Mes</th>
                          <th>P10 (Pesimista)</th>
                          <th>P50 (Mediana)</th>
                          <th>P90 (Optimista)</th>
                          <th>Prob. Déficit</th>
                          <th>Estado</th>
                        </tr>
                      </thead>
                      <tbody>
                        {resultados.meses.map((mes) => (
                          <tr key={mes.mes}>
                            <td className="font-medium">Mes {mes.mes}</td>
                            <td className="text-red-600">{formatCurrency(mes.p10)}</td>
                            <td className="font-medium text-blue-600">
                              {formatCurrency(mes.p50)}
                            </td>
                            <td className="text-green-600">{formatCurrency(mes.p90)}</td>
                            <td>{(mes.prob_deficit * 100).toFixed(1)}%</td>
                            <td>
                              {mes.prob_deficit < 0.05 ? (
                                <span className="badge badge-success">Bajo riesgo</span>
                              ) : mes.prob_deficit < 0.2 ? (
                                <span className="badge badge-warning">Riesgo medio</span>
                              ) : (
                                <span className="badge badge-danger">Riesgo alto</span>
                              )}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              </>
            )}

            {/* Placeholder inicial */}
            {!resultados && !loading && !error && (
              <div className="card text-center py-16">
                <TrendingUp className="mx-auto h-12 w-12 text-gray-300" />
                <h3 className="mt-4 text-lg font-semibold text-gray-900">
                  Configura y ejecuta una simulación
                </h3>
                <p className="mt-2 text-sm text-gray-500 max-w-md mx-auto">
                  Utiliza los parámetros del formulario para ejecutar una simulación Monte Carlo
                  que proyectará la tesorería de tu empresa con un análisis probabilístico.
                </p>
              </div>
            )}
          </div>
        </main>
      </div>
    </div>
  );
}
