CREATE TABLE IF NOT EXISTS sunat_consultas_respuestas (
 id_consulta BIGINT UNSIGNED NOT NULL PRIMARY KEY,id_cliente_asescon INT NOT NULL,ruc CHAR(11) NOT NULL,
 respuesta JSON NOT NULL,INDEX idx_cliente_consulta(id_cliente_asescon,ruc,id_consulta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS sunat_detalles_declaracion (
 id_cliente_asescon INT NOT NULL,ruc CHAR(11) NOT NULL,formulario VARCHAR(30) NOT NULL,numero_orden VARCHAR(80) NOT NULL,
 respuesta JSON NOT NULL,consultado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id_cliente_asescon,ruc,formulario,numero_orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
