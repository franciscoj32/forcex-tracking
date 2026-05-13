# Git y Deploy Rapido

## Flujo normal

### En tu PC

Ubicate en el proyecto:

```powershell
cd C:\laragon\www\tracking-forcex
```

Revisa cambios:

```powershell
git status
```

Sube cambios a GitHub:

```powershell
git add .
git commit -m "describe tu cambio"
git push origin main
```

### En el hosting

Ubicate en el clon del repo:

```bash
cd /home/penguin2/repos/forcex-tracking
```

Publica todo:

```bash
bash deploy.sh
```

## Que hace `deploy.sh`

- Hace `git pull origin main`
- Copia la carpeta publica a `/home/penguin2/gsc-business.com`
- Publica `public/vendor/flag-icons/` en `/home/penguin2/gsc-business.com/flags/flag-icons/`
- Aplica permisos `755` a carpetas de `flags/` y `644` a sus archivos
- Copia la app a `/home/penguin2/tracking-forcex-app`
- Conserva `.env`, `.env.hosting`, `storage/` y `vendor/`
- Ejecuta `composer install`
- Ejecuta `php artisan migrate --force`
- Ejecuta `php artisan optimize:clear`

## Comandos utiles

Ver rama actual:

```bash
git branch
```

Ver estado:

```bash
git status
```

Volver a correr el deploy:

```bash
bash deploy.sh
```

## Si algo falla

Primero revisa el error y vuelve a correr:

```bash
cd /home/penguin2/repos/forcex-tracking
bash deploy.sh
```

Si el problema es de Laravel, prueba:

```bash
cd /home/penguin2/tracking-forcex-app
php artisan migrate --force
php artisan optimize:clear
```

## Regla recomendada

- Haz cambios en tu PC
- Sube a GitHub
- Despliega desde el hosting
- Evita editar archivos directo en produccion salvo emergencia
