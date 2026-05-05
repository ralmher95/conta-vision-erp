# ContaVisión ERP — Plan de Arquitectura

## Visión General

ERP modular para pymes/autónomos con contabilidad de partida doble, facturación, conciliación bancaria automática vía OCR, dashboard de KPIs financieros y proyección de tesorería con simulaciones de Monte Carlo.

---

## Estructura de Directorios (Monorepo)

```
conta-vision-erp/
├── docker-compose.yml
├── .github/
│   └── workflows/
│       ├── ci-backend.yml
│       ├── ci-frontend.yml
│       └── deploy.yml
│
├── backend/                    # PHP 8.x - API REST (Slim Framework)
│   ├── composer.json
│   ├── public/
│   │   └── index.php          # Entry point + router Slim
│   ├── src/
│   │   ├── Core/
│   │   │   ├── Database.php   # PDO con transacciones ACID
│   │   │   ├── Router.php
│   │   │   ├── Middleware/
│   │   │   │   ├── AuthMiddleware.php
│   │   │   │   └── RoleMiddleware.php
│   │   │   └── Response.php
│   │   ├── Modules/
│   │   │   ├── Auth/
│   │   │   │   ├── Controller/AuthController.php
│   │   │   │   └── Service/JwtService.php
│   │   │   ├── Accounting/
│   │   │   │   ├── Controller/AccountController.php
│   │   │   │   ├── Controller/JournalEntryController.php
│   │   │   │   ├── Model/Account.php
│   │   │   │   ├── Model/JournalEntry.php
│   │   │   │   └── Service/DoubleEntryService.php
│   │   │   ├── Invoicing/
│   │   │   │   ├── Controller/InvoiceController.php
│   │   │   │   ├── Model/Invoice.php
│   │   │   │   └── Service/InvoicePdfService.php
│   │   │   ├── Reconciliation/
│   │   │   │   ├── Controller/ReconciliationController.php
│   │   │   │   └── Service/OcrService.php
│   │   │   └── Dashboard/
│   │   │       ├── Controller/DashboardController.php
│   │   │       └── Service/KpiCalculator.php
│   │   └── Integrations/
│   │       └── MonteCarloClient.php   # HTTP client al microservicio Python
│   ├── tests/
│   │   ├── Accounting/DoubleEntryTest.php
│   │   └── Reconciliation/OcrTest.php
│   └── .env.example
│
├── monte-carlo/               # Python - Microservicio de simulaciones
│   ├── requirements.txt
│   ├── app.py                 # FastAPI entry point
│   ├── core/
│   │   ├── simulation.py      # Motor Monte Carlo
│   │   └── models.py          # Pydantic schemas
│   └── tests/
│       └── test_simulation.py
│
├── frontend/                  # React + TypeScript + Vite
│   ├── package.json
│   ├── tsconfig.json
│   ├── vite.config.ts
│   ├── tailwind.config.js
│   ├── src/
│   │   ├── main.tsx
│   │   ├── App.tsx
│   │   ├── routes/
│   │   │   ├── index.tsx              # React Router v6
│   │   │   ├── ProtectedRoute.tsx     # Wrapper con guardias de rol
│   │   │   └── pages/
│   │   │       ├── auth/LoginPage.tsx
│   │   │       ├── dashboard/DashboardPage.tsx
│   │   │       ├── accounting/JournalPage.tsx
│   │   │       ├── accounting/ChartOfAccountsPage.tsx
│   │   │       ├── invoicing/InvoicesPage.tsx
│   │   │       ├── reconciliation/ReconciliationPage.tsx
│   │   │       └── treasury/TreasuryProjectionPage.tsx
│   │   ├── components/
│   │   │   ├── ui/                    # Botones, inputs, modales, tablas
│   │   │   ├── layout/
│   │   │   │   ├── Sidebar.tsx
│   │   │   │   └── TopBar.tsx
│   │   │   ├── charts/
│   │   │   │   ├── CashFlowChart.tsx
│   │   │   │   ├── KpiCards.tsx
│   │   │   │   └── MonteCarloBandChart.tsx
│   │   │   └── accounting/
│   │   │       ├── JournalEntryForm.tsx
│   │   │       └── AccountSelector.tsx
│   │   ├── hooks/
│   │   │   ├── useApi.ts              # Custom hook con JWT + manejo errores
│   │   │   ├── useAuth.ts
│   │   │   └── usePermissions.ts
│   │   ├── services/
│   │   │   ├── api.ts                 # Axios instance con interceptors
│   │   │   ├── auth.service.ts
│   │   │   └── accounting.service.ts
│   │   ├── types/
│   │   │   ├── accounting.d.ts
│   │   │   ├── invoice.d.ts
│   │   │   └── treasury.d.ts
│   │   └── utils/
│   │       ├── formatters.ts          # Formato moneda, fechas
│   │       └── validators.ts          # Validación partida doble
│   └── index.html
│
└── docs/
    ├── ARCHITECTURE.md
    ├── API.md
    └── DEPLOYMENT.md
```

