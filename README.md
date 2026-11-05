# Midori Kissa

Tienda online de matcha y té japonés. Proyecto final del CFGS Desarrollo de Aplicaciones Web (IES Ataúlfo Argenta, 2026-2027).

**Tecnologías:** PHP 8.3 (MVC propio, API REST), MySQL 8.0, Angular y Bootstrap, Docker Compose con HTTPS local.

## Requisitos

- Docker con Docker Compose (Docker Desktop en Windows y macOS).

## Puesta en marcha

1. Crear el archivo de configuración a partir del ejemplo y cambiar las contraseñas:

   ```bash
   cp .env.example .env
   ```

2. Construir y arrancar los contenedores:

   ```bash
   docker compose up -d --build
   ```

3. Abrir la tienda en <https://localhost>. Para comprobar que la API y la base de datos funcionan, <https://localhost/api/health> debe devolver `{"status":"ok"}`.

   El certificado es autofirmado, así que el navegador mostrará un aviso la primera vez; hay que aceptarlo para continuar. Las peticiones a `http://localhost` se redirigen a HTTPS.

## Uso en GitHub Codespaces

Tras `docker compose up -d --build`, en la pestaña **Puertos** se abre el puerto **443** y se cambia su protocolo a **HTTPS** (clic derecho → *Change Port Protocol*).

## Desarrollo del frontend

Con los contenedores arrancados, el servidor de desarrollo de Angular recarga los cambios al momento y redirige las llamadas a `/api` hacia el contenedor web:

```bash
cd frontend
npm install
npm start
```

La aplicación queda en el puerto 4200. Para ver los cambios en `https://localhost` hay que volver a construir la imagen con `docker compose up -d --build`.

## Base de datos

- El script `database/midori_kissa.sql` crea las tablas y se ejecuta solo la primera vez que se crea el volumen de datos.
- Para borrar los datos y volver a crear la base de datos desde el script:

  ```bash
  docker compose down -v
  docker compose up -d --build
  ```

## Estructura

```
backend/
  config/       configuración (lee las variables de entorno del .env)
  Controllers/  controladores de la API
  Core/         clases base: Database, Router, Response
  public/       punto de entrada de la API (index.php)
database/       script SQL
docker/apache/  configuración de Apache (HTTP → HTTPS y alias /api)
frontend/       aplicación Angular con Bootstrap
```
