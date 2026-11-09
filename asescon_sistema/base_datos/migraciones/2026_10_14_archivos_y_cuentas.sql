CREATE TABLE IF NOT EXISTS archivos_comprobantes (
 id_cliente INT NOT NULL,
 ruc_emisor CHAR(11) NOT NULL,
 tipo_documento VARCHAR(2) NOT NULL,
 serie VARCHAR(20) NOT NULL,
 numero VARCHAR(20) NOT NULL,
 archivo_pdf VARCHAR(500) NULL,
 archivo_xml VARCHAR(500) NULL,
 archivo_cdr VARCHAR(500) NULL,
 cuenta_contable VARCHAR(30) NOT NULL DEFAULT '',
 centro_costos VARCHAR(120) NOT NULL DEFAULT '',
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id_cliente,ruc_emisor,tipo_documento,serie,numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cuentas_contables_sire (
 id_cliente INT NOT NULL,
 codigo VARCHAR(30) NOT NULL,
 nombre VARCHAR(200) NOT NULL,
 PRIMARY KEY(id_cliente,codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
