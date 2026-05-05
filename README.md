# ContaVisión ERP

> **ERP modular para pymes y autónomos** con contabilidad de partida doble, facturación, conciliación bancaria por OCR y proyección de tesorería con simulaciones Monte Carlo.

**Proyecto de portafolio** — Diseñado para demostrar competencias full-stack con contexto financiero (fintech/ERP).

[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net)
[![React 18](https://img.shields.io/badge/React-18-61DAFB?logo=react)](https://react.dev)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.5-3178C6?logo=typescript)](https://typescriptlang.org)
[![MySQL 8](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql)](https://mysql.com)
[![Python 3.12](https://img.shields.io/badge/Python-3.12-3776AB?logo=python)](https://python.org)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker)](https://docker.com)

---

## 📸 Capturas

> *(Añadir screenshots aquí una vez desplegado)*

- Dashboard con KPIs financieros en tiempo real
- Libro diario con validación de partida doble
- Gráfico de bandas de confianza Monte Carlo (P10/P50/P90)
- Formulario de asiento contable con validación en tiempo real

---

## 🚀 Demo en vivo

**URL:** *(pendiente de despliegue en Railway/Render)*

---

## 🏗️ Arquitectura

```
┌─────────────────┐       HTTPS/JWT       ┌──────────────────────┐
│   React 18      │ ────────────────────► │  Slim PHP 8 (API)    │
│   TypeScript    │ ◄──────────────────── │  PDO + ACID          │
│   Chart.js      │                       │  JWT + RBAC          │
└─────────────────┘                       └────┬──────────┬──────┘
                                               │          │
                                     SQL       │          │ HTTP
                                     (ACID)    │          │ (cURL)
                                               ▼          ▼
                                        ┌─────────┐  ┌──────────────┐
                                        │ MySQL 8 │  │ FastAPI      │
                                        │ InnoDB  │  │ Python/numpy │
                                        │         │  │ Monte Carlo  │
                                        └─────────┘  └──────────────┘
```

### Estructura del monorepo

```
conta-vision-erp/
├── backend/               # API REST PHP 8 + Slim Framework
│   ├── src/
│   │   ├── Core/          # Database (ACID), Middlewares (JWT, RBAC)
│   │   ├── Modules/       # Auth, Accounting, Invoicing, Dashboard
│   │   └── Integrations/  # Cliente HTTP a microservicio Python
│   └── tests/             # PHPUnit: Partida doble, OCR
│
├── frontend/              # React 18 + TypeScript + Vite
│   └── src/
│       ├── hooks/         # useAuth, useApi (JWT + error handling)
│       ├── components/    # Sidebar, KpiCards, MonteCarloBandChart
│       └── routes/pages/  # Dashboard, Libro Diario, Tesorería
│
├── monte-carlo/           # Microservicio Python + FastAPI
│   └── core/
│       ├── simulation.py  # Motor Monte Carlo (numpy vectorizado)
│       └── models.py      # Pydantic schemas
│
├── docker-compose.yml     # Orquestación: MySQL + PHP + Python + React
├── database_schema.sql    # 14 tablas con InnoDB, FKs, índices
└── docs/                  # API docs, arquitectura, despliegue
```

---

## 🔧 Stack Tecnológico

| Capa | Tecnología | Justificación |
|------|-----------|---------------|
| **Backend** | PHP 8.2 + Slim | Control total sobre routing/middleware sin magia de framework. Demuestra comprensión de PSR-4, DI, middleware chain. |
| **Base de datos** | MySQL 8 + InnoDB | ACID es obligatorio en contabilidad. Partida doble requiere atomicidad: si falla una línea del asiento, ROLLBACK total. |
| **Frontend** | React 18 + TypeScript + Vite | TypeScript garantiza types en DTOs contables. Vite es el estándar 2025. Chart.js para visualización financiera. |
| **Microservicio** | Python + FastAPI + numpy | numpy vectorizado es 50-100x más rápido que PHP para cálculos numéricos. Separa responsabilidades. |
| **Infraestructura** | Docker Compose | Reproducibilidad total. Un `docker compose up` levanta todo el stack. |

---

## 📋 Módulos Implementados

### ✅ Completados

| Módulo | Descripción | Endpoint clave |
|--------|-------------|----------------|
| **Autenticación** | JWT + RBAC (admin, contable, consultor) | `POST /api/auth/login` |
| **Contabilidad** | Libro diario con partida doble ACID | `POST /api/asientos` |
| **Dashboard** | 8 KPIs financieros + gráficos Chart.js | `GET /api/dashboard/kpis` |
| **Proyección Monte Carlo** | Percentiles P10/P50/P90, prob. déficit | `POST /api/treasury/simulate` |

### 🚧 En desarrollo

| Módulo | Estado |
|--------|--------|
| Facturación (emitidas/recibidas, PDF) | Pendiente |
| Conciliación bancaria OCR (Tesseract) | Pendiente |
| Plan de cuentas (CRUD) | Pendiente |
| Libro Mayor / Balance | Pendiente |

---

## 🛠️ Instalación Local

### Prerrequisitos

- Docker + Docker Compose
- PHP 8.2+ (para desarrollo sin Docker)
- Node.js 20+ (para desarrollo frontend)
- Python 3.12+ (para microservicio)

### Con Docker (recomendado)

```bash
# 1. Clonar repositorio
git clone https://github.com/tu-usuario/conta-vision-erp.git
cd conta-vision-erp

# 2. Configurar variables de entorno
cp backend/.env.example backend/.env
# Editar backend/.env con tus credenciales

# 3. Levantar todo el stack
docker compose up -d

# 4. Inicializar la base de datos
docker compose exec mysql mysql -u contavision_user -p contavision_erp < database_schema.sql

# 5. Acceder
# Frontend: http://localhost:5173
# Backend API: http://localhost:8080
# Monte Carlo: http://localhost:8000/docs
```

### Desarrollo sin Docker

```bash
# Backend
cd backend
composer install
cp .env.example .env
# Editar .env
php -S localhost:8080 -t public

# Frontend
cd frontend
npm install
npm run dev

# Microservicio Monte Carlo
cd monte-carlo
pip install -r requirements.txt
python app.py
```

---

## 🧪 Testing

```bash
# Backend (PHPUnit)
cd backend
vendor/bin/phpunit tests/

# Ejemplo de test: Partida Doble
# ✅ Asiento simple cuadra (debe == haber)
# ✅ Asiento con IVA (3 líneas) cuadra
# ✅ Asiento no cuadra → lanza excepción
# ✅ Línea con debe y haber simultáneos → excepción
# ✅ Asiento sin líneas → excepción
```

---

## 📊 Ejemplo: Proyección Monte Carlo

```json
// POST /api/treasury/simulate
{
  "empresa_id": 1,
  "horizonte_meses": 12,
  "num_simulaciones": 10000
}

// Response
{
  "meses": [
    { "mes": 1, "p10": 45000, "p50": 55000, "p90": 65000, "prob_deficit": 0.03 },
    { "mes": 2, "p10": 40000, "p50": 60000, "p90": 78000, "prob_deficit": 0.05 },
    { "mes": 3, "p10": -5000, "p50": 52000, "p90": 82000, "prob_deficit": 0.12 }
  ],
  "global": {
    "prob_deficit_total": 0.08,
    "mes_critico": 3,
    "mejor_escenario": 120000,
    "peor_escenario": -15000
  },
  "duracion_ms": 287
}
```

---

## 🔐 Seguridad

- **JWT** con expiración configurable (default: 8h)
- **RBAC** a nivel de endpoint y de componente frontend
- **Prepared statements** PDO nativos (no emulados) → sin SQL injection
- **password_hash()** con bcrypt (cost 12)
- **CORS** configurado por entorno
- **Audit trail** completo: quién hizo qué, cuándo, desde qué IP

---

## 📖 API Documentation

Documentación completa de todos los endpoints en [`backend/API.md`](./backend/API.md).

---

## 🗺️ Roadmap

| Semana | Hito |
|--------|------|
| 1 | Auth + Contabilidad (partida doble ACID) + Plan de cuentas |
| 2 | Facturación (CRUD + PDF) + Terceros |
| 3 | Dashboard KPIs + Gráficos Chart.js |
| 4 | Conciliación bancaria + OCR Tesseract |
| 5 | Microservicio Monte Carlo + integración |
| 6 | Docker + CI/CD + Despliegue + Documentación |

---

## 👩‍💻 Sobre el Proyecto

Este proyecto fue desarrollado como **proyecto de portafolio profesional** por una desarrolladora full-stack con background en Finanzas y Contabilidad. Combina:

- **Dominio financiero**: Partida doble, ratios financieros, proyección de tesorería, conciliación bancaria
- **Ingeniería de software**: ACID, JWT, RBAC, microservicios, CI/CD, Docker, TypeScript
- **Análisis cuantitativo**: Simulaciones Monte Carlo con numpy, percentiles, probabilidades de déficit

### Decisiones técnicas clave

| Decisión | Razonamiento |
|----------|-------------|
| MySQL InnoDB sobre PostgreSQL | Estándar en pymes españolas. InnoDB soporta ACID completo. Más familiar para el público objetivo. |
| Slim sobre Laravel | Demuestra comprensión de routing, middleware, DI sin magia. Más fácil de auditar en code reviews. |
| Microservicio Python separado | PHP es ineficiente para cálculos numéricos intensivos. numpy vectorizado es 50-100x más rápido. |
| TypeScript en frontend | Types en DTOs contables previenen bugs costosos. `LineaAsiento.debe: number` vs string. |
| Chart.js sobre D3 | Suficiente para KPIs financieros. Curva de aprendizaje menor. Mejor mantenibilidad. |

---

## 📄 Licencia

MIT License — Ver [`LICENSE`](./LICENSE) para detalles.
