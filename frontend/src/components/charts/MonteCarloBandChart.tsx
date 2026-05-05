import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js';
import { Line } from 'react-chartjs-2';
import type { MesProyeccion, ProyeccionGlobal } from '@/types/accounting';
import { formatCurrency } from '@/utils/formatters';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Legend,
  Filler
);

interface MonteCarloBandChartProps {
  meses: MesProyeccion[];
  global: ProyeccionGlobal;
}

/**
 * Gráfico de bandas de confianza de Monte Carlo.
 *
 * Muestra 3 líneas:
 * - P90 (optimista) con banda verde semitransparente
 * - P50 (mediana) como línea principal
 * - P10 (pesimista) con banda roja semitransparente
 */
export default function MonteCarloBandChart({ meses, global }: MonteCarloBandChartProps) {
  const labels = meses.map((m) => `Mes ${m.mes}`);

  const data = {
    labels,
    datasets: [
      // Banda P90-P50 (zona optimista)
      {
        label: 'P90 (Optimista)',
        data: meses.map((m) => m.p90),
        borderColor: 'rgba(34, 197, 94, 0.6)',
        backgroundColor: 'rgba(34, 197, 94, 0.1)',
        fill: '+1',
        tension: 0.3,
        pointRadius: 3,
        pointHoverRadius: 5,
        borderWidth: 1,
        borderDash: [5, 5],
      },
      // Línea P50 (mediana)
      {
        label: 'P50 (Mediana)',
        data: meses.map((m) => m.p50),
        borderColor: 'rgba(59, 130, 246, 1)',
        backgroundColor: 'transparent',
        fill: false,
        tension: 0.3,
        pointRadius: 4,
        pointHoverRadius: 7,
        pointBackgroundColor: 'rgba(59, 130, 246, 1)',
        borderWidth: 2,
      },
      // Banda P50-P10 (zona pesimista)
      {
        label: 'P10 (Pesimista)',
        data: meses.map((m) => m.p10),
        borderColor: 'rgba(239, 68, 68, 0.6)',
        backgroundColor: 'rgba(239, 68, 68, 0.1)',
        fill: false,
        tension: 0.3,
        pointRadius: 3,
        pointHoverRadius: 5,
        borderWidth: 1,
        borderDash: [5, 5],
      },
      // Línea de déficit (referencia)
      {
        label: 'Línea de déficit',
        data: meses.map(() => 0),
        borderColor: 'rgba(107, 114, 128, 0.5)',
        backgroundColor: 'transparent',
        fill: false,
        tension: 0,
        pointRadius: 0,
        borderWidth: 2,
        borderDash: [10, 5],
      },
    ],
  };

  const options = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      mode: 'index' as const,
      intersect: false,
    },
    plugins: {
      legend: {
        position: 'top' as const,
        labels: {
          usePointStyle: true,
          pointStyle: 'circle' as const,
          font: { size: 11 },
        },
      },
      tooltip: {
        callbacks: {
          label: (context: { dataset: { label: string }; parsed: { y: number } }) => {
            const label = context.dataset.label || '';
            const value = context.parsed.y;
            return `${label}: ${formatCurrency(value)}`;
          },
        },
      },
    },
    scales: {
      y: {
        ticks: {
          callback: (value: number) => formatCurrency(value, 'EUR').replace(',00', ''),
          font: { size: 10 },
        },
        grid: {
          color: 'rgba(0, 0, 0, 0.05)',
        },
      },
      x: {
        grid: {
          display: false,
        },
      },
    },
  };

  return (
    <div className="card">
      <div className="mb-4 flex items-center justify-between">
        <div>
          <h3 className="text-base font-semibold text-gray-900">
            Proyección de Tesorería — Monte Carlo
          </h3>
          <p className="mt-1 text-xs text-gray-500">
            {global.prob_deficit_total * 100}% probabilidad de déficit global
            {global.mes_critico > 0 && ` | Mes más crítico: ${global.mes_critico}`}
          </p>
        </div>
        <div className="flex gap-4 text-xs text-gray-500">
          <span className="flex items-center gap-1">
            <span className="h-2 w-2 rounded-full bg-green-500"></span>
            Mejor escenario: {formatCurrency(global.mejor_escenario)}
          </span>
          <span className="flex items-center gap-1">
            <span className="h-2 w-2 rounded-full bg-red-500"></span>
            Peor escenario: {formatCurrency(global.peor_escenario)}
          </span>
        </div>
      </div>
      <div className="h-80">
        <Line data={data} options={options} />
      </div>
    </div>
  );
}
