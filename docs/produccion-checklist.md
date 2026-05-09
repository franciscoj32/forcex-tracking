# Checklist de Producción

Memoria rápida para el paso de `tracking-forcex` a producción.

## Dominio y document root

- Dominio final: `tracking.forcexlogistics.com.pe`
- El subdominio debe apuntar a:
  - `/home/usuario/tracking-forcex/public`
- Nunca apuntar el subdominio a:
  - `/home/usuario/tracking-forcex/`

## Entorno

- En producción, `APP_ENV=production`
- En producción, `APP_DEBUG=false`
- En producción, `APP_URL=https://tracking.forcexlogistics.com.pe`
- No dejar valores locales como `.test`, `localhost` o `127.0.0.1` en producción

## Base de datos

- Configurar credenciales reales de MySQL en `.env`
- Verificar acceso antes de migrar
- Ejecutar migraciones solo en el servidor correcto

## Seguridad

- No exponer `.env`
- No exponer la raíz del proyecto
- No permitir acceso directo a `storage/app/private`
- Los documentos sensibles deben descargarse solo por controlador protegido
- Validar que el cliente solo vea sus propios embarques y documentos

## Archivos y permisos

- Confirmar permisos correctos en:
  - `storage/`
  - `bootstrap/cache/`
- Crear enlace público solo para `storage/app/public`:
  - `php artisan storage:link`
- Recordar que los documentos privados no deben servirse desde URL pública

## Laravel

- Ejecutar:
  - `php artisan migrate --seed`
  - `php artisan config:cache`
  - `php artisan route:cache`
  - `php artisan view:cache`
- Si cambia `.env`, limpiar y reconstruir cachés

## Usuario inicial

- Usuario admin inicial:
  - `admin@forcexlogistics.com.pe`
- Contraseña inicial:
  - `cambiar123`
- Cambiar la contraseña al primer ingreso

## Qué no copiar desde local

- No copiar configuración de Laragon a producción
- No dejar `APP_URL` local
- No dejar rutas locales de Windows
- No asumir que `.test` tiene algo que ver con producción

## Revisión final antes de abrir acceso

- Login carga directamente al entrar al subdominio
- No existe web pública
- `/` redirige a login si no hay sesión
- `/` redirige a dashboard si el usuario ya autenticó
- Descarga de documentos funciona con permisos por rol
- `APP_DEBUG` está apagado
- El subdominio apunta a `/public`
