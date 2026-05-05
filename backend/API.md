# ContaVisión ERP — Documentación de la API REST

## Base URL

```
Desarrollo: http://localhost:8080
Producción: https://api.contavision.tu-dominio.com
```

## Autenticación

Todas las rutas requieren un token JWT en el header `Authorization`:

```
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
```

Obtención del token: `POST /api/auth/login`

---

## Endpoints por Módulo

### 🔐 AUTH

| Método | Ruta | Permisos | Descripción |
|--------|------|----------|-------------|
| POST | `/api/auth/login` | Público | Iniciar sesión |
| POST | `/api/auth/register` | `admin.manage_users` | Registrar usuario |
| GET | `/api/auth/me` | Auth | Datos del usuario actual |

---

### 📒 CONTABILIDAD

| Método | Ruta | Permisos | Descripción |
|--------|------|----------|-------------|
| GET | `/api/asientos` | `accounting.read` | Listar asientos (paginado + filtros) |
| GET | `/api/asientos/{id}` | `accounting.read` | Detalle asiento con líneas |
| POST | `/api/asientos` | `accounting.write` | Crear asiento (partida doble) |
| DELETE | `/api/asientos/{id}` | `accounting.write` | Anular asiento |
| GET | `/api/cuentas` | `accounting.read` | Plan de cuentas |
| POST | `/api/cuentas` | `accounting.write` | Crear cuenta contable |
| PATCH | `/api/cuentas/{id}` | `accounting.write` | Actualizar cuenta |
| GET | `/api/libro-mayor` | `accounting.read` | Libro mayor por cuenta |
| GET | `/api/balance` | `accounting.read` | Balance de situación |
| GET | `/api/cuenta-resultados` | `accounting.read` | Cuenta de resultados |

---

### 📄 FACTURACIÓN

| Método | Ruta | Permisos | Descripción |
|--------|------|----------|-------------|
| GET | `/api/facturas` | `invoices.read` | Listar facturas |
| GET | `/api/facturas/{id}` | `invoices.read` | Detalle factura con líneas |
| POST | `/api/facturas` | `invoices.write` | Crear factura |
| PATCH | `/api/facturas/{id}` | `invoices.write` | Actualizar estado |
| POST | `/api/facturas/{id}/pdf` | `invoices.write` | Generar PDF |
| GET | `/api/terceros` | `invoices.read` | Clientes y proveedores |
| POST | `/api/terceros` | `invoices.write` | Crear tercero |

---

### 🏦 CONCILIACIÓN BANCARIA

| Método | Ruta | Permisos | Descripción |
|--------|------|----------|-------------|
| POST | `/api/conciliacion/upload` | `reconciliation.write` | Subir extracto bancario |
| GET | `/api/conciliacion/pendientes` | `reconciliation.read` | Movimientos sin conciliar |
| POST | `/api/conciliacion/conciliar` | `reconciliation.write` | Conciliar movimiento con asiento |
| GET | `/api/conciliacion/extractos` | `reconciliation.read` | Historial de extractos |

---

### 📊 DASHBOARD

| Método | Ruta | Permisos | Descripción |
|--------|------|----------|-------------|
| GET | `/api/dashboard/kpis` | `dashboard.read` | KPIs financieros |
| GET | `/api/dashboard/cuentas-cobrar` | `dashboard.read` | Cuentas por cobrar |
| GET | `/api/dashboard/cuentas-pagar` | `dashboard.read` | Cuentas por pagar |
| GET | `/api/dashboard/liquidez` | `dashboard.read` | Ratios de liquidez |
| GET | `/api/dashboard/solvencia` | `dashboard.read` | Ratios de solvencia |

---

### 💰 PROYECCIÓN DE TESORERÍA (Monte Carlo)

| Método | Ruta | Permisos | Descripción |
|--------|------|----------|-------------|
| POST | `/api/treasury/simulate` | `treasury.write` | Ejecutar simulación |
| GET | `/api/treasury/projections` | `treasury.read` | Proyecciones guardadas |
| GET | `/api/treasury/health` | `treasury.read` | Estado microservicio Python |

---

## Ejemplos de Uso

### Crear Asiento Contable (Partida Doble)

```bash
curl -X POST http://localhost:8080/api/asientos \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "empresa_id": 1,
    "fecha": "2025-01-15",
    "descripcion": "Cobro factura F-2025-001 a Pérez SL",
    "tipo": "banco",
    "lineas": [
      {
        "cuenta_id": 12,
        "debe": 1210.00,
        "haber": 0,
        "descripcion": "Ingreso en banco Santander",
        "referencia": "F-2025-001"
      },
      {
        "cuenta_id": 5,
        "debe": 0,
        "haber": 1000.00,
        "descripcion": "Cobro a cliente Pérez SL"
      },
      {
        "cuenta_id": 20,
        "debe": 0,
        "haber": 210.00,
        "descripcion": "IVA 21% repercutido"
      }
    ]
  }'
```

### Ejecutar Simulación Monte Carlo

```bash
curl -X POST http://localhost:8080/api/treasury/simulate \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "empresa_id": 1,
    "horizonte_meses": 12,
    "num_simulaciones": 10000
  }'
```

---

## Códigos de Error

| Código | Significado |
|--------|-------------|
| 400 | Validación fallida (partida doble no cuadra, campos requeridos) |
| 401 | Token ausente, expirado o inválido |
| 403 | Permisos insuficientes |
| 404 | Recurso no encontrado |
| 409 | Conflicto (email duplicado, número factura repetido) |
| 500 | Error interno del servidor |
| 502 | Microservicio Python no disponible |
