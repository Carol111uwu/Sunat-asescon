# Cambios controlados ASESCON — 18/11/2026

Base: `Sunat-asescon-main (1)(2).zip` entregada por el usuario. Se compararon también
`ASESCON_BUZONES_TEMA_GLOBAL_20261118(1).zip` y las dos variantes de 17/11/2026.

- Buzón SOL: «Ver todas» recorre empresas autorizadas, incluidas las que aún no tienen notificaciones. Flechas ‹/›, numeración, nombre y RUC de empresa visibles; no mezcla filas.
- Consultar todas: reutiliza `ProgramadorBuzones` para programar la consulta de cada empresa con credenciales válidas. Reintentar todas vuelve a programar o reutiliza trabajos activos.
- Estados y notificaciones: contador global claramente separado del contador por empresa; panel de estado para empresa visible.
- Responsive y temas: estilos acotados a Buzón SOL, botones alineados a la izquierda, ventanas legibles en oscuro, etiquetas accesibles, flechas táctiles.
- Login: logotipo original con idénticas proporciones en tema claro y oscuro. Se mantienen los colores del tema oscuro.
- RCE y RVIE: «Datos guardados» no equivale a «hoy». Se conserva el historial y se permite ver períodos anteriores.
- Limpieza: se evita la eliminación automática de datos de negocio y de archivos SUNAT/SUNAFIL hasta comprobar su respaldo. ATENCIÓN: monitorear almacenamiento y definir retención y exportación confirmada.

## Precauciones

1. El paquete NO incluye `.env` real, claves ni respaldos de bases. Mantenerlos fuera de GitHub.
2. No reemplazar datos ni desplegar directamente en producción: probar login, consultas, RCE, RVIE, Buzón SOL, PDF, PDF faltante, filtros y permisos en entorno de ensayo con copia controlada.
3. El ZIP no altera el worker Python ni acelera/garantiza por sí solo las consultas SUNAT; errores de acceso SOL requieren diagnóstico específico de logs del worker y validación autorizada del portal.
4. La cola de trabajos no es secuencial garantizada solo por la interfaz. La ejecución y limitación de concurrencia se configuran en los workers existentes.
5. Registros ya borrados por el CRON anterior no se recuperan automáticamente. Solo se puede volver a consultar o restaurar respaldos.
6. La autenticación, CSRF, autorización y tablas existentes se conservan. Para el estado de empresa en la pantalla «Ver todas», el botón de comprobar estado apunta a la empresa actualmente visible.
7. La limpieza queda conservadora. Establecer más adelante una política de retención por tabla/archivo y confirmaciones de respaldo antes de volver a eliminar.
