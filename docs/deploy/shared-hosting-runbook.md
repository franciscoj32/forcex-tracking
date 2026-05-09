# Shared Hosting Runbook

Guia reutilizable para subir `tracking-forcex` a un hosting compartido o a un servidor real con estructura separada entre web root y app root.

## 1. Datos que debes tener antes de empezar

Pide o define estos datos antes de tocar archivos:

- Dominio o subdominio final
- Ruta publica del dominio
  - Ejemplo: `/home/usuario/mi-dominio.com`
- Ruta privada de la app Laravel
  - Ejemplo: `/home/usuario/tracking-forcex-app`
- Version de PHP disponible en el hosting
  - Requerido por este proyecto: `PHP 8.2+`
- Acceso por `SSH`
  - Si no hay SSH, al menos `phpMyAdmin` y `Administrador de archivos`
- Host de base de datos
  - Ejemplo: `localhost`
- Puerto de base de datos
  - Ejemplo: `3306`
- Nombre de base de datos
- Usuario de base de datos
- Password de base de datos
- Posibilidad de crear `cron jobs`
- Permisos de escritura en:
  - `storage/`
  - `bootstrap/cache/`

## 2. Estructura recomendada

Usar esta forma evita exponer `.env`, `vendor`, `app` y el resto de Laravel al publico.

- Web root del dominio:
  - `/home/usuario/mi-dominio.com`
- App root de Laravel:
  - `/home/usuario/tracking-forcex-app`

En esta app el `index.php` del dominio apunta a la app privada con:

```php
$appRoot = __DIR__.'/../tracking-forcex-app';
```

## 3. Paquete de despliegue

El paquete generado para este proyecto usa esta forma:

- `root/`
  - Contenido que va al dominio publico
- `tracking-forcex-app/`
  - Contenido privado de Laravel

Regla importante:

- Subir **el contenido** de `root/` al web root
- Subir **el contenido** de `tracking-forcex-app/` al app root
- No subir las carpetas contenedoras dentro de otra carpeta extra

## 4. Pasos de instalacion

### Paso 1. Crear rutas en el hosting

Crear estas carpetas:

- `/home/usuario/mi-dominio.com`
- `/home/usuario/tracking-forcex-app`

### Paso 2. Subir archivos publicos

Subir al web root:

- `index.php`
- `.htaccess`
- `build/`
- `favicon.ico`
- `favicon.svg`
- `robots.txt`

### Paso 3. Subir la app privada

Subir al app root:

- `app/`
- `bootstrap/`
- `config/`
- `database/`
- `public/`
- `resources/`
- `routes/`
- `storage/`
- `vendor/`
- `artisan`
- `composer.json`
- `composer.lock`
- el resto de archivos base del proyecto

### Paso 4. Crear y revisar `.env`

Renombrar `.env.hosting` a `.env` y revisar como minimo:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mi-dominio.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=mi_base
DB_USERNAME=mi_usuario
DB_PASSWORD=mi_password

FILESYSTEM_DISK=public
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file
```

### Paso 5. Verificar `index.php`

En el web root, confirmar:

```php
$appRoot = __DIR__.'/../tracking-forcex-app';
```

Si la carpeta privada tiene otro nombre, cambiar esa linea.

### Paso 6. Revisar assets de Vite

Esta app necesita que exista:

- `tracking-forcex-app/public/build/manifest.json`

Si falta, subir `public/build` completo desde local.

### Paso 7. Revisar assets publicos externos

Esta app publica tambien necesita:

- `gsc-business.com/vendor/flag-icons/...`
  o
- `gsc-business.com/flags/flag-icons/...`

Si el hosting bloquea `/vendor/`, moverlo a `/flags/` y ajustar:

Archivo:

- `resources/views/components/public-tracking-layout.blade.php`

Linea esperada:

```blade
<link rel="stylesheet" href="{{ asset('flags/flag-icons/css/flag-icons.min.css') }}">
```

### Paso 8. Revisar permisos

Base recomendada:

- Carpetas: `755`
- Archivos: `644`

Rutas criticas:

- `storage/`
- `bootstrap/cache/`
- carpetas internas de assets publicos si el hosting no las sirve bien

Si algo existe en disco pero da `404`, revisar permisos de todas las carpetas padre.

### Paso 9. Ejecutar comandos de Laravel

Desde SSH:

```bash
cd /home/usuario/tracking-forcex-app
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize:clear
php artisan optimize
```

### Paso 10. Dejar seguridad final

- `APP_DEBUG=false`
- borrar cualquier `phpinfo.php`
- no dejar archivos temporales de prueba

## 5. Problemas comunes y solucion

### Error 500 al abrir el dominio

Revisar:

- que el `index.php` apunte al app root correcto
- que `vendor/` se haya subido completo
- que `vendor/autoload.php` exista y cargue

### `Vite manifest not found`

Falta subir:

- `tracking-forcex-app/public/build`

### `SQLSTATE[28000] Access denied`

Revisar:

- usuario DB asignado a la base
- password correcta
- host correcto

### `Table ... doesn't exist`

Falta correr:

```bash
php artisan migrate --force
```

### Banderas no cargan en tracking publico

Revisar:

- CSS de `flag-icons`
- SVG de banderas
- permisos `755` en carpetas internas
- que el hosting no bloquee `/vendor/`

## 6. Validacion final antes de evaluacion

- Login abre sin errores
- Dashboard carga rapido
- Embarques abren y tabs funcionan
- Reportes muestran data
- Tracking publico funciona con codigo real
- Banderas y assets cargan
- Subida y descarga de documentos funciona
- `APP_DEBUG=false`

## 7. Datos concretos del entorno de pruebas actual

Solo como referencia del staging que acabamos de levantar:

- Dominio: `https://gsc-business.com`
- Web root: `/home/penguin2/gsc-business.com`
- App root: `/home/penguin2/tracking-forcex-app`
- PHP: `8.3.30`
- DB host: `localhost`
- DB port: `3306`

No guardar passwords reales en este documento.
