-- Ejecutar en la base que contiene rce_detalle y rvie_detalle.
-- Conserva los comprobantes anteriores y permite repetir la migración.
ALTER TABLE rce_detalle ADD COLUMN IF NOT EXISTS datos_impresion_sire JSON NULL;
ALTER TABLE rvie_detalle ADD COLUMN IF NOT EXISTS datos_impresion_sire JSON NULL;
