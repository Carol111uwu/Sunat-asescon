#!/bin/sh
set -eu

sunat_db="${ASESCON_DB_SUNAT_NAME:-asescon_sunat}"
case "$sunat_db" in
  *[!A-Za-z0-9_]*) echo "Nombre de base SUNAT no válido" >&2; exit 1 ;;
esac

mariadb --protocol=socket -uroot -p"$MARIADB_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS \`$sunat_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`$sunat_db\`.* TO '$MARIADB_USER'@'%';
FLUSH PRIVILEGES;
SQL

