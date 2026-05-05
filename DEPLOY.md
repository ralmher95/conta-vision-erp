# 🚀 Guía de Despliegue - ContaVisión ERP

## Opción 1: Render (Recomendada)

### Base de Datos (MySQL en Render)
1. Crear **MySQL Database** en Render
2. Copiar `INTERNAL_DATABASE_URL`
3. Ejecutar `database_schema.sql` desde la consola de Render

### Backend PHP
1. Nuevo **Web Service** → Conectar repo GitHub
2. Directorio raíz: `/`
3. Build Command:
   ```bash
   cd backend && composer install --no-dev --optimize-autoloader
   ```
4. Start Command:
   ```bash
   apache2-foreground
   ```
5. Variables de entorno:
   ```
   DB_HOST=your-mysql.railway.internal
   DB_DATABASE=contavision
   DB_USERNAME=root
   DB_PASSWORD=your_password
   JWT_SECRET=genera_con_openssl_rand_hex_32
   MONTE_CARLO_URL=https://your-monte-carlo.onrender.com
   ```

### Microservice Monte Carlo (Python)
1. Nuevo **Web Service** → Conectar repo GitHub
2. Directorio raíz: `/monte-carlo`
3. Build Command:
   ```bash
   pip install -r requirements.txt
   ```
4. Start Command:
   ```bash
   uvicorn main:app --host 0.0.0.0 --port $PORT --workers 2
   ```
5. Variables de entorno:
   ```
   MAX_SIMULATIONS=10000
   PYTHONUNBUFFERED=1
   ```

### Frontend (Vercel)
1. Nuevo proyecto en **Vercel** → Importar repo GitHub
2. Directorio raíz: `/frontend`
3. Framework Preset: `Vite`
4. Build Command: `npm run build`
5. Output Directory: `dist`
6. Variables de entorno:
   ```
   VITE_API_URL=https://your-backend.onrender.com
   ```

---

## Opción 2: Railway

### Todo en un proyecto Railway

1. Crear nuevo proyecto en Railway
2. Añadir servicios:
   - **MySQL Database** (desde templates)
   - **Backend PHP** (Dockerfile en raíz)
   - **Monte Carlo** (Dockerfile en `/monte-carlo`)
   - **Frontend** (Dockerfile.frontend en raíz)

3. Variables de entorno compartidas:
   ```env
   DATABASE_URL=mysql://root:password@mysql.railway.internal:3306/railway
   JWT_SECRET=your_secret_here
   MONTE_CARLO_URL=http://monte-carlo:8000
   ```

4. Railway auto-detecta `docker-compose.yml` para desarrollo local.

---

## Opción 3: Docker en VPS

### Servidor mínimo recomendado
- 2 vCPU, 4GB RAM, 50GB SSD
- Ubuntu 22.04 LTS

### Comandos

```bash
# 1. Instalar Docker + Compose
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# 2. Clonar repo
git clone https://github.com/youruser/contavision-erp.git
cd contavision-erp

# 3. Configurar variables
cp .env.example .env
nano .env

# 4. Levantar servicios
docker compose up -d --build

# 5. Inicializar BD
docker exec contavision-mysql mysql -u root -p contavision < database_schema.sql

# 6. Verificar
docker compose ps
curl http://localhost:3000  # Frontend
curl http://localhost:80/api/health  # Backend
curl http://localhost:8000/health  # Monte Carlo
```

---

## Preproducción Checklist

- [ ] Base de datos migrada (`database_schema.sql` ejecutado)
- [ ] JWT_SECRET generado (`openssl rand -hex 32`)
- [ ] CORS configurado (origen del frontend)
- [ ] SSL/TLS activo (Let's Encrypt o Render automático)
- [ ] Backups automáticos de BD configurados
- [ ] Health checks verificando `/api/health` y `/health`
- [ ] Logs centralizados (Render logs, Railway logs, o ELK)

## Monitoreo

### Endpoints de salud
- **Backend**: `GET /api/health` → `{"status": "ok", "db": "connected"}`
- **Monte Carlo**: `GET /health` → `{"status": "ok", "numpy": "1.26.0"}`

### Alertas recomendadas
- BD: conexiones > 80% del pool
- Backend: latencia > 500ms (p95)
- Monte Carlo: tiempo de simulación > 2s
- Frontend: error rate > 1%
