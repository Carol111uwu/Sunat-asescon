                                                                     
                                                                            
                                                              
CREATE TABLE IF NOT EXISTS sire_validaciones_igv (
    id_cliente INT NOT NULL,
    periodo CHAR(6) NOT NULL,
    credito_validado DECIMAL(17,2) NOT NULL,
    saldo_favor_anterior DECIMAL(17,2) NOT NULL DEFAULT 0.00,
    retenciones_aplicables DECIMAL(17,2) NOT NULL DEFAULT 0.00,
    percepciones_aplicables DECIMAL(17,2) NOT NULL DEFAULT 0.00,
    otros_ajustes DECIMAL(17,2) NOT NULL DEFAULT 0.00,
    observaciones VARCHAR(1000) NOT NULL DEFAULT '',
    actualizado_por INT NOT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_cliente, periodo),
    KEY idx_sire_validaciones_periodo (periodo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
