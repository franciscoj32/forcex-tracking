# Maintenance And Backups

Guia de mantenimiento operativo y backups para `tracking-forcex`.

## 1. Mantenimiento rutinario

### Diario

- Confirmar que el login abre
- Confirmar que el tracking publico abre
- Revisar que no haya `500`
- Revisar espacio libre de disco
- Confirmar que el backup nocturno se ejecuto

### Semanal

- Revisar `storage/logs/`
- Revisar peso de `storage/app/backups/`
- Probar un restore en un entorno aparte si es posible
- Revisar usuarios admin y accesos activos

### Mensual

- Cambiar passwords sensibles si aplica
- Revisar permisos de carpetas criticas
- Limpiar backups muy antiguos
- Revisar versiones de PHP y dependencias para plan de upgrade

## 2. Que respaldar

Minimo:

- Base de datos MySQL
- `storage/app/`
  - aqui viven archivos privados y publicos del negocio
- `.env`
- `public/build/`
  - reconstruible, pero util para levantar rapido
- `composer.lock`

No hace falta respaldar siempre:

- `node_modules/`
- `tests/`
- caches temporales de Laravel

## 3. Politica sugerida de retencion

- 7 backups diarios
- 4 backups semanales
- 3 backups mensuales

Si el hosting es pequeño:

- dejar 7 diarios y descargar uno externo cada semana

## 4. Scripts incluidos

Se incluyen estos scripts:

- `scripts/backups/nightly-backup.sh`
- `scripts/backups/restore-latest.sh`

## 5. Cron recomendado

Ejemplo para correr cada noche a las 2:15 am:

```cron
15 2 * * * /bin/bash /home/usuario/tracking-forcex-app/scripts/backups/nightly-backup.sh /home/usuario/tracking-forcex-app >> /home/usuario/tracking-forcex-app/storage/logs/nightly-backup.log 2>&1
```

### Que hace el backup nocturno

- lee credenciales DB desde `.env`
- genera `mysqldump`
- empaqueta `storage/app/`, `.env`, `composer.lock`, `public/build/`
- deja todo en `storage/app/backups/YYYYmmdd_HHMMSS/`
- comprime a `.tar.gz`
- elimina carpetas temporales
- conserva solo los backups mas recientes

## 6. Restore sugerido

Restore rapido del ultimo backup:

```bash
/bin/bash /home/usuario/tracking-forcex-app/scripts/backups/restore-latest.sh /home/usuario/tracking-forcex-app
```

Antes de usar restore en produccion:

- poner el sitio en mantenimiento
- validar el nombre del backup
- confirmar que restaurar DB sobreescribira datos actuales

## 7. Flujo seguro de restauracion

### Opcion rapida

```bash
cd /home/usuario/tracking-forcex-app
php artisan down
/bin/bash scripts/backups/restore-latest.sh /home/usuario/tracking-forcex-app
php artisan optimize:clear
php artisan optimize
php artisan up
```

### Si algo falla

- dejar el sitio en mantenimiento
- revisar `storage/logs/`
- revisar permisos
- restaurar el backup anterior

## 8. Checklist post-restore

- login abre
- dashboard carga
- tracking publico abre
- documentos descargan
- reportes muestran data
- banderas y assets cargan

## 9. Recomendacion importante

No depender solo del hosting. Si el proyecto entra a produccion real:

- descargar copia externa de backups
- guardar al menos el dump SQL fuera del servidor
- probar restore periodicamente