---

## Diagrama de Componentes

```
┌─────────────────────────────────────────────────────────┐
│                    BROWSER (Cliente)                     │
│   React 18 + TypeScript + Vite + Tailwind + Chart.js    │
│   Puerto: 5173 (dev) / Nginestático (prod)              │
└─────────────────────┬───────────────────────────────────┘
                      │ HTTPS (JWT Bearer)
                      ▼
┌─────────────────────────────────────────────────────────┐
│              BACKEND PHP 8.x (Slim Framework)            │
│   Puerto: 8080                                           │
│                                                          │
│   ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌────────┐ │
│   │   Auth   │  │Accounting│  │ Invoices │  │ OCR    │ │
│   │ JWT+RBAC │  │ACID+PDO  │  │ PDF gen  │  │Tesseract│ │
│   └──────────┘  └──────────┘  └──────────┘  └────────┘ │
│                                                          │
│   ┌──────────────────────────────────────────────────┐  │
│   │  Integraciones: MonteCarloClient (HTTP a Python) │  │
│   └──────────────────────────────────────────────────┘  │
└──────────┬───────────────────────────┬──────────────────┘
           │ SQL (PDO)                 │ HTTP POST
           ▼                           ▼
┌──────────────────────┐   ┌────────────────────────────┐
│  MySQL 8 (InnoDB)    │   │  Microservicio Python      │
│  Puerto: 3306        │   │  FastAPI - Puerto: 8000    │
│                      │   │                            │
│  • Usuarios          │   │  POST /simulate-cashflow   │
│  • Empresas          │   │  → numpy.random.normal()   │
│  • Plan de cuentas   │   │  → 10.000 simulaciones     │
│  • Asientos + líneas │   │  → percentiles P10/P50/P90 │
│  • Facturas          │   │  → probabilidad de déficit │
│  • Extractos OCR     │   │  → retorno en 200-500ms    │
│  • Conciliaciones    │   │                            │
└──────────────────────┘   └────────────────────────────┘
```

---

## Justificación de Decisiones Técnicas

| Decisión | Por qué |
|----------|---------|
| **Slim sobre Laravel** | Laravel es overkill para un portafolio. Slim demuestra que entiendes routing, DI y middlewares sin magia. Más fácil de auditar en una code review. |
| **MySQL InnoDB ACID** | La contabilidad exige atomicidad. Si un asiento tiene 2 líneas y falla la segunda, el rollback garantiza que no queda "medio asiento". PostgreSQL valdría igual, pero MySQL es el estándar en pymes. |
| **Microservicio Python separado** | PHP es pésimo para cálculos numéricos intensivos. numpy vectorizado es 50-100x más rápido. Separar responsabilidades demuestra arquitectura de sistemas. |
| **Tesseract en PHP** | El upload del PDF va al backend PHP (ya maneja ficheros). Llamar a `exec('tesseract ...')` es simple y evita otra capa de red. |
| **React + Vite + TypeScript** | TypeScript demuestra madurez (types en DTOs contables). Vite es el estándar 2025. Chart.js es suficiente para KPIs financieros sin la complejidad de D3. |
| **FastAPI sobre Flask** | FastAPI ofrece validación automática con Pydantic, tipado nativo, y rendimiento asíncrono superior para el microservicio de simulaciones. |
