# Integración SUNAFIL – 20/11/2026

Base: ZIP de main entregado como Sunat-asescon-main (3).zip.
Se conservan los cambios de main: notificaciones, planificación de rondas,
navegador de empresas, exportación y gestión de PDF.
Se incorporan categorías, campos oficiales, descarga de Acciones Previas,
detalle de fiscalización y WhatsApp Web. El PDF se adjunta manualmente en WhatsApp.

## Base de datos
Respaldar la BD principal antes de aplicar cualquier migración.
Aplicar, si aún no existen, las migraciones:
- asescon_sistema/base_datos/migraciones/2026_11_20_novedades_buzones.sql
- asescon_sistema/base_datos/migraciones/2026_11_20_campos_sunafil.sql

Ambas son aditivas. No borrar volúmenes, tablas ni registros.
No se incluye .env real: conservar el propio de cada entorno fuera de Git.

## GitHub
Clonar o actualizar una copia limpia de main y crear una rama independiente.
El parche INTEGRACION_SUNAFIL.patch adjunto al ZIP contiene solo cambios
respecto del ZIP original de main. Ejecutar git apply --check con ese parche
antes de git apply. Si no aplica limpiamente, detenerse y resolver conflictos
con los cambios más recientes; no forzar. Revisar git diff antes de commit.
Abrir un Pull Request hacia main. No publicar .env, respaldos ni credenciales.

## Verificación
152 pruebas estáticas Node y 5 pruebas Python pasaron.
No se realizó prueba en la VPS, ni conexión real a SUNAFIL, ni ejecución PHP
en este entorno. Comprobar localmente ambas migraciones, una consulta de
SUNAFIL, Acciones Previas, Orientación, Fiscalización, WhatsApp y campana.
La descarga automática de Fiscalización requiere una orden real y puede
necesitar adaptar los controles del portal. Los listados no verificados
se informan como parciales. No se garantiza la conciliación automática
de identificadores históricos de versiones anteriores del conector;
revisar posibles duplicados antes de producción, sin eliminar historial.
