import { useState, FormEvent } from 'react';
import { useAuth } from '@/hooks/useAuth';
import type { LineaAsiento } from '@/types/accounting';
import { validarPartidaDoble } from '@/utils/validators';
import { Plus, Trash2, X, CheckCircle2, AlertCircle, Loader2 } from 'lucide-react';

interface JournalEntryFormProps {
  onSuccess: () => void;
  onCancel: () => void;
}

export default function JournalEntryForm({ onSuccess, onCancel }: JournalEntryFormProps) {
  const { user } = useAuth();

  const [fecha, setFecha] = useState(new Date().toISOString().split('T')[0]);
  const [descripcion, setDescripcion] = useState('');
  const [tipo, setTipo] = useState('ordinario');
  const [lineas, setLineas] = useState<LineaAsiento[]>([
    { cuenta_id: 0, debe: 0, haber: 0, descripcion: '' },
    { cuenta_id: 0, debe: 0, haber: 0, descripcion: '' },
  ]);
  const [validacionError, setValidacionError] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  const addLinea = () => {
    setLineas([...lineas, { cuenta_id: 0, debe: 0, haber: 0, descripcion: '' }]);
  };

  const removeLinea = (index: number) => {
    if (lineas.length <= 2) return; // Mínimo 2 líneas
    setLineas(lineas.filter((_, i) => i !== index));
  };

  const updateLinea = (index: number, field: keyof LineaAsiento, value: string | number) => {
    const nuevas = [...lineas];
    nuevas[index] = { ...nuevas[index], [field]: value };

    // Si pone debe > 0, poner haber a 0 y viceversa
    if (field === 'debe' && (value as number) > 0) {
      nuevas[index].haber = 0;
    }
    if (field === 'haber' && (value as number) > 0) {
      nuevas[index].debe = 0;
    }

    setLineas(nuevas);
  };

  const totalDebe = lineas.reduce((sum, l) => sum + (l.debe || 0), 0);
  const totalHaber = lineas.reduce((sum, l) => sum + (l.haber || 0), 0);
  const cuadra = Math.abs(totalDebe - totalHaber) < 0.01;

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();

    // Validar partida doble
    const error = validarPartidaDoble(lineas);
    if (error) {
      setValidacionError(error);
      return;
    }

    setValidacionError(null);
    setEnviando(true);

    try {
      const response = await fetch('/api/asientos', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${localStorage.getItem('contavision_token')}`,
        },
        body: JSON.stringify({
          empresa_id: user?.empresa_id || 1,
          fecha,
          descripcion,
          tipo,
          lineas: lineas.map((l) => ({
            cuenta_id: l.cuenta_id,
            debe: l.debe,
            haber: l.haber,
            descripcion: l.descripcion,
          })),
        }),
      });

      if (!response.ok) {
        const err = await response.json();
        throw new Error(err.error || 'Error al crear el asiento');
      }

      onSuccess();
    } catch (err) {
      setValidacionError(err instanceof Error ? err.message : 'Error desconocido');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
        <h2 className="text-lg font-semibold text-gray-900">Nuevo Asiento Contable</h2>
        <button onClick={onCancel} className="text-gray-400 hover:text-gray-500">
          <X className="h-5 w-5" />
        </button>
      </div>

      <form onSubmit={handleSubmit} className="p-6 space-y-6">
        {/* Cabecera */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div>
            <label className="block text-sm font-medium text-gray-700">Fecha</label>
            <input
              type="date"
              value={fecha}
              onChange={(e) => setFecha(e.target.value)}
              className="input-field mt-1"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">Tipo</label>
            <select
              value={tipo}
              onChange={(e) => setTipo(e.target.value)}
              className="input-field mt-1"
            >
              <option value="ordinario">Ordinario</option>
              <option value="apertura">Apertura</option>
              <option value="cierre">Cierre</option>
              <option value="regularizacion">Regularización</option>
              <option value="nomina">Nómina</option>
              <option value="banco">Banco</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">Descripción</label>
            <input
              type="text"
              value={descripcion}
              onChange={(e) => setDescripcion(e.target.value)}
              className="input-field mt-1"
              placeholder="Ej: Cobro factura F-2025-001"
              required
            />
          </div>
        </div>

        {/* Líneas del asiento */}
        <div>
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-sm font-medium text-gray-700">Líneas del Asiento</h3>
            <button type="button" onClick={addLinea} className="btn-secondary text-xs py-1 px-3">
              <Plus className="mr-1 h-3 w-3" />
              Añadir línea
            </button>
          </div>

          {/* Validation summary */}
          <div className="mb-3 flex items-center gap-2 rounded-md bg-gray-50 px-3 py-2 text-sm">
            {cuadra ? (
              <>
                <CheckCircle2 className="h-4 w-4 text-green-600" />
                <span className="text-green-700 font-medium">
                  Partida doble correcta: {totalDebe.toFixed(2)} € = {totalHaber.toFixed(2)} €
                </span>
              </>
            ) : (
              <>
                <AlertCircle className="h-4 w-4 text-red-600" />
                <span className="text-red-700 font-medium">
                  Descuadrado: Debe {totalDebe.toFixed(2)} € ≠ Haber {totalHaber.toFixed(2)} €
                  (dif: {(totalDebe - totalHaber).toFixed(2)} €)
                </span>
              </>
            )}
          </div>

          {validacionError && (
            <div className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-inset ring-red-600/10">
              {validacionError}
            </div>
          )}

          <div className="overflow-x-auto rounded-lg ring-1 ring-inset ring-gray-200">
            <table className="min-w-full divide-y divide-gray-300">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">
                    Cuenta ID
                  </th>
                  <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">
                    Descripción
                  </th>
                  <th className="px-3 py-2 text-right text-xs font-semibold text-red-600 uppercase">
                    Debe (€)
                  </th>
                  <th className="px-3 py-2 text-right text-xs font-semibold text-green-600 uppercase">
                    Haber (€)
                  </th>
                  <th className="px-3 py-2 w-10"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200 bg-white">
                {lineas.map((linea, index) => (
                  <tr key={index}>
                    <td className="px-3 py-2">
                      <input
                        type="number"
                        value={linea.cuenta_id || ''}
                        onChange={(e) => updateLinea(index, 'cuenta_id', parseInt(e.target.value) || 0)}
                        className="input-field py-1 text-sm w-24"
                        placeholder="Ej: 12"
                        required
                      />
                    </td>
                    <td className="px-3 py-2">
                      <input
                        type="text"
                        value={linea.descripcion || ''}
                        onChange={(e) => updateLinea(index, 'descripcion', e.target.value)}
                        className="input-field py-1 text-sm"
                        placeholder="Detalle..."
                      />
                    </td>
                    <td className="px-3 py-2">
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={linea.debe || ''}
                        onChange={(e) => updateLinea(index, 'debe', parseFloat(e.target.value) || 0)}
                        className="input-field py-1 text-sm text-right text-red-600 w-28"
                        placeholder="0.00"
                      />
                    </td>
                    <td className="px-3 py-2">
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={linea.haber || ''}
                        onChange={(e) => updateLinea(index, 'haber', parseFloat(e.target.value) || 0)}
                        className="input-field py-1 text-sm text-right text-green-600 w-28"
                        placeholder="0.00"
                      />
                    </td>
                    <td className="px-3 py-2">
                      <button
                        type="button"
                        onClick={() => removeLinea(index)}
                        className="text-gray-400 hover:text-red-500 disabled:opacity-30"
                        disabled={lineas.length <= 2}
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot className="bg-gray-50">
                <tr>
                  <td colSpan={2} className="px-3 py-2 text-right text-sm font-semibold text-gray-900">
                    TOTALES
                  </td>
                  <td className="px-3 py-2 text-right text-sm font-bold text-red-600">
                    {totalDebe.toFixed(2)} €
                  </td>
                  <td className="px-3 py-2 text-right text-sm font-bold text-green-600">
                    {totalHaber.toFixed(2)} €
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        {/* Actions */}
        <div className="flex justify-end gap-3 border-t border-gray-200 pt-4">
          <button type="button" onClick={onCancel} className="btn-secondary">
            Cancelar
          </button>
          <button
            type="submit"
            className="btn-primary disabled:opacity-50"
            disabled={!cuadra || enviando || !descripcion}
          >
            {enviando ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Guardando...
              </>
            ) : (
              'Guardar Asiento'
            )}
          </button>
        </div>
      </form>
    </div>
  );
}
