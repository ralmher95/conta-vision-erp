-- ==========================================
-- CONTA-VISIÓN ERP
-- Esquema de base de datos MySQL 8
-- Motor: InnoDB | Collation: utf8mb4_unicode_ci
-- Garantía ACID en operaciones contables
-- ==========================================

DROP DATABASE IF EXISTS contavision_erp;
CREATE DATABASE contavision_erp
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE contavision_erp;

-- ==========================================
-- 1. USUARIOS Y ROLES
-- ==========================================

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE,
    slug VARCHAR(30) NOT NULL UNIQUE,
    descripcion VARCHAR(100),
    permisos JSON COMMENT 'Array de permisos: ["accounting.write", "invoices.read"]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol_id INT NOT NULL,
    empresa_id INT, -- NULL si aún no pertenece a una empresa
    avatar_url VARCHAR(255),
    activo TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_rol
        FOREIGN KEY (rol_id) REFERENCES roles(id)
        ON DELETE RESTRICT,
    INDEX idx_user_email (email),
    INDEX idx_user_activo (activo)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- 2. EMPRESAS
-- ==========================================

CREATE TABLE empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    cif VARCHAR(15) NOT NULL UNIQUE,
    direccion VARCHAR(255),
    telefono VARCHAR(20),
    email VARCHAR(150),
    sector VARCHAR(50),
    regimen_iva ENUM('general', 'reducido', 'exento') NOT NULL DEFAULT 'general',
    moneda_base CHAR(3) NOT NULL DEFAULT 'EUR',
    ejercicio_fiscal_inicio DATE NOT NULL COMMENT 'Fecha inicio ejercicio fiscal actual',
    ejercicio_fiscal_fin DATE NOT NULL COMMENT 'Fecha fin ejercicio fiscal actual',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_empresa_cif (cif),
    INDEX idx_empresa_ejercicio (ejercicio_fiscal_inicio, ejercicio_fiscal_fin)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

ALTER TABLE users
    ADD CONSTRAINT fk_user_empresa
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
    ON DELETE SET NULL;

