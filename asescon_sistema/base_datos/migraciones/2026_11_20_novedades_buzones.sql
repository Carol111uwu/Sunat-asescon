-- ASESCON: avisos de novedades para ambos buzones; no modifica los registros oficiales.
-- Importar UNA VEZ en la base principal usando respaldo previo.
CREATE TABLE IF NOT EXISTS buzon_sondeos (
    id_cliente INT NOT NULL,
    buzon VARCHAR(12) NOT NULL,
    ultima_consulta_exitosa DATETIME NOT NULL,
    inicializado TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id_cliente,buzon),
    INDEX idx_buzon_sondeos_exito (buzon,ultima_consulta_exitosa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS buzon_novedades (
    id_novedad BIGINT NOT NULL AUTO_INCREMENT,
    buzon VARCHAR(12) NOT NULL,
    id_cliente INT NOT NULL,
    id_documento BIGINT NOT NULL,
    detectada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_novedad),
    UNIQUE KEY uq_buzon_novedad (buzon,id_cliente,id_documento),
    INDEX idx_novedad_cliente (id_cliente,detectada_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS buzon_novedades_vistas (
    id_novedad BIGINT NOT NULL,
    id_usuario INT NOT NULL,
    vista_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_novedad,id_usuario),
    INDEX idx_vistas_usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
