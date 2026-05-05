from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from typing import List, Optional
from core.simulation import run_monte_carlo
import uvicorn

app = FastAPI(
    title="ContaVisión Monte Carlo Service",
    description="Microservicio para simulaciones de tesorería con Monte Carlo",
    version="1.0.0"
)


class SimulationRequest(BaseModel):
    saldo_inicial: float = Field(..., description="Saldo inicial de tesorería")
    medias: List[float] = Field(..., description="Medias mensuales de ingresos/gastos")
    desviaciones: List[float] = Field(..., description="Desviaciones estándar mensuales")
    horizonte: int = Field(..., ge=1, le=60, description="Horizonte temporal en meses")
    num_simulaciones: int = Field(10000, ge=1000, le=100000, description="Número de simulaciones")
    estacionalidad: Optional[List[float]] = Field(None, description="Factores de estacionalidad por mes")


class MesResultado(BaseModel):
    mes: int
    p10: float
    p50: float
    p90: float
    prob_deficit: float


class ProyeccionGlobal(BaseModel):
    prob_deficit_total: float
    mejor_escenario: float
    peor_escenario: float
    mes_critico: int


class SimulationResponse(BaseModel):
    meses: List[MesResultado]
    global_: ProyeccionGlobal = Field(..., alias="global")


@app.get("/health")
async def health_check():
    return {"status": "ok", "service": "monte-carlo", "version": "1.0.0"}


@app.post("/simulate-cashflow", response_model=SimulationResponse)
async def simulate_cashflow(request: SimulationRequest):
    try:
        resultados = run_monte_carlo(
            saldo_inicial=request.saldo_inicial,
            medias=request.medias,
            desviaciones=request.desviaciones,
            horizonte=request.horizonte,
            num_simulaciones=request.num_simulaciones,
            estacionalidad=request.estacionalidad
        )
        return resultados
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Error en simulación: {str(e)}")


if __name__ == "__main__":
    uvicorn.run("app:app", host="0.0.0.0", port=8000, reload=True)
