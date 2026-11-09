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

