Deploy de FORCE X Tracking para hosting compartido

Estructura objetivo en el servidor:

1. Subir el contenido de `root/` a:
   /home/penguin2/gsc-business.com

2. Subir la carpeta `tracking-forcex-app/` completa a:
   /home/penguin2/tracking-forcex-app

3. Dentro de `/home/penguin2/tracking-forcex-app`:
   - renombrar `.env.hosting` a `.env`
   - revisar si `DB_HOST=localhost` aplica en tu hosting

4. Si tienes acceso a terminal en el hosting, ejecutar:
   php artisan migrate --force
   php artisan storage:link
   php artisan optimize:clear
   php artisan optimize

5. Permisos recomendados:
   - `storage/` con escritura
   - `bootstrap/cache/` con escritura
   - `flags/flag-icons/` con carpetas `755` y archivos `644`

Notas:
- El `index.php` de `root/` ya apunta a `../tracking-forcex-app`.
- El proyecto ya incluye `vendor/` y `public/build/`, asi que no necesitas correr `composer install` ni `npm run build` para levantarlo.
- El tracking publico carga banderas desde `/flags/flag-icons/`.
- Si el hosting no permite `storage:link`, la carga de logos/branding publico necesitara un enlace simbolico manual desde:
  /home/penguin2/gsc-business.com/storage
  hacia
  /home/penguin2/tracking-forcex-app/storage/app/public
