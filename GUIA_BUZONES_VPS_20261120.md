# ASESCON — Buzones SOL / SUNAFIL y avisos — 20/11/2026

## Base
Esta actualización se construyó SOBRE el ZIP que el usuario subió:
`Sunat-asescon-main (3)(1).zip`. Incluye el repositorio COMPLETO para GitHub / VPS,
no solo la carpeta del Buzón. No incluye `.env` real ni claves de producción.

## Cambios

1. `docker/tareas.cron` ahora registra dos trabajos más que ejecutan:
   - `procesos/buzones/planificar_rondas.php` cada 5 minutos.
   - `procesos/buzon_sunafil/procesar_cola.sh` cada 5 minutos, independiente del lote SIRE.
   Quedan cinco entradas en total (se conserva SIRE, SOL y limpieza).
   La ronda SOL/SUNAFIL comienza desde las 00:00 de Lima. Se encolan **hasta tres
   empresas por buzón por pasada**, con un intervalo predeterminado de tres horas
   entre consultas programadas por empresa. El cron usa cierre por bloqueo
   y omite solicitudes activas. Es la hora de INICIO, no una promesa de finalización.
   No se insertan `crontab` adicionales en la VPS si ya funciona `tareas`.

2. El procesador SOL ya no se detiene entre 00:30 y 02:00.
   SUNAFIL no queda esperando a que termine el lote global de SIRE.
   Los registros se conservan aunque un cliente falle.

3. Búsqueda de empresas en vista «Todas»: flechas anterior/siguiente + lupa
   (campo escrito con sugerencias) por razón social o RUC; los cambios de empresa
   leen la información ya guardada y **NO** disparan el acceso SOL/SUNAFIL.
   Permisos por usuario y paginación de 100 filas para evitar congelamientos.

4. Histórico: la lectura SOL no filtra por año. Su límite de páginas se amplía
   con `ASESCON_SOL_MAX_PAGINAS` (80–400, por defecto 300).
   SUNAFIL amplía de 30 a 120 páginas, incluidos los recorridos de documentos.
   **Solo se puede recuperar lo que el portal realmente muestre a la cuenta.**
   Si hay páginas inaccesibles, el sistema debe informar un fallo, no inventar
   que la empresa tiene cero notificaciones. Las consultas de históricos extensos
   pueden exceder el tiempo del proceso; habrá que ajustar el conector por lotes.

5. Las últimas consultas EXITOSAS se anotan por empresa y buzón en MySQL,
   separado de la marca de Cron. Si aún no hubo una, se muestra «Primera consulta
   pendiente». No se muestran falsas fechas de éxito cuando existe un error.

6. La campana consulta `api/buzones/avisos_nuevos.php` cada 45 segundos mientras
   el navegador está abierto. La detección requiere que una ronda del servidor
   haya encontrado una notificación. Cada usuario autorizado puede marcar visto
   su aviso; si estaba desconectado, lo verá al regresar. La primera ronda
   después de importar la migración constituye una LÍNEA BASE y no anuncia
   registros antiguos como si acabaran de llegar.

7. Los errores SOL/SUNAFIL incluyen códigos específicos (por ejemplo, error
   de acceso, paginación o Chromium). Ninguna contraseña debe mostrarse en
   la interfaz, los registros ni los reportes.

8. Los PDF no se precargan automáticamente. El usuario los solicita bajo demanda.
   Se siguen descargando desde el navegador hacia su dispositivo. El sistema
   necesita una copia privada TEMPORAL mientras el navegador la solicita.
   La opción `ASESCON_PDF_SOLO_DISPOSITIVO=1`, DESACTIVADA POR DEFECTO,
   elimina la copia del servidor tras enviar el archivo completo en modo
   «Descargar». **No** elimina una copia si la transmisión se interrumpe,
   si es solo vista previa o si se pide un rango parcial. No purga
   automáticamente documentos anteriores o solicitudes sin descargar:
   necesitará una política de caché adicional si se exige cero retención total.

## Migración obligatoria para avisos y últimas consultas exitosas

