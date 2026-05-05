from pydantic import BaseModel, Field
from typing import Optional


class Estacionalidad(BaseModel):
    """Factor de ajuste mensual para ingresos y/o gastos."""
    mes: int = Field(..., ge=1, le=12, description="Número del mes (1-12)")
    factor_ingreso: float = Field(default=1.0, ge=0, description="Multiplicador de ingresos para este mes")
    factor_gasto: float = Field(default=1.0, ge=0, description="Multiplicador de gastos para este mes")


class SimulacionRequest(BaseModel):
    """Parámetros de entrada para la simulación Monte Carlo de tesorería."""
    saldo_inicial: float = Field(..., description="Saldo de tesorería actual en euros")
    ingresos_media: float = Field(..., gt=0, description="Media mensual de ingresos históricos")
    ingresos_desviacion: float = Field(..., ge=0, description="Desviación estándar mensual de ingresos")
    gastos_media: float = Field(..., gt=0, description="Media mensual de gastos históricos")
    gastos_desviacion: float = Field(..., ge=0, description="Desviación estándar mensual de gastos")
    horizonte_meses: int = Field(default=12, ge=1, le=36, description="Horizonte de proyección en meses")
    num_simulaciones: int = Field(default=10000, ge=100, le=100000, description="Número de iteraciones Monte Carlo")
    estacionalidad: list[Estacionalidad] = Field(default_factory=list, description="Factores estacionales por mes")


class MesResultado(BaseModel):
    """Resultados estadísticos para un mes específico."""
    mes: int
    p10: float
    p50: float
    p90: float
    prob_deficit: float


class GlobalResultado(BaseModel):
    """Resumen global de la simulación."""
    prob_deficit_total: float
    mes_critico: int
    mejor_escenario: float
    peor_escenario: float


class SimulacionResponse(BaseModel):
    """Respuesta completa de la simulación."""
    meses: list[MesResultado]
    global_stats: GlobalResultado
    num_simulaciones: int
    duracion_ms: float
