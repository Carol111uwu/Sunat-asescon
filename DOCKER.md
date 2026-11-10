# ASESCON con Docker

## Inicio local

1. Copia `.env.example` como `.env` y reemplaza ambas contraseñas.
2. Importa en MariaDB el respaldo inicial de las bases `asescon` y `asescon_sunat`. Las migraciones incluidas en `asescon_sistema/base_datos/migraciones` son incrementales y no crean todo el esquema desde cero.
3. Ejecuta:

   ```powershell
   docker compose config
   docker compose build
   docker compose up -d
   docker compose ps
   ```

4. Abre `http://localhost:8080` (o el puerto definido en `.env`).

Los documentos privados, secretos y datos de MariaDB se guardan en volúmenes Docker y no entran en la imagen ni en Git.

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
