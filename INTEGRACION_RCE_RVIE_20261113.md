# Integración de RCE y RVIE — 13/11/2026

Comparación: actualización local ASESCON_RCE_USUARIOS_SUNAT_LOCAL_20261111.zip frente al ZIP Sunat-asescon-main (1).zip entregado por el usuario.

- Se conserva de la versión actual el soporte de RVIE: comprobante individual, exportación PDF/Excel y descargas por cantidad.
- Se restaura en RCE la selección por fila, selección masiva limitada a 60, filtros, ZIP de PDF, Excel de seleccionados y menú Enviar (correo/WhatsApp), que habían quedado desconectados de la vista.
- Se conserva el diseño de PDF de RCE que estaba en la actualización del equipo.
- Se añade la selección por cantidad (10/20/50/60/todos visibles, máximo 60) a las acciones RCE, evitando superar el límite de la API.
- Se corrige el espaciado vertical de filas en el generador PDF nuevo utilizado por RVIE.
- No se cambia Docker, las bases de datos, claves privadas ni las tareas CRON.
- Las pruebas sintácticas y automatizadas no sustituyen pruebas con datos y acceso real SUNAT.
