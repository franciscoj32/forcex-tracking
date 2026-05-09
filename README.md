# FORCEX Tracking

Aplicación privada en Laravel 12 para seguimiento de carga internacional de FORCEX LOGISTICS.

## Stack

- Laravel 12
- PHP 8.2+
- MySQL
- Blade + Tailwind
- Laravel Breeze

## Acceso privado

- La raíz `/` redirige a `login` si el usuario no está autenticado.
- No existe registro público.
- El usuario administrador inicial se crea por seeder:
  - Email: `admin@forcexlogistics.com.pe`
  - Password: `cambiar123`

Cambia esa contraseña al primer ingreso.

## Instalación local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

## Base de datos local usada en este entorno

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tracking_forcex
DB_USERNAME=root
DB_PASSWORD=
```

## Documentos privados

- Los archivos se guardan en `storage/app/private`.
- Los embarques usan la ruta `storage/app/private/embarques/{anio}/{nro_orden_trabajo}/`.
- La descarga siempre pasa por controlador protegido.
- El cliente solo puede descargar documentos de sus propios embarques.

## Despliegue en dominio de prueba

### Estructura recomendada

Proyecto:

```text
/home/usuario/tracking-forcex/
```

Document root del subdominio:

```text
/home/usuario/tracking-forcex/public
```

Nunca apuntar el dominio o subdominio a la raíz del proyecto.

### Pasos sugeridos

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --force --seed
php artisan storage:link
php artisan optimize
```

### Variables mínimas para el `.env` del hosting

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio-de-prueba.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tracking_forcex
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password
```

### Verificación rápida post despliegue

- El login debe cargar sin error 500.
- La ruta `/tracking-publico` debe responder.
- Deben verse estilos cargados desde `public/build`.
- El enlace `public/storage` debe existir.

## Documentacion funcional

- Arquitectura de datos: [docs/arquitectura-datos-tracking.md](C:/laragon/www/tracking-forcex/docs/arquitectura-datos-tracking.md:1)
