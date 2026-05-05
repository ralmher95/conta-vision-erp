import type { KpiData } from '@/types/accounting';
import { formatCurrency } from '@/utils/formatters';
import {
  TrendingUp,
  TrendingDown,
  DollarSign,
  AlertTriangle,
  Building2,
  Percent,
  Wallet,
  Clock,
} from 'lucide-react';

interface KpiCardsProps {
  data: KpiData | null;
  loading: boolean;
}

const kpiConfig: {
  key: keyof KpiData;
  label: string;
  icon: React.ReactNode;
  format: (value: number) => string;
  trendUp: boolean; // true = subir es bueno
  color: string;
}[] = [
  {
    key: 'tesoreria_actual',
    label: 'Tesorería Actual',
    icon: <Wallet className="h-5 w-5" />,
    format: formatCurrency,
    trendUp: true,
    color: 'bg-blue-500',
  },
  {
    key: 'liquidez_corriente',
    label: 'Liquidez Corriente',
    icon: <DollarSign className="h-5 w-5" />,
    format: (v) => v.toFixed(2) + 'x',
    trendUp: true,
    color: 'bg-green-500',
  },
  {
    key: 'ratio_endeudamiento',
    label: 'Ratio Endeudamiento',
    icon: <Percent className="h-5 w-5" />,
    format: (v) => (v * 100).toFixed(1) + '%',
    trendUp: false,
    color: 'bg-yellow-500',
  },
  {
    key: 'rentabilidad_economica',
    label: 'Rentabilidad Económica',
    icon: <TrendingUp className="h-5 w-5" />,
    format: (v) => (v * 100).toFixed(1) + '%',
    trendUp: true,
    color: 'bg-purple-500',
  },
  {
    key: 'cuentas_cobrar',
    label: 'Cuentas por Cobrar',
    icon: <Clock className="h-5 w-5" />,
    format: formatCurrency,
    trendUp: true,
    color: 'bg-erp-500',
  },
  {
    key: 'cuentas_pagar',
    label: 'Cuentas por Pagar',
    icon: <TrendingDown className="h-5 w-5" />,
    format: formatCurrency,
    trendUp: false,
    color: 'bg-orange-500',
  },
  {
    key: 'facturas_vencidas',
    label: 'Facturas Vencidas',
    icon: <AlertTriangle className="h-5 w-5" />,
    format: (v) => v.toString(),
    trendUp: false,
    color: 'bg-red-500',
  },
  {
    key: 'fondo_manobra',
    label: 'Fondo de Maniobra',
    icon: <Building2 className="h-5 w-5" />,
    format: formatCurrency,
    trendUp: true,
    color: 'bg-teal-500',
  },
];

export default function KpiCards({ data, loading }: KpiCardsProps) {
  if (loading) {
    return (
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {Array.from({ length: 8 }).map((_, i) => (
          <div key={i} className="card animate-pulse">
            <div className="flex items-center gap-3">
              <div className="h-10 w-10 rounded-lg bg-gray-200"></div>
              <div className="flex-1 space-y-2">
                <div className="h-3 w-24 rounded bg-gray-200"></div>
                <div className="h-5 w-16 rounded bg-gray-200"></div>
              </div>
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (!data) {
    return (
      <div className="card text-center text-gray-500">
        No hay datos de KPIs disponibles.
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {kpiConfig.map(({ key, label, icon, format, trendUp, color }) => {
        const value = data[key];

        return (
          <div key={key} className="card">
            <div className="flex items-center gap-3">
              <div className={`flex h-10 w-10 items-center justify-center rounded-lg text-white ${color}`}>
                {icon}
              </div>
              <div className="min-w-0 flex-1">
                <p className="truncate text-xs font-medium text-gray-500">{label}</p>
                <p className="truncate text-lg font-bold text-gray-900">{format(value)}</p>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}