CREATE TABLE empresa_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    user_id INT NOT NULL,
    rol_id INT NOT NULL, -- Rol específico dentro de esta empresa
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_eu_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_eu_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_eu_rol
        FOREIGN KEY (rol_id) REFERENCES roles(id)
        ON DELETE RESTRICT,
    UNIQUE KEY uk_empresa_user (empresa_id, user_id),
    INDEX idx_eu_empresa (empresa_id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- 3. PLAN DE CUENTAS CONTABLE
-- ==========================================

CREATE TABLE plan_cuentas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    codigo VARCHAR(10) NOT NULL COMMENT 'Ej: 4300000000 (Clientes)',
    descripcion VARCHAR(200) NOT NULL,
    tipo ENUM('activo', 'pasivo', 'patrimonio_neto', 'ingreso', 'gasto') NOT NULL,
    nivel INT NOT NULL COMMENT 'Profundidad: 1=grupo, 2=subgrupo, 3=cuenta',
    padre_id INT NULL COMMENT 'Referencia a cuenta padre para estructura jerárquica',
    saldo_actual DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pc_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pc_padre
        FOREIGN KEY (padre_id) REFERENCES plan_cuentas(id)
        ON DELETE SET NULL,
    UNIQUE KEY uk_empresa_codigo (empresa_id, codigo),
    INDEX idx_pc_tipo (empresa_id, tipo),
    INDEX idx_pc_padre (empresa_id, padre_id),
    INDEX idx_pc_nivel (empresa_id, nivel)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- 4. ASIENTOS CONTABLES (CABECERA)
-- ==========================================

CREATE TABLE asientos_contables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    numero INT NOT NULL COMMENT 'Número secuencial por empresa y ejercicio',
    fecha DATE NOT NULL,
    descripcion TEXT NOT NULL,
    tipo ENUM('ordinario', 'apertura', 'cierre', 'regularizacion', 'nomina', 'banco') NOT NULL DEFAULT 'ordinario',
    ejercicio_fiscal INT NOT NULL COMMENT 'Ejemplo: 2025',
    total_debe DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de todos los debe',
    total_haber DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de todos los haber',
    conciliado TINYINT(1) NOT NULL DEFAULT 0,
    factura_id INT NULL COMMENT 'Si proviene de una factura, referencia aquí',
    creado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_asiento_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_asiento_creador
        FOREIGN KEY (creado_por) REFERENCES users(id)
        ON DELETE RESTRICT,
    UNIQUE KEY uk_empresa_ejercicio_numero (empresa_id, ejercicio_fiscal, numero),
    INDEX idx_asiento_fecha (empresa_id, fecha),
    INDEX idx_asiento_ejercicio (empresa_id, ejercicio_fiscal),
    INDEX idx_asiento_tipo (empresa_id, tipo)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- 5. LÍNEAS DE ASIENTO (PARTIDA DOBLE)
-- ==========================================

CREATE TABLE lineas_asiento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asiento_id INT NOT NULL,
    cuenta_id INT NOT NULL,
    debe DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    haber DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    descripcion VARCHAR(255) COMMENT 'Detalle de esta línea específica',
    referencia VARCHAR(50) COMMENT 'Nº factura, albarán, etc.',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_la_asiento
        FOREIGN KEY (asiento_id) REFERENCES asientos_contables(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_la_cuenta
        FOREIGN KEY (cuenta_id) REFERENCES plan_cuentas(id)
        ON DELETE RESTRICT,
    CONSTRAINT chk_debe_haber_exclusivo
        CHECK (
            (debe > 0 AND haber = 0) OR
            (haber > 0 AND debe = 0) OR
            (debe = 0 AND haber = 0)
        ),
    CHECK (debe >= 0 AND haber >= 0),
    INDEX idx_la_asiento (asiento_id),
    INDEX idx_la_cuenta (cuenta_id),
    INDEX idx_la_asiento_cuenta (asiento_id, cuenta_id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- 6. CLIENTES Y PROVEEDORES
-- ==========================================

CREATE TABLE terceros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tipo ENUM('cliente', 'proveedor') NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    cif VARCHAR(15),
    direccion VARCHAR(255),
    ciudad VARCHAR(100),
    provincia VARCHAR(50),
    codigo_postal VARCHAR(10),
    pais CHAR(2) DEFAULT 'ES',
    telefono VARCHAR(20),
    email VARCHAR(150),
    cuenta_contable_id INT COMMENT 'Cuenta del plan de cuentas asociada (430xxx o 400xxx)',
    saldo_pendiente DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tercero_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_tercero_cuenta
        FOREIGN KEY (cuenta_contable_id) REFERENCES plan_cuentas(id)
        ON DELETE SET NULL,
    INDEX idx_tercero_empresa_tipo (empresa_id, tipo),
    INDEX idx_tercero_nombre (empresa_id, nombre),
    INDEX idx_tercero_cif (cif)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- 7. FACTURAS (EMITIDAS Y RECIBIDAS)
-- ==========================================

CREATE TABLE facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tercero_id INT NOT NULL,
    tipo ENUM('emitida', 'recibida') NOT NULL,
    numero VARCHAR(30) NOT NULL COMMENT 'Número de factura visible (F-2025-001)',
    fecha_emision DATE NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    fecha_pago DATE NULL COMMENT 'NULL si aún no está pagada',
    estado ENUM('borrador', 'emitida', 'pagada', 'vencida', 'anulada') NOT NULL DEFAULT 'borrador',
    base_imponible DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tipo_iva DECIMAL(5,2) NOT NULL DEFAULT 21.00,
    cuota_iva DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    retencion DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'IRPF u otra retención',
    cuota_retencion DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Base + IVA - Retención',
    asiento_id INT NULL COMMENT 'Asiento contable generado automáticamente',
    pdf_path VARCHAR(255) COMMENT 'Ruta al PDF generado',
    creado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_factura_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_factura_tercero
        FOREIGN KEY (tercero_id) REFERENCES terceros(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_factura_creador
        FOREIGN KEY (creado_por) REFERENCES users(id)
        ON DELETE RESTRICT,
    UNIQUE KEY uk_empresa_numero (empresa_id, numero),
    INDEX idx_factura_estado (empresa_id, estado),
    INDEX idx_factura_vencimiento (empresa_id, fecha_vencimiento),
    INDEX idx_factura_tipo (empresa_id, tipo),
    INDEX idx_factura_tercero (tercero_id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- FK de asientos_contables a facturas (después de crear facturas)
ALTER TABLE asientos_contables
    ADD CONSTRAINT fk_asiento_factura
    FOREIGN KEY (factura_id) REFERENCES facturas(id)
    ON DELETE SET NULL;

-- ==========================================
-- 8. LÍNEAS DE FACTURA
-- ==========================================

CREATE TABLE lineas_factura (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    precio_unitario DECIMAL(15,2) NOT NULL,
    tipo_iva DECIMAL(5,2) NOT NULL DEFAULT 21.00,
    subtotal DECIMAL(15,2) NOT NULL COMMENT 'cantidad * precio_unitario',
    cuota_iva DECIMAL(15,2) NOT NULL COMMENT 'subtotal * tipo_iva / 100',
    total DECIMAL(15,2) NOT NULL COMMENT 'subtotal + cuota_iva',
    cuenta_ingreso_id INT COMMENT 'Cuenta de ingreso (700xxx)',
    cuenta_gasto_id INT COMMENT 'Cuenta de gasto (600xxx) si es factura recibida',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lf_factura
        FOREIGN KEY (factura_id) REFERENCES facturas(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_lf_cuenta_ingreso
        FOREIGN KEY (cuenta_ingreso_id) REFERENCES plan_cuentas(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_lf_cuenta_gasto
        FOREIGN KEY (cuenta_gasto_id) REFERENCES plan_cuentas(id)
        ON DELETE SET NULL,
    INDEX idx_lf_factura (factura_id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- 9. EXTRACTOS BANCARIOS (OCR)
-- ==========================================

CREATE TABLE cuentas_bancarias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    iban VARCHAR(34) NOT NULL,
    banco VARCHAR(100),
    titular VARCHAR(150),
    saldo_inicial DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    cuenta_contable_id INT COMMENT 'Cuenta 572xxx (Bancos)',
    activa TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cb_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cb_cuenta
        FOREIGN KEY (cuenta_contable_id) REFERENCES plan_cuentas(id)
        ON DELETE SET NULL,
    UNIQUE KEY uk_empresa_iban (empresa_id, iban)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE extractos_bancarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cuenta_bancaria_id INT NOT NULL,
    archivo_original VARCHAR(255) NOT NULL COMMENT 'Ruta al PDF/imagen subido',
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_extracto_inicio DATE NOT NULL,
    fecha_extracto_fin DATE NOT NULL,
    estado ENUM('pendiente_ocr', 'procesado', 'error', 'conciliando', 'conciliado') NOT NULL DEFAULT 'pendiente_ocr',
    texto_ocr TEXT COMMENT 'Texto crudo extraído por Tesseract',
    errores_ocr TEXT COMMENT 'Errores de parseo si los hubo',
    procesado_por INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_eb_cuenta
        FOREIGN KEY (cuenta_bancaria_id) REFERENCES cuentas_bancarias(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_eb_procesador
        FOREIGN KEY (procesado_por) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_eb_cuenta_estado (cuenta_bancaria_id, estado),
    INDEX idx_eb_fecha (fecha_extracto_inicio, fecha_extracto_fin)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- 10. MOVIMIENTOS DE EXTRACTO (parseados del OCR)
-- ==========================================

CREATE TABLE movimientos_bancarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    extracto_id INT NOT NULL,
    fecha_operacion DATE NOT NULL,
    descripcion VARCHAR(255) NOT NULL COMMENT 'Concepto tal cual aparece en el extracto',
    importe DECIMAL(15,2) NOT NULL COMMENT 'Positivo=ingreso, Negativo=pago',
    referencia_bancaria VARCHAR(50) COMMENT 'ID único del banco (CRC, ID operación)',
    conciliado TINYINT(1) NOT NULL DEFAULT 0,
    asiento_sugerido_id INT NULL COMMENT 'Asiento que el sistema sugiere para conciliar',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mb_extracto
        FOREIGN KEY (extracto_id) REFERENCES extractos_bancarios(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_mb_asiento
        FOREIGN KEY (asiento_sugerido_id) REFERENCES asientos_contables(id)
        ON DELETE SET NULL,
    INDEX idx_mb_extracto (extracto_id),
    INDEX idx_mb_fecha (extracto_id, fecha_operacion),
    INDEX idx_mb_conciliado (extracto_id, conciliado),
    INDEX idx_mb_referencia (referencia_bancaria)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- 11. CONCILIACIONES
-- ==========================================

CREATE TABLE conciliaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    movimiento_bancario_id INT NOT NULL,
    asiento_id INT NOT NULL COMMENT 'Asiento contable conciliado',
    conciliado_por INT NOT NULL,
    fecha_conciliacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notas VARCHAR(255),
    CONSTRAINT fk_conc_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conc_movimiento
        FOREIGN KEY (movimiento_bancario_id) REFERENCES movimientos_bancarios(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_conc_asiento
        FOREIGN KEY (asiento_id) REFERENCES asientos_contables(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_conc_usuario
        FOREIGN KEY (conciliado_por) REFERENCES users(id)
        ON DELETE RESTRICT,
    UNIQUE KEY uk_movimiento_asiento (movimiento_bancario_id, asiento_id),
    INDEX idx_conc_empresa (empresa_id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- 12. SIMULACIONES MONTE CARLO
-- ==========================================

CREATE TABLE configuraciones_simulacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL COMMENT 'Ej: Proyección Q1 2026',
    horizonte_meses INT NOT NULL DEFAULT 12 COMMENT '3, 6 o 12 meses',
    saldo_inicial DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    ingresos_media_mensual DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    ingresos_desviacion DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Volatilidad de ingresos',
    gastos_media_mensual DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    gastos_desviacion DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Volatilidad de gastos',
    num_simulaciones INT NOT NULL DEFAULT 10000 COMMENT 'Número de iteraciones Monte Carlo',
    estacionalidad JSON COMMENT 'Ajustes mensuales: [{"mes": 1, "factor_ingreso": 0.8}, ...]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cs_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,
    INDEX idx_cs_empresa (empresa_id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

CREATE TABLE resultados_simulacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    configuracion_id INT NOT NULL,
    fecha_ejecucion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    duracion_ms INT COMMENT 'Tiempo de ejecución en milisegundos',
    resultados JSON NOT NULL COMMENT '{
        "mes_1": {"p10": -500, "p50": 2000, "p90": 5000, "prob_deficit": 0.12},
        "mes_2": {...},
        ...
        "global": {"prob_deficit_total": 0.08, "mes_critico": 3}
    }',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rs_config
        FOREIGN KEY (configuracion_id) REFERENCES configuraciones_simulacion(id)
        ON DELETE CASCADE,
    INDEX idx_rs_config (configuracion_id),
    INDEX idx_rs_fecha (fecha_ejecucion)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- 13. LOG DE AUDITORÍA (AUDIT TRAIL)
-- ==========================================

CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    user_id INT,
    tabla_afectada VARCHAR(50) NOT NULL,
    registro_id INT NOT NULL,
    accion ENUM('create', 'update', 'delete', 'conciliar', 'anular') NOT NULL,
    valores_anteriores JSON COMMENT 'Snapshot antes del cambio',
    valores_nuevos JSON COMMENT 'Snapshot después del cambio',
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_audit_tabla (empresa_id, tabla_afectada, registro_id),
    INDEX idx_audit_fecha (empresa_id, created_at),
    INDEX idx_audit_user (user_id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

-- ==========================================
-- 14. SEMILLAS DE DATOS INICIALES
-- ==========================================

-- Roles del sistema
INSERT INTO roles (nombre, slug, descripcion, permisos) VALUES
    ('Administrador', 'admin', 'Acceso total al sistema',
     '["accounting.read", "accounting.write", "invoices.read", "invoices.write", "reconciliation.read", "reconciliation.write", "dashboard.read", "treasury.read", "treasury.write", "admin.manage_users"]'),
    ('Contable', 'contable', 'Gestión contable y facturación',
     '["accounting.read", "accounting.write", "invoices.read", "invoices.write", "reconciliation.read", "reconciliation.write", "dashboard.read"]'),
    ('Consultor', 'consultor', 'Solo lectura y proyecciones',
     '["accounting.read", "invoices.read", "reconciliation.read", "dashboard.read", "treasury.read"]');

-- Usuario admin por defecto (password: admin123)
-- Hash generado con: password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12])
-- Para regenerar: php -r "echo password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);"
INSERT INTO users (nombre_completo, email, password_hash, rol_id) VALUES
    ('Admin Sistema', 'admin@contavision.local', '$2y$12$LJ3m4ys3GZ8eVQjGqQ8OjO3v8Pq6r9CxKU1b7l2fJ4uU3zPbz9Hsy', 1);

-- ==========================================
-- VERIFICACIÓN
-- ==========================================

SELECT
    TABLE_NAME,
    TABLE_ROWS,
    ENGINE,
    TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'contavision_erp'
ORDER BY TABLE_NAME;
