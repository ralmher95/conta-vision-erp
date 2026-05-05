export interface User {
  id: number;
  nombre: string;
  email: string;
  empresa_id: number | null;
  rol: string;
  permisos: string[];
}

export interface AuthResponse {
  token: string;
  expires_at: string;
  user: User;
}

export interface AsientoContable {
  id: number;
  empresa_id: number;
  numero: number;
  fecha: string;
  descripcion: string;
  tipo: 'ordinario' | 'apertura' | 'cierre' | 'regularizacion' | 'nomina' | 'banco';
  ejercicio_fiscal: number;
  total_debe: number;
  total_haber: number;
  conciliado: boolean;
  creado_por_nombre: string;
  created_at: string;
  lineas?: LineaAsiento[];
}

export interface LineaAsiento {
  id?: number;
  cuenta_id: number;
  cuenta_codigo?: string;
  cuenta_descripcion?: string;
  cuenta_tipo?: string;
  debe: number;
  haber: number;
  descripcion?: string;
  referencia?: string;
}

export interface CuentaContable {
  id: number;
  empresa_id: number;
  codigo: string;
  descripcion: string;
  tipo: 'activo' | 'pasivo' | 'patrimonio_neto' | 'ingreso' | 'gasto';
  nivel: number;
  padre_id: number | null;
  saldo_actual: number;
  activa: boolean;
}

export interface Factura {
  id: number;
  empresa_id: number;
  tercero_id: number;
  tipo: 'emitida' | 'recibida';
  numero: string;
  fecha_emision: string;
  fecha_vencimiento: string;
  fecha_pago: string | null;
  estado: 'borrador' | 'emitida' | 'pagada' | 'vencida' | 'anulada';
  base_imponible: number;
  tipo_iva: number;
  cuota_iva: number;
  retencion: number;
  cuota_retencion: number;
  total: number;
  tercero_nombre?: string;
}

export interface LineaFactura {
  id?: number;
  factura_id: number;
  descripcion: string;
  cantidad: number;
  precio_unitario: number;
  tipo_iva: number;
  subtotal: number;
  cuota_iva: number;
  total: number;
}

export interface Tercero {
  id: number;
  empresa_id: number;
  tipo: 'cliente' | 'proveedor';
  nombre: string;
  cif?: string;
  email?: string;
  telefono?: string;
  saldo_pendiente: number;
  activo: boolean;
}

export interface KpiData {
  liquidez_corriente: number;
  fondo_manobra: number;
  ratio_endeudamiento: number;
  rentabilidad_economica: number;
  rentabilidad_financiera: number;
  cuentas_cobrar: number;
  cuentas_pagar: number;
  tesoreria_actual: number;
  facturas_vencidas: number;
  facturas_pendientes_cobro: number;
}

export interface MesProyeccion {
  mes: number;
  p10: number;
  p50: number;
  p90: number;
  prob_deficit: number;
}

export interface ProyeccionGlobal {
  prob_deficit_total: number;
  mes_critico: number;
  mejor_escenario: number;
  peor_escenario: number;
}

export interface ResultadoSimulacion {
  id: number;
  nombre: string;
  horizonte_meses: number;
  saldo_inicial: number;
  ingresos_media_mensual: number;
  gastos_media_mensual: number;
  fecha_ejecucion: string;
  duracion_ms: number;
  meses: MesProyeccion[];
  global: ProyeccionGlobal;
}

export interface ApiResponse<T> {
  data?: T;
  error?: string;
  total?: number;
  pagina?: number;
  por_pagina?: number;
  total_paginas?: number;
}
