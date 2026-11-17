# ASESCON con Docker

## Configuración de producción

El archivo `compose.yaml` usa `https://sistema.asescon.net`, HTTPS y la red externa `proxy_net`.
Las claves, usuarios y nombres de las bases se cargan desde el archivo `.env` existente de la VPS.
La conexión entre los contenedores utiliza `db:3306`. El código PHP sigue leyendo las variables `ASESCON_DB_*` y `ASESCON_DB_SUNAT_*`.
El `Dockerfile` conserva los permisos de lectura de Apache.

Los servicios habituales son `app`, `tareas` y `db`. Redis y el worker SOL están disponibles con el perfil `prueba-workers`.
La actualización de una VPS ya instalada usa los volúmenes y el entorno privado existentes.
Después de fusionar el pull request, desde `/srv/asescon/app` se ejecuta `docker compose up -d --build --no-deps app tareas`.

## Uso local opcional

El archivo `compose.local.yaml` requiere una selección explícita. Usa `localhost:8081`, desactiva HTTPS y permite trabajar sin la red externa del proxy.

Para reutilizar el entorno de pruebas de tu compañera se requiere su `.env` local y sus volúmenes de prueba.

```powershell
docker compose -f compose.yaml -f compose.local.yaml config --quiet
docker compose -f compose.yaml -f compose.local.yaml up -d --build app tareas
```

Para ejecutar también las pruebas del worker SOL se añade `--profile prueba-workers`.
El perfil de pruebas tiene las consultas del worker habilitadas en `compose.local.yaml` y necesita credenciales autorizadas.

## Importar respaldos

Con los contenedores iniciados:

```powershell
Get-Content .\respaldo-principal.sql | docker compose exec -T db mariadb -u root -p asescon
Get-Content .\respaldo-sunat.sql | docker compose exec -T db mariadb -u root -p asescon_sunat
```

MariaDB solicitará la contraseña root configurada en `.env`. No guardes los respaldos ni `.env` en el repositorio.

## Publicación en GitHub

Antes del primer commit ejecuta `git status --short --ignored` y confirma que `asescon_secure`, `asescon_datos_privados`, `.env`, archivos `*.servidor.php`, logs y locks aparecen ignorados.


## Tareas automáticas (antes Cron de cPanel)

El servicio `tareas` usa la misma imagen y los mismos datos que la web. Ejecuta cada 5 minutos las 3 tareas
que estaban en cPanel. Los horarios reales los controla cada script:

| Tarea | Script | Horario de trabajo |
|---|---|---|
| Sincronización nocturna (SUNAT, SIRE, SUNAFIL) | `procesos/sincronizacion/cron_nocturno.sh` | 2:00–3:00 a. m. |
| Buzón SOL | `procesos/buzon_sol/procesar_cola.sh 0` | Continuo |
| Limpieza de almacenamiento | `procesos/almacenamiento/cron_limpieza.sh` | 12:40–1:50 a. m. |

**Primera vez (una sola vez por servidor):** instalar el entorno privado (Python 3.13.18, Playwright y Chromium)
con el instalador original del sistema. Queda guardado en el volumen `asescon_recursos_privados`:

```powershell
docker compose up -d --build
docker compose exec -u www-data tareas asescon-instalar-entorno
```

Revisar que funciona:

```powershell
docker compose ps                                   # tareas: healthy
docker compose logs tareas                          # "cron iniciado"
docker compose exec tareas tail -n 20 /var/www/html/asescon_datos_privados/almacenamiento/sincronizacion/cron_docker.log
```

Si una instalación falla, el instalador no se repite solo: revisa
`/var/www/html/asescon_recursos_privados/instalacion_runtime.log` y, tras corregir la causa, borra
`RUNTIME_INTENTADO` en esa carpeta antes de volver a intentar.

## Detrás del proxy

Apache usa `mod_remoteip` (`docker/apache-proxy.conf`), así que permisos, auditoría y registros ven la IP real del
usuario y no la del proxy. Si el proxy recibió la visita por HTTPS, PHP ve `HTTPS=on`.

Los archivos `configuracion/*.servidor.php` (contraseñas) ya no entran a la imagen. En Docker la configuración llega
por variables de entorno (`.env`).
