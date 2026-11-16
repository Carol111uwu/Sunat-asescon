# Integración de acciones SUNAFIL

La base es `main` en el commit `683b13f`. Este ZIP incluye solo los archivos de la integración. Se copia su contenido sobre un checkout limpio de ASESCON que ya tenga el PR #2 integrado. Conserva el historial de Git y el resto de la configuración.

## Cambios

- Desde **Buzón electrónico → SUNAFIL → Más acciones** se abre la ficha para solicitar un PDF, enviar un correo con el PDF o dar seguimiento al caso.
- La cuenta principal puede habilitar el seguimiento y asignar a un miembro activo de Mi equipo que tenga acceso a esa empresa y permisos de edición de SUNAFIL. El responsable puede registrar avances mientras el caso esté abierto. La cuenta principal puede cerrar o reabrir el caso.
- La descarga individual busca la notificación por su identificador oficial en la sesión de la empresa. Si el portal no entrega un PDF directo, la bandeja mantiene **Ver documento** para vincularlo manualmente. Los adjuntos válidos ya vinculados se conservan.
- Las solicitudes de PDF y las consultas de bandeja tienen resultados distintos. La descarga individual no ejecuta la importación de la bandeja ni vuelve a programar avisos.

## Publicar en GitHub

En PowerShell:

```powershell
Set-Location 'D:\Ant'
git clone https://github.com/Carol111uwu/Sunat-asescon.git Sunat-asescon-sunafil
Set-Location 'D:\Ant\Sunat-asescon-sunafil'
git status -sb
git rev-parse --short HEAD
git switch -c mejora/sunafil-acciones
code .
```

Antes de copiar el ZIP, comprueba que el clon esté limpio y que la versión de base sea `683b13f`. Si `main` avanzó, revisa qué cambió antes de superponer los archivos. Extrae el ZIP y pega su contenido dentro del clon. Revisa lo preparado antes del commit:

```powershell
git add -A
git diff --cached --check
git diff --cached --name-status
git diff --cached --name-only -- Dockerfile compose.yaml docker .env
```

El último comando debe quedar vacío. La única ruta SQL nueva esperada es `asescon_sistema/base_datos/migraciones/2026_11_16_seguimiento_sunafil.sql`. Publica la rama y abre un pull request a `main`.

```powershell
git commit -m "Integra acciones y seguimiento de SUNAFIL"
git push -u origin mejora/sunafil-acciones
```

## Despliegue y validación

Después de fusionar el PR, desde la carpeta actual de ASESCON en la VPS y con `main` limpio:

```bash
git pull --ff-only origin main
export ASESCON_REVISION="$(git rev-parse HEAD)"
docker compose up -d --build --no-deps app tareas
docker compose ps
```

Se mantienen el contenedor de base de datos, sus volúmenes y el `.env` del servidor. Para activar el seguimiento, entra como cuenta principal, abre una notificación y pulsa **Habilitar seguimiento SUNAFIL**. Crea dos tablas nuevas si faltan, sin alterar tablas que ya existen. Si el usuario de la aplicación carece del permiso `CREATE`, un administrador puede aplicar solo esa migración en la base principal. La bandeja y el PDF pueden usarse aunque el seguimiento aún no esté habilitado.

Comprueba con una empresa autorizada la consulta general, la consulta de un PDF individual, la descarga desde la interfaz y el correo con el PDF a un destinatario de prueba. Verifica también que el usuario asignado pueda registrar un avance y que otro usuario sin asignación no pueda modificarlo. El correo requiere la configuración SMTP existente.
