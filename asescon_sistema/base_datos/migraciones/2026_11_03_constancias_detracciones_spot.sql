                                                                                 
                                                                                     
                                                                                   
CREATE TABLE IF NOT EXISTS detracciones_archivos_sunat (
    id_archivo_sunat BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    ruc CHAR(11) NOT NULL,
    clave_origen CHAR(64) NOT NULL,
    periodo CHAR(6) NOT NULL,
    numero_constancia VARCHAR(100) NOT NULL,
    fecha_deposito DATE NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    tipo_cuenta VARCHAR(80) NOT NULL DEFAULT '',
    numero_cuenta VARCHAR(100) NOT NULL DEFAULT '',
    documento VARCHAR(160) NOT NULL DEFAULT '',
    contraparte VARCHAR(220) NOT NULL DEFAULT '',
    modalidad VARCHAR(40) NOT NULL DEFAULT '',
    origen_archivo VARCHAR(180) NOT NULL,
    datos_origen JSON NOT NULL,
    importado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_spot_cliente_clave (id_cliente,clave_origen),
    KEY idx_spot_cliente_periodo (id_cliente,periodo,fecha_deposito),
    KEY idx_spot_ruc_constancia (ruc,numero_constancia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
