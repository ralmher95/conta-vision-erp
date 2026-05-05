import { useEffect } from 'react';
import Sidebar from '@/components/layout/Sidebar';
import TopBar from '@/components/layout/TopBar';
import KpiCards from '@/components/charts/KpiCards';
import type { KpiData } from '@/types/accounting';
import api from '@/services/api';
import { useState } from 'react';
import { formatCurrency } from '@/utils/formatters';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  ArcElement,
  Tooltip,
  Legend,
} from 'chart.js';
import { Bar, Doughnut } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Tooltip, Legend);

export default function DashboardPage() {
  const [kpiData, setKpiData] = useState<KpiData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchKpis = async () => {
      try {
        const empresaId = 1; // En producción usar user?.empresa_id
        const response = await api.get<KpiData>(`/api/dashboard/kpis?empresa_id=${empresaId}`);
        setKpiData(response.data);
      } catch (err) {
        console.error('Error cargando KPIs:', err);
        setError('Servicio no disponible');
      } finally {
        setLoading(false);
      }
    };

    fetchKpis();
  }, []);

  const barData = {
    labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
    datasets: [
      {
        label: 'Ingresos',
        data: [32000, 28000, 35000, 42000, 38000, 45000],
        backgroundColor: 'rgba(59, 130, 246, 0.7)',
        borderRadius: 4,
      },
      {
        label: 'Gastos',
        data: [25000, 22000, 28000, 30000, 27000, 32000],
        backgroundColor: 'rgba(239, 68, 68, 0.7)',
        borderRadius: 4,
      },
    ],
  };

  const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'top' as const },
      tooltip: {
        callbacks: {
          label: (context: { dataset: { label: string }; parsed: { y: number } }) =>
            `${context.dataset.label}: ${formatCurrency(context.parsed.y)}`,
        },
      },
    },
    scales: {
      y: {
        ticks: {
          callback: (value: number) =>
            `${(value / 1000).toFixed(0)}k`,
        },
      },
    },
  };

  const doughnutData = {
    labels: ['Cobradas', 'Pendientes', 'Vencidas'],
    datasets: [
      {
        data: [45, 35, 20],
        backgroundColor: ['rgba(34, 197, 94, 0.7)', 'rgba(59, 130, 246, 0.7)', 'rgba(239, 68, 68, 0.7)'],
        borderWidth: 0,
      },
    ],
  };

  return (
    <div className="flex h-screen">
      <Sidebar />
      <div className="flex flex-1 flex-col overflow-hidden">
        <TopBar title="Dashboard" subtitle="Resumen financiero de la empresa" />

        <main className="flex-1 overflow-y-auto p-6">
          <div className="mx-auto max-w-7xl space-y-6">
            {/* Error */}
            {error && (
              <div className="flex items-center gap-3 rounded-md bg-yellow-50 p-4 text-sm text-yellow-700 ring-1 ring-inset ring-yellow-600/10">
                <span className="font-medium">Servicio no disponible</span>
                <span>{error}</span>
              </div>
            )}

            {/* KPI Cards */}
            <KpiCards data={kpiData} loading={loading} />

            {/* Charts */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
              {/* Ingresos vs Gastos */}
              <div className="card lg:col-span-2">
                <h3 className="mb-4 text-base font-semibold text-gray-900">
                  Ingresos vs Gastos (últimos 6 meses)
                </h3>
                <div className="h-64">
                  <Bar data={barData} options={barOptions} />
                </div>
              </div>

              {/* Estado de facturas */}
              <div className="card">
                <h3 className="mb-4 text-base font-semibold text-gray-900">
                  Estado de Facturas
                </h3>
                <div className="h-64">
                  <Doughnut data={doughnutData} />
                </div>
                <div className="mt-4 space-y-2 text-sm">
                  <div className="flex justify-between">
                    <span className="text-gray-500">Cobradas</span>
                    <span className="font-medium text-green-600">45%</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500">Pendientes</span>
                    <span className="font-medium text-blue-600">35%</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500">Vencidas</span>
                    <span className="font-medium text-red-600">20%</span>
                  </div>
                </div>
              </div>
            </div>

            {/* Resumen rápido */}
            <div className="card">
              <h3 className="mb-4 text-base font-semibold text-gray-900">Últimos Movimientos</h3>
              <div className="overflow-x-auto">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Fecha</th>
                      <th>Descripción</th>
                      <th>Tipo</th>
                      <th>Importe</th>
                      <th>Estado</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>15/01/2025</td>
                      <td>Cobro factura F-2025-001</td>
                      <td className="text-blue-600">Ingreso</td>
                      <td className="font-medium">1.210,00 €</td>
                      <td><span className="badge badge-success">Conciliado</span></td>
                    </tr>
                    <tr>
                      <td>14/01/2025</td>
                      <td>Pago proveedor Matinsa</td>
                      <td className="text-red-600">Gasto</td>
                      <td className="font-medium">-3.450,00 €</td>
                      <td><span className="badge badge-success">Conciliado</span></td>
                    </tr>
                    <tr>
                      <td>12/01/2025</td>
                      <td>Nómina enero</td>
                      <td className="text-red-600">Gasto</td>
                      <td className="font-medium">-8.200,00 €</td>
                      <td><span className="badge badge-warning">Pendiente</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>
  );
}
