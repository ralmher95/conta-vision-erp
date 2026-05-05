# 🗺️ ContaVisión ERP - Roadmap de Desarrollo (6 Semanas)

## Semana 1: Cimientos y Autenticación
| Día | Tarea | Estado |
|-----|-------|--------|
| 1-2 | Setup monorepo, Git, estructura de directorios | ✅ |
| 3-4 | Schema MySQL completo, migraciones iniciales | ✅ |
| 5 | Conexión DB (PDO), patrón Singleton | ✅ |
| 6-7 | Autenticación JWT, login/registro, refresh tokens | ✅ |

## Semana 2: Contabilidad Core
| Día | Tarea | Estado |
|-----|-------|--------|
| 1-2 | CRUD Plan de Cuentas (PGC 2007) | ⬜ |
| 3-4 | Servicio Doble Partida (validación debe=haber) | ✅ |
| 5 | CRUD Asientos Contables | ⬜ |
| 6-7 | Libro Diario y Mayor (endpoints + frontend) | ⬜ |

## Semana 3: Facturación y OCR
| Día | Tarea | Estado |
|-----|-------|--------|
| 1-2 | CRUD Facturas (emitidas/recibidas) | ⬜ |
| 3-4 | Integración Tesseract OCR (extractos bancarios) | ✅ |
| 5 | Parser de transacciones bancarias (regex) | ✅ |
| 6-7 | Conciliación automática (sugerencias por importe/fecha) | ⬜ |

## Semana 4: Dashboard y Reportes
| Día | Tarea | Estado |
|-----|-------|--------|
| 1-2 | Cálculo de KPIs financieros (liquidez, endeudamiento) | ⬜ |
| 3-4 | Gráficos: Balance, Cuenta de Resultados, Cash Flow | ⬜ |
| 5 | Export PDF (reportes) | ⬜ |
| 6-7 | Dashboard interactivo con filtros por fecha | ⬜ |

## Semana 5: Monte Carlo y Predicción
| Día | Tarea | Estado |
|-----|-------|--------|
| 1-2 | API FastAPI + endpoint de simulación | ✅ |
| 3-4 | Simulación numpy vectorizada (P10/P50/P90) | ✅ |
| 5 | Integración PHP → Python (cURL client) | ✅ |
| 6-7 | Frontend: gráfico de bandas de confianza | ⬜ |

## Semana 6: DevOps y Lanzamiento
| Día | Tarea | Estado |
|-----|-------|--------|
| 1-2 | Docker Compose (todos los servicios) | ✅ |
| 3 | GitHub Actions CI/CD | ⬜ |
| 4 | Deploy a Render/Railway | ⬜ |
| 5 | Tests end-to-end (Playwright) | ⬜ |
| 6-7 | Documentación final, demo video, README | ⬜ |

---

## Métricas de Éxito
- [ ] 0 errores de balance (debe == haber siempre)
- [ ] OCR con >80% de precisión en extractos estándar
- [ ] Simulación Monte Carlo < 500ms para 10k ejecuciones
- [ ] Deploy automatizado en CI/CD
- [ ] Lighthouse score > 90 (Performance, Accessibility, SEO)

## Deuda Técnica Conocida
1. Migraciones versionadas (actualmente schema.sql único)
2. Tests unitarios pendientes en DoubleEntryService
3. Rate limiting en endpoints de OCR
4. Internacionalización (i18n) del frontend
