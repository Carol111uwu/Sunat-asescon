# Actualización ASESCON

Base del paquete actualizada el 17 de noviembre de 2026. Parte de `main` en el commit `77ce096`, que ya incluye las integraciones anteriores y SUNAFIL. Para aplicar esta actualización se reconstruyen los servicios de aplicación con las bases y los volúmenes existentes. Este paquete no requiere migraciones nuevas.

## Preparación de producción del 17 de noviembre

La configuración principal usa el dominio `https://sistema.asescon.net`, HTTPS y `proxy_net`.
`compose.local.yaml` permite seleccionar explícitamente el entorno de pruebas de la PC.
Las credenciales se toman del archivo `.env` existente en la VPS. Los valores del entorno local acompañaban al ZIP recibido y se excluyen de este paquete para publicación.
Redis y el worker SOL quedan disponibles en el perfil opcional `prueba-workers`.

## Cambios del 17 de noviembre

- Cerrar sesión queda fijo al pie del menú. Los iconos de sol y luna están a su derecha en la misma fila y en la tarjeta de inicio de sesión. El navegador recuerda el tema elegido. El tema completo se aplica al inicio de sesión y al Dashboard.
- En RCE y RVIE, **Ver datos guardados** muestra RUC, razón social, año y meses de la caché del día. **Ver/consultar** presenta primero los resultados locales y consulta a SUNAT los períodos faltantes.
- Las consultas registran el progreso por empresa y período. Los resultados correctos se refrescan al terminar el lote aunque otros fallen. El reintento individual conserva la selección original del lote y un cero confirmado cuenta como resultado disponible.
- Las propuestas se importan a MariaDB para consulta durante el día. El TXT/CSV íntegro se conserva comprimido para **Excel original** y la recuperación local. El ZIP recibido se usa durante la extracción y se elimina al terminar. Los PDF, Excel y ZIP de exportación se preparan al solicitarlos. El cron sigue limpiando la caché del día anterior dentro de su ventana de 00:40 a 01:50, hora de Perú.

## Cambios anteriores

- Menú en el orden solicitado: Dashboard, General, Buzón electrónico, Tributario, AFPs y Extras. Utilitarios queda en Extras. Relación SSCO tiene su propia pantalla, con búsqueda y actualización del padrón existente.
- Mi equipo permite una cuenta por nombre o por RUC. La ficha pública completa el nombre o razón social. El RUC se guarda como usuario de inicio de sesión y la razón social en la ficha de equipo existente.
- La búsqueda de empresas mantiene la selección. Los botones Seleccionar todo y Deseleccionar todo aplican a las empresas de la lista completa, incluidas las ocultas por la búsqueda. El permiso para todas las empresas futuras se guarda aparte.
- Solo el Creador y el cliente MAIN pueden cambiar contraseñas. La recuperación por correo o SMS aplica esa misma regla.
- La cola SOL da prioridad a las solicitudes manuales y comprueba cada 15 minutos, cuando queda libre, si hay PDFs pendientes de precarga. Los intentos interrumpidos se marcan como tales.
- Compras conserva filtros, selección, PDF individuales, ZIP y Excel de seleccionados y envío. Admite hasta 100 seleccionados por operación. Compras y Ventas incorporan ZIP del período consultado en lotes de 100, con un manifiesto por archivo. Son reportes informativos SIRE. Los documentos oficiales del buzón se consultan en su propio módulo.
- Panel y SIRE usan URL comunes para los recursos. El panel prepara los datos del menú antes de renderizar. La imagen normaliza la lectura del código y verifica recursos estáticos críticos.
- Alertas muestra también notificaciones recientes de cada buzón al que tiene acceso el usuario. Los avisos por correo usan la configuración SMTP existente y abren la bandeja de la empresa correspondiente.
- Conserva el índice RUC anterior hasta activar por completo el siguiente. La limpieza nocturna conserva el Padrón RUC, SSCO, las notificaciones y el estado de los avisos enviados. Los adjuntos siguen sujetos a la limpieza temporal existente.
- SUNAFIL descarga enlaces PDF directos del portal en la sesión oficial y los vincula a la bandeja privada. Si el portal solo muestra un paso de acuse, una acción interactiva o un enlace que no entrega un PDF, se conserva la notificación y queda disponible la vinculación manual. Buzón SOL mantiene la descarga y precarga existentes.

## Publicación desde PowerShell y VS Code

La ruta de publicación es carpeta nueva, archivos del ZIP, rama en GitHub, pull request a `main` y actualización de la VPS.
Usa la terminal PowerShell de VS Code. El indicador debe comenzar por `PS`. Si muestra `MINGW64`, cambia a PowerShell antes de pegar estos comandos.

### 1. Preparar la carpeta

Este bloque mueve la carpeta anterior a `D:\Ant\pasado` y vuelve a clonar el repositorio en `D:\Ant\Sunat-asescon`.

