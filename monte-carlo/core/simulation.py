import numpy as np
from typing import List, Optional, Dict, Any


def run_monte_carlo(
    saldo_inicial: float,
    medias: List[float],
    desviaciones: List[float],
    horizonte: int,
    num_simulaciones: int = 10000,
    estacionalidad: Optional[List[float]] = None
) -> Dict[str, Any]:
    """
    Ejecuta simulación Monte Carlo para proyección de tesorería.

    Returns:
        Dict con resultados por mes y resumen global.
    """
    if estacionalidad is None:
        estacionalidad = [1.0] * horizonte

    # Ajustar medias por estacionalidad
    medias_ajustadas = [m * e for m, e in zip(medias[:horizonte], estacionalidad)]

    # Matriz de simulaciones: filas = simulaciones, columnas = meses
    flujos = np.random.normal(
        loc=medias_ajustadas,
        scale=desviaciones[:horizonte],
        size=(num_simulaciones, horizonte)
    )

    # Calcular saldo acumulado para cada simulación
    saldo_acumulado = np.cumsum(flujos, axis=1) + saldo_inicial

    meses_resultado = []
    deficits_por_mes = []

    for mes in range(horizonte):
        saldo_mes = saldo_acumulado[:, mes]
        p10 = float(np.percentile(saldo_mes, 10))
        p50 = float(np.percentile(saldo_mes, 50))
        p90 = float(np.percentile(saldo_mes, 90))
        prob_deficit = float(np.mean(saldo_mes < 0))
        deficits_por_mes.append(prob_deficit)

        meses_resultado.append({
            "mes": mes + 1,
            "p10": round(p10, 2),
            "p50": round(p50, 2),
            "p90": round(p90, 2),
            "prob_deficit": round(prob_deficit, 4)
        })

    # Resumen global
    saldo_final = saldo_acumulado[:, -1]
    prob_deficit_total = float(np.mean(saldo_final < 0))
    mejor_escenario = float(np.percentile(saldo_final, 90))
    peor_escenario = float(np.percentile(saldo_final, 10))
    mes_critico = int(np.argmax(deficits_por_mes)) + 1

    return {
        "meses": meses_resultado,
        "global": {
            "prob_deficit_total": round(prob_deficit_total, 4),
            "mejor_escenario": round(mejor_escenario, 2),
            "peor_escenario": round(peor_escenario, 2),
            "mes_critico": mes_critico
        }
    }
