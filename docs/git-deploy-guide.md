# Subir FORCEX Tracking al hosting con Git

## Cómo está armado

| Dónde | Qué hay |
|---|---|
| GitHub `franciscoj32/forcex-tracking` | El código. `public/build` (CSS/JS compilados) también se sube, porque el hosting no tiene Node. |
| `/home/penguin2/repos/forcex-tracking` | Clon del repositorio en el hosting. Solo se usa para correr `deploy.sh`. |
| `/home/penguin2/tracking-forcex-app` | La app que corre: código, `.env`, `storage/` (documentos subidos, logs) y `vendor/`. |
| `/home/penguin2/gsc-business.com` | Carpeta pública del dominio: `index.php`, `build/`, `images/`, `flags/` y el enlace `storage`. |

`deploy.sh` copia el repositorio a esas dos carpetas. **Nunca toca** el `.env`, `storage/` ni los datos (solo corre las migraciones).

## En tu PC, cada vez que quieras publicar

```powershell
cd C:\laragon\www\tracking-forcex
npm run build
php artisan test
git add -A
git commit -m "describe el cambio"
git push origin main
```

`npm run build` va siempre antes del commit: el hosting usa los archivos de `public/build` tal cual.

## En el hosting (Terminal de cPanel o SSH)

```bash
cd /home/penguin2/repos/forcex-tracking
bash deploy.sh
```

El script:
1. hace `git pull`;
2. pone el sitio en mantenimiento;
3. copia la app y la carpeta pública;
4. corre `composer install --no-dev`;
5. corre las migraciones;
6. guarda la configuración y las rutas en caché (`php artisan optimize`);
7. vuelve a abrir el sitio.

Si algo falla, saca el sitio de mantenimiento y muestra el error.

## Solo la primera vez (o en una actualización grande)

### 1. Respaldo

- **Base de datos:** cPanel → phpMyAdmin → `penguin2_tracking` → Exportar.
- **Documentos subidos:** cPanel → Administrador de archivos → comprimir `tracking-forcex-app/storage/app`.

### 2. Requisitos del hosting

- **PHP 8.2 o superior** (cPanel → Seleccionar versión de PHP), con las extensiones `gd`, `zip`, `mbstring`, `xml`, `dom`, `fileinfo` e `intl`. Las usan los PDF, el Excel y el logo.
- **Composer:** el script lo busca solo. Si no lo encuentra, indica la ruta así: `COMPOSER_BIN=/ruta/composer bash deploy.sh`.

### 3. Revisar el `.env` del hosting

Archivo: `/home/penguin2/tracking-forcex-app/.env`. Agrega o corrige estas líneas; las demás se quedan como están:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://gsc-business.com
APP_FALLBACK_LOCALE=es
APP_TIMEZONE=America/Lima

QUEUE_CONNECTION=database
CACHE_STORE=file

# Correo: crea la cuenta en cPanel → Cuentas de correo
MAIL_MAILER=smtp
MAIL_HOST=mail.gsc-business.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=tracking@gsc-business.com
MAIL_PASSWORD=la-clave-de-esa-cuenta
MAIL_FROM_ADDRESS="tracking@gsc-business.com"

FORCEX_NOTIFICATIONS=true
FORCEX_DAILY_ALERTS_AT=07:00
FORCEX_WHATSAPP_COUNTRY_CODE=51
```

- **Correos:** mientras no tengas la cuenta de correo, deja `MAIL_MAILER=log`. Así los correos no salen, quedan escritos en `storage/logs/laravel.log`.
- **Caché:** después de cambiar el `.env`, corre `php artisan optimize` en la carpeta de la app, o vuelve a correr `deploy.sh`.
- **`APP_KEY`:** si es igual a la de tu PC, genera una propia del hosting con `cd /home/penguin2/tracking-forcex-app && php artisan key:generate --force`. Solo cierra las sesiones abiertas; el sistema no guarda datos cifrados con esa clave.

### 4. Tarea programada (cron)

cPanel → Trabajos de cron → cada minuto:

```
* * * * * cd /home/penguin2/tracking-forcex-app && php artisan schedule:run >> /dev/null 2>&1
```

Con esto salen los correos de la cola, el resumen diario de las 7:00 y la limpieza de las 3:00.

### 5. Primer ingreso

- **No corras `db:seed` en el hosting:** carga los datos de prueba.
- **Configuración:** sube el logo y revisa los colores, el tipo de cambio y los umbrales de alertas.
- **Usuarios:** crea los usuarios reales (Comercial, Customer, Operaciones) desde Usuarios.

## Si algo falla

- **Sitio en mantenimiento:** `php /home/penguin2/tracking-forcex-app/artisan up`
- **Ver el error:** `tail -n 50 /home/penguin2/tracking-forcex-app/storage/logs/laravel.log`
- **Pantalla sin estilos:** falta `public/build` en el repositorio. Corre `npm run build` en tu PC, haz commit y vuelve a publicar.
- **No se ve el logo:** revisa que exista el enlace `/home/penguin2/gsc-business.com/storage` hacia `tracking-forcex-app/storage/app/public`.

## Regla

Haz los cambios en tu PC, súbelos a GitHub y publícalos con `deploy.sh`. No edites archivos directamente en el hosting.
