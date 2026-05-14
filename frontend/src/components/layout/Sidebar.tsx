import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import {
  LayoutDashboard,
  BookOpen,
  FileText,
  Landmark,
  TrendingUp,
  LogOut,
  ChevronDown,
  ChevronRight,
} from 'lucide-react';
import { useState } from 'react';

interface NavItem {
  label: string;
  icon: React.ReactNode;
  path?: string;
  permission?: string;
  children?: { label: string; path: string; permission?: string }[];
}

export default function Sidebar() {
  const { user, logout, hasPermission } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();

  const [expandedSections, setExpandedSections] = useState<Record<string, boolean>>({
    contabilidad: true,
  });

  const toggleSection = (section: string) => {
    setExpandedSections((prev) => ({ ...prev, [section]: !prev[section] }));
  };

  const navItems: NavItem[] = [
    {
      label: 'Dashboard',
      icon: <LayoutDashboard className="h-5 w-5" />,
      path: '/dashboard',
      permission: 'dashboard.read',
    },
    {
      label: 'Contabilidad',
      icon: <BookOpen className="h-5 w-5" />,
      permission: 'accounting.read',
      children: [
        { label: 'Plan de Cuentas', path: '/contabilidad/plan-cuentas' },
        { label: 'Libro Diario', path: '/contabilidad/diario' },
        { label: 'Libro Mayor', path: '/contabilidad/mayor' },
        { label: 'Balance', path: '/contabilidad/balance' },
        { label: 'Cuenta de Resultados', path: '/contabilidad/resultados' },
      ],
    },
    {
      label: 'Facturación',
      icon: <FileText className="h-5 w-5" />,
      permission: 'invoices.read',
      children: [
        { label: 'Facturas Emitidas', path: '/facturas/emitidas' },
        { label: 'Facturas Recibidas', path: '/facturas/recibidas' },
        { label: 'Clientes y Proveedores', path: '/facturas/terceros' },
      ],
    },
    {
      label: 'Conciliación Bancaria',
      icon: <Landmark className="h-5 w-5" />,
      path: '/conciliacion',
      permission: 'reconciliation.read',
    },
    {
      label: 'Proyección Tesorería',
      icon: <TrendingUp className="h-5 w-5" />,
      path: '/tesoreria',
      permission: 'treasury.read',
    },
  ];

  return (
    <aside className="flex h-full w-64 flex-col bg-erp-950 text-white">
      {/* Logo */}
      <div className="flex h-16 items-center border-b border-erp-800 px-4">
        <button onClick={() => navigate('/dashboard')} className="flex items-center gap-2">
          <div className="flex h-8 w-8 items-center justify-center rounded bg-erp-600 font-bold text-white">
            CV
          </div>
          <div>
            <span className="text-sm font-semibold">ContaVisión</span>
            <span className="ml-1 text-[10px] text-erp-400">ERP</span>
          </div>
        </button>
      </div>

      {/* User info */}
      <div className="border-b border-erp-800 px-4 py-3">
        <div className="flex items-center gap-3">
          <div className="flex h-9 w-9 items-center justify-center rounded-full bg-erp-700 text-sm font-medium">
            {user?.nombre.charAt(0).toUpperCase()}
          </div>
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-medium">{user?.nombre}</p>
            <p className="truncate text-xs text-erp-400">{user?.rol}</p>
          </div>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-2 py-4">
        {navItems.map((item) => {
          // Skip if user doesn't have permission
          if (item.permission && !hasPermission(item.permission)) return null;

          if (item.children) {
            const isExpanded = expandedSections[item.label.toLowerCase()] ?? false;

            return (
              <div key={item.label} className="mb-1">
                <button
                  onClick={() => toggleSection(item.label.toLowerCase())}
                  className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-erp-300 hover:bg-erp-800 hover:text-white transition-colors"
                >
                  {item.icon}
                  <span className="flex-1 text-left">{item.label}</span>
                  {isExpanded ? (
                    <ChevronDown className="h-4 w-4" />
                  ) : (
                    <ChevronRight className="h-4 w-4" />
                  )}
                </button>
                {isExpanded && (
                  <div className="ml-4 mt-1 border-l border-erp-800 pl-4">
                    {item.children.map((child) => {
                      if (child.permission && !hasPermission(child.permission)) return null;
                      const isActive = location.pathname === child.path;

                      return (
                        <Link
                          key={child.path}
                          to={child.path}
                          className={`block rounded-md px-3 py-1.5 text-sm transition-colors ${
                            isActive
                              ? 'bg-erp-800 text-white font-medium'
                              : 'text-erp-400 hover:text-white hover:bg-erp-800'
                          }`}
                        >
                          {child.label}
                        </Link>
                      );
                    })}
                  </div>
                )}
              </div>
            );
          }

          // Simple link (no children)
          const isActive = location.pathname === item.path;

          return (
            <Link
              key={item.path!}
              to={item.path!}
              className={`mb-1 flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                isActive
                  ? 'bg-erp-800 text-white'
                  : 'text-erp-300 hover:bg-erp-800 hover:text-white'
              }`}
            >
              {item.icon}
              {item.label}
            </Link>
          );
        })}
      </nav>

      {/* Footer */}
      <div className="border-t border-erp-800 p-4">
        <button
          onClick={logout}
          className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-erp-400 hover:bg-erp-800 hover:text-white transition-colors"
        >
          <LogOut className="h-5 w-5" />
          Cerrar Sesión
        </button>
      </div>
    </aside>
  );
}