```powershell
$ErrorActionPreference = 'Stop'
Set-Location 'D:\Ant'

if (Test-Path '.\Sunat-asescon') {
    New-Item -ItemType Directory -Force '.\pasado' | Out-Null
    $respaldo = '.\pasado\Sunat-asescon-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff')
    Move-Item '.\Sunat-asescon' $respaldo
}

git clone https://github.com/Carol111uwu/Sunat-asescon.git Sunat-asescon
if ($LASTEXITCODE -ne 0) { throw 'PARA. Falló la descarga de GitHub.' }

Set-Location '.\Sunat-asescon'
git switch -c mejora/vps-tema-sire
if ($LASTEXITCODE -ne 0) { throw 'PARA. No se pudo crear la rama.' }

git config --local user.name 'Carol111uwu'
git config --local user.email '01zqcarol@gmail.com'
code .
```

Sigue cuando veas `Switched to a new branch 'mejora/vps-tema-sire'`.
Para si aparece `fatal`, `error` o un mensaje `PARA`.

### 2. Copiar los archivos

Extrae `ASESCON_LISTO_VPS_20261117.zip` en Descargas. Copia todo su contenido dentro de `D:\Ant\Sunat-asescon` y acepta reemplazar los archivos.
En esa carpeta deben quedar directamente `asescon_sistema`, `docker`, `Dockerfile` y `compose.yaml`.
La carpeta `.git` creada por el clon permite enviar los cambios al repositorio.

### 3. Revisar y preparar el commit

```powershell
Set-Location 'D:\Ant\Sunat-asescon'
git branch --show-current
git config user.name
git --no-pager diff --check
if ($LASTEXITCODE -ne 0) { throw 'PARA. Hay errores de formato para revisar.' }

git add -A
if ($LASTEXITCODE -ne 0) { throw 'PARA. No se pudieron preparar los archivos.' }

git --no-pager diff --cached --shortstat
git --no-pager diff --cached --check
if ($LASTEXITCODE -ne 0) { throw 'PARA. Hay errores de formato para revisar.' }

git --no-pager diff --cached --name-only -- .env asescon_secure asescon_datos_privados asescon_sistema/base_datos
```

Sigue si la rama es `mejora/vps-tema-sire`, el nombre es `Carol111uwu`, aparecen archivos modificados y el último comando devuelve una salida vacía.
Los avisos de conversión LF/CRLF pueden aparecer durante `git add`. Los errores de formato incluyen archivo y línea y requieren revisión.
Para si el último comando muestra archivos privados o de bases de datos.

### 4. Guardar y subir

```powershell
git commit -m "Prepara ASESCON para VPS e integra tema y consultas SIRE"
if ($LASTEXITCODE -ne 0) { throw 'PARA. El commit no se creó.' }

git log -1 --format="%h %an <%ae>"
```

Sigue cuando el autor sea `Carol111uwu <01zqcarol@gmail.com>`.

```powershell
git push -u origin mejora/vps-tema-sire
if ($LASTEXITCODE -ne 0) { throw 'PARA. GitHub rechazó la subida.' }

git status -sb
```

Sigue si aparece `[new branch]`, la rama queda enlazada a `origin/mejora/vps-tema-sire` y el estado final muestra únicamente la línea de la rama.
Para si aparece `rejected`, `Authentication failed` o `fatal`.

### 5. Integrar en main

Abre [el formulario de pull request](https://github.com/Carol111uwu/Sunat-asescon/compare/main...mejora/vps-tema-sire?expand=1).
Comprueba `base: main` y `compare: mejora/vps-tema-sire`. Pulsa **Create pull request**.
Con las revisiones y comprobaciones requeridas completadas y sin conflictos, pulsa **Merge pull request** y **Confirm merge**.
El estado **Merged** confirma la integración. Si GitHub muestra conflictos o comprobaciones fallidas, detén la publicación y resuélvelos antes de la VPS.

## Publicación en la VPS

Desde el directorio que ya usa ASESCON en la VPS, documentado como `/srv/asescon/app`:

```bash
cd /srv/asescon/app
git status -sb
git remote -v
docker compose ps
```

Si la rama es `main`, el repositorio está limpio y el pull request ya se fusionó:

```bash
git pull --ff-only origin main
export ASESCON_REVISION="$(git rev-parse HEAD)"
docker compose up -d --build --no-deps app tareas
docker compose ps
docker compose logs --tail 60 app tareas
```

La reconstrucción usa los mismos volúmenes y el `.env` de la VPS. No ejecutar `docker compose down -v` ni importar SQL para este despliegue. Verifica Cerrar sesión, el tema en login y Dashboard, y Ver datos guardados en Compras y Ventas. Con una empresa autorizada, consulta un período faltante y verifica después la consulta local, el Excel original y la descarga del período. Comprueba también el estado del servicio de tareas y su próxima limpieza en la VPS.

## Pruebas y alcance

Las pruebas del proyecto y la sintaxis PHP se comprobaron localmente. También se probaron el flujo de resultados parciales, la lectura del original comprimido y la generación de Excel con datos de prueba. La consulta a SUNAT y el cron se verifican en el entorno de despliegue.