Antes de activar el código, realiza una copia de seguridad de la BD principal.
Importa en ESA BD:

`asescon_sistema/base_datos/migraciones/2026_11_20_novedades_buzones.sql`

El SQL crea:
- `buzon_sondeos`: última consulta correcta e inicialización por empresa y buzón.
- `buzon_novedades`: eventos de nuevas notificaciones sin duplicados.
- `buzon_novedades_vistas`: lectura independiente por usuario.

No borra ni altera `buzon_sol_sunat`, `buzon_sunafil`, `clientes`,
RVIE/RCE, credenciales, documentos ni historial.

## Procedimiento recomendado de integración con trabajo de tu compañera

NO reemplazar `main` a ciegas, ni hacer `git push --force`, ni `docker compose down -v`.
Tu compañera está trabajando en el mismo repositorio.

1. Clonar/fetch del `main` vigente y crear una rama de integración.
2. Copiar sobre esa rama solamente los archivos MODIFICADOS de esta entrega,
   revisando conflictos con el trabajo reciente. Abrir PR y aprobar diff.
3. Respaldar la base de datos y los volúmenes actuales.
4. Importar la migración anterior.
5. Una vez aprobado el PR y actualizado el código en la VPS, ejecutar:
   `docker compose up -d --build app tareas`
   La nueva imagen contendrá las cinco tareas Cron. **No** se recrea MariaDB
   ni se ejecuta `down -v`.
6. Verificar estado del servicio y consola:
   `docker compose ps`
   `docker compose logs --since=15m --tail=100 tareas`
   `docker compose exec -T tareas php -l /var/www/html/asescon_sistema/procesos/buzones/planificar_rondas.php`
7. Si faltan Python, Playwright o Chromium:
   `docker compose exec -u www-data tareas asescon-instalar-entorno`
   (solo si el proyecto lo necesita; nunca reinstales sin revisar RUNTIME_OK).
8. En el Buzón SOL consulta UNA empresa que funcione local, desde el `tareas`
   de la VPS, y compara el código de error del paso de acceso con el resultado
   local. Después prueba SUNAFIL, páginas históricas, la campana y los PDF.
9. Revisa el bloqueo/restricciones de SUNAT/SUNAFIL: esta entrega no asegura
   superar verificaciones interactivas, disponibilidad del portal o límites
   de velocidad. No ejecutes solicitudes descontroladas para 70 empresas.

## Ajustes opcionales en `.env` de Docker

```
ASESCON_INTERVALO_BUZONES=10800
ASESCON_BUZONES_POR_RONDA=3
ASESCON_SOL_MAX_PAGINAS=300
ASESCON_PRECARGAR_PDF=0
ASESCON_PDF_SOLO_DISPOSITIVO=0
```

`ASESCON_PDF_SOLO_DISPOSITIVO=1` requiere probar primero la descarga
completa y aceptar que al volver a abrir el PDF se necesitará solicitarlo
otra vez desde SUNAT/SUNAFIL. Los avisos y metadatos no se borran.

## Diagnóstico y limitaciones

- Cron muestra actividad, pero SOL no accede: inspecciona el código `SOL_*`
  en los estados sin compartir claves. Compara el navegador con local.
- `SOL_PAGINACION_INCOMPLETA`: no se puede confirmar el historial entero.
- `SOL_VERIFICACION_INTERACTIVA`: completar la verificación en portal oficial;
  no intentar eludirla automáticamente.
- `PY_NAVEGADOR_NO_DISPONIBLE`: revisar Playwright/Chromium en `tareas`.
- Una notificación nueva se conoce cuando el sistema vuelva a consultar esa
  cuenta. Polling visual de 45 s NO es una entrega inmediata de SUNAT.
- El cron planifica trabajos, pero con 70+ empresas las rondas pueden tardar
  horas por límites de recursos, red o portales. Es necesario medirlo en VPS.
- **No se ha probado una autenticación real de producción ni un PDF remoto**
  desde este entorno. Las pruebas estáticas / Node / PHP / Python se
  refieren exclusivamente al código entregado.
