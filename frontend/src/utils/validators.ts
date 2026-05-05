import type { LineaAsiento } from '@/types/accounting';

/**
 * Valida que un array de líneas de asiento cumpla la partida doble.
 * Retorna null si es válido, o un string con el error.
 */
export function validarPartidaDoble(lineas: LineaAsiento[]): string | null {
  if (lineas.length === 0) {
    return 'El asiento debe tener al menos una línea';
  }

  if (lineas.length < 2) {
    return 'La partida doble requiere al menos 2 líneas';
  }

  let totalDebe = 0;
  let totalHaber = 0;

  for (const linea of lineas) {
    if (linea.debe < 0 || linea.haber < 0) {
      return 'Los importes no pueden ser negativos';
    }

    if (linea.debe > 0 && linea.haber > 0) {
      return 'Una línea no puede tener importe en debe y haber simultáneamente';
    }

    if (linea.debe === 0 && linea.haber === 0) {
      return `La línea "${linea.descripcion || 'sin descripción'}" no tiene importe`;
    }

    if (!linea.cuenta_id) {
      return 'Todas las líneas deben tener una cuenta contable asignada';
    }

    totalDebe += linea.debe;
    totalHaber += linea.haber;
  }

  const diferencia = Math.round((totalDebe - totalHaber) * 100) / 100;

  if (Math.abs(diferencia) > 0.01) {
    return `La partida doble no cuadra. Debe: ${totalDebe.toFixed(2)} € | Haber: ${totalHaber.toFixed(2)} € | Diferencia: ${diferencia.toFixed(2)} €`;
  }

  return null;
}

/**
 * Valida un email.
 */
export function validarEmail(email: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/**
 * Valida que un CIF español tenga formato correcto.
 */
export function validarCIF(cif: string): boolean {
  return /^[ABCDEFGHJKLMNPQRSUVW][0-9]{7}[0-9A-J]$/.test(cif.toUpperCase());
}

/**
 * Valida que un IBAN español tenga formato correcto.
 */
export function validarIBAN(iban: string): boolean {
  return /^ES\d{22}$/.test(iban.replace(/\s/g, ''));
}
