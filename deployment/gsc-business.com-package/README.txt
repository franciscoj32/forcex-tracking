Carpeta publica del hosting (gsc-business.com)

Aqui solo quedan los dos archivos propios del hosting:
- index.php: arranca la app desde ../tracking-forcex-app y usa esta carpeta como "public".
- .htaccess: reglas de Laravel.

Todo lo demas (build/, images/, flags/, favicon, robots) lo copia deploy.sh desde
public/ del repositorio. La app la copia deploy.sh desde la raiz del repositorio.

Ver docs/git-deploy-guide.md para los pasos completos.
