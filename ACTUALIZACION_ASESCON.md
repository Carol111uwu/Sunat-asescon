# Actualización ASESCON

Base de integración: GitHub, commit `719e73d`, que incluye la corrección de permisos de lectura para Apache. Este paquete contiene el proyecto completo sin la carpeta `.git`. Conserva los archivos SQL del repositorio. La actualización se instala sobre las bases y volúmenes actuales mediante una reconstrucción de los servicios de aplicación.

## Cambios

- Menú en el orden solicitado: Dashboard, General, Buzón electrónico, Tributario, AFPs y Extras. Utilitarios y el cierre de sesión quedan en Extras. Relación SSCO tiene su propia pantalla, con búsqueda y actualización del padrón existente.
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

1. Crea una copia de trabajo nueva mediante Git. Comprueba que `git rev-parse --short HEAD` muestre `719e73d` antes de aplicar el ZIP. Si GitHub ya avanzó, integra las diferencias con esa versión antes de superponer los archivos.

```powershell
New-Item -ItemType Directory -Force 'D:\Ant' | Out-Null
Set-Location 'D:\Ant'
git clone https://github.com/Carol111uwu/Sunat-asescon.git Sunat-asescon-actualizado
Set-Location 'D:\Ant\Sunat-asescon-actualizado'
git rev-parse --short HEAD
git switch -c mejora/asescon-integrado
code .
```

2. Extrae el ZIP en una carpeta temporal. Copia el contenido extraído a `D:\Ant\Sunat-asescon-actualizado`. Al terminar, `Dockerfile`, `compose.yaml` y `asescon_sistema` deben quedar directamente en la carpeta del clon.
3. Revisa la lista de archivos antes del commit. Las credenciales, `.env` y los datos de producción se quedan en la VPS.

```powershell
git status --short
git diff --check
git add -A
git diff --cached --name-status
git commit -m "Integra SIRE, buzones y despliegue ASESCON"
git push -u origin mejora/asescon-integrado
```

4. En GitHub, abre un pull request hacia `main`, revisa los cambios y fusiónalo cuando pasen las pruebas.

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

La reconstrucción usa los mismos volúmenes y el `.env` de la VPS. No ejecutar `docker compose down -v` ni importar SQL para este despliegue. Verifica el panel después del login, una consulta RUC, un ZIP con 100 comprobantes disponibles, un aviso por correo y un PDF de cada buzón. En Mi equipo, prueba una cuenta por RUC, una por nombre, la asignación masiva y las restricciones de cambio de clave. Si la configuración SMTP está en `.env`, Compose pasa sus variables existentes a ambos servicios. El correo automático enlaza a la bandeja protegida.

## Pruebas y alcance

Se verificó sintaxis PHP y se generó un ZIP de 100 reportes en cada libro con datos de prueba. La recepción de correo y la descarga oficial requieren una sesión y configuración autorizadas en la VPS. El código y los datos de producción se despliegan por separado.
