# ASESCON — Continuidad y cambios propuestos · 18/11/2026

## Integración
Base: ASESCON_BUZON_SIRE_RESPONSIVE_CORREGIDO_20261118.zip.
Se contrastó con la copia del 17 de noviembre y con el paquete main recibido.
No se sobrescribieron los módulos de descargas implementados por la compañera,
ni se incluyó el `.env` real de ninguna copia.

## Buzones
- Acciones de SOL y SUNAFIL: una sola fila alineada a la derecha.
- En móvil, las acciones siguen en fila desplazable horizontalmente; en desktop
  se reserva más ancho en la columna Acciones.
- Enviar conserva sus dos canales (correo, WhatsApp) con títulos accesibles.
- Si un trabajo SOL de la empresa está activo y se solicita otro PDF,
  se registra `EN_COLA`. No aborta una sesión en curso; al quedar libre,
  el procesador convierte el documento diferido a trabajo pendiente.
- Prueba de cola: `php asescon_sistema/instalacion/verificar_pdf_en_cola_sol.test.php`.
- No cambia ni sustituye los conectores Python que obtiene PDF de SUNAT.

## Mi equipo
- Correo principal, secundario y teléfono se señalan como opcionales.
- RUC se exige solo cuando se elige la modalidad `RUC como usuario`, ya que
  se utiliza como identificador para iniciar sesión y se valida con SUNAT.
- Se aclaran permisos de menú versus permisos de edición por rol.
- Las pestañas de funciones futuras siguen informativas y no simulan guardar
  configuraciones inexistentes.

## Retención
- Notificaciones de SOL y SUNAFIL no se programan para eliminación.
- Los directorios `sunat` y `sunafil` siguen protegidos contra la limpieza
  indiscriminada de archivos en el cron existente.
- Se añade una política SQL de caducidad por fecha de consulta para cachés
  RCE/RVIE/SIRE y algunos reportes temporales.
- IMPORTANTE: desactivada por defecto (`ASESCON_LIMPIEZA_SQL_DIARIA=0`).
  Nunca activar sin respaldo, verificación de tablas/relaciones reales y
  un `--dry-run`; la compatibilidad con todos los esquemas de producción
  NO está confirmada. La limpieza de archivos sigue la política anterior.
- No se eliminan clientes, usuarios, credenciales, bases completas ni
  tablas de notificaciones.

## Diagnóstico pendiente en producción
- El resumen GENERAL muestra errores de acceso SOL y estados pendientes
  SUNAFIL. Sin registros del conector y credenciales válidas no se puede
  garantizar que se solucione con un cambio de presentación.
- Revisar logs protegidos, tiempos de ejecución y respuesta real de SUNAT.
- Probar permisos, rol, consulta RCE/RVIE de varios meses, notificaciones y
  descarga PDF con una empresa que está en sincronización.
- No se ha actualizado GitHub ni la VPS; solo se creó un candidato local.
- Trabajar sobre una rama de pruebas; no cambiar FACT-KARD ni el proxy.
