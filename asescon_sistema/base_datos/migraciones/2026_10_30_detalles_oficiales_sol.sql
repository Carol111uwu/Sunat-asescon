                                                                           
                                                     
                                                                          
                                                                           
                                                                         
SET @tipo_id_buzon_sol = (
  SELECT COLUMN_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'buzon_sol_sunat'
    AND COLUMN_NAME = 'id_documento' LIMIT 1
);
SET @crear_detalle_sol = IF(@tipo_id_buzon_sol IS NULL,
  'SELECT ''ERROR: selecciona la base principal que contiene buzon_sol_sunat'' AS resultado',
  CONCAT('CREATE TABLE IF NOT EXISTS buzon_sol_detalles_oficiales (',
  'id_documento ', @tipo_id_buzon_sol, ' NOT NULL, ',
  'contenido LONGTEXT NOT NULL, archivos_json LONGTEXT NULL, ',
  'obtenido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ',
  'PRIMARY KEY (id_documento), ',
  'CONSTRAINT fk_buzon_sol_detalle_documento FOREIGN KEY (id_documento) ',
  'REFERENCES buzon_sol_sunat(id_documento) ON DELETE CASCADE ON UPDATE CASCADE',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')
);
PREPARE crear_detalle FROM @crear_detalle_sol;
EXECUTE crear_detalle;
DEALLOCATE PREPARE crear_detalle;

                                                                          
                                                                          
                                                                
SET @indice_existente = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'buzon_sol_sunat'
    AND INDEX_NAME = 'uq_buzon_sol_cliente_ident'
);
SET @duplicados_sol = (
  SELECT COUNT(*) FROM (
    SELECT id_cliente, identificador_externo
    FROM buzon_sol_sunat
    WHERE id_cliente IS NOT NULL AND identificador_externo IS NOT NULL
    GROUP BY id_cliente, identificador_externo HAVING COUNT(*) > 1
  ) x
);
SET @comando_indice = IF(@indice_existente > 0 OR @duplicados_sol > 0,
  'SELECT ''Índice único existente o pendiente de resolver duplicados'' AS resultado',
  'ALTER TABLE buzon_sol_sunat ADD UNIQUE KEY uq_buzon_sol_cliente_ident(id_cliente, identificador_externo)'
);
PREPARE preparar_indice FROM @comando_indice;
EXECUTE preparar_indice;
DEALLOCATE PREPARE preparar_indice;
SELECT @duplicados_sol AS grupos_duplicados_para_revision;
