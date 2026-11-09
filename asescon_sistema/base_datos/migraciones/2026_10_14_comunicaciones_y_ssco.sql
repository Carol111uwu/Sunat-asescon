                                                                                      
CREATE TABLE IF NOT EXISTS configuracion_comunicaciones (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 nombre_remitente VARCHAR(120) NOT NULL DEFAULT 'ASESCON',
 correo_copia VARCHAR(254) NOT NULL DEFAULT '',
 correo_avisos VARCHAR(254) NOT NULL DEFAULT '',
 avisos_activos TINYINT NOT NULL DEFAULT 0,
 ultimo_sol_inicial BIGINT UNSIGNED NOT NULL DEFAULT 0,
 ultimo_sunafil_inicial BIGINT UNSIGNED NOT NULL DEFAULT 0,
 plantilla_asunto VARCHAR(200) NOT NULL DEFAULT 'Entregables de {cliente}',
 plantilla_mensaje TEXT NOT NULL,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO configuracion_comunicaciones (id,plantilla_mensaje) VALUES
 (1,'Estimado/a {cliente}:\nLe enviamos los siguientes entregables:\n{entregables}\n\nSaludos cordiales.');
CREATE TABLE IF NOT EXISTS tipos_entregable (
 id_entregable INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(120) NOT NULL,
 UNIQUE KEY nombre_unico(nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cola_avisos_buzones (
 id_aviso BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 entidad VARCHAR(10) NOT NULL,
 id_documento BIGINT UNSIGNED NOT NULL,
 id_cliente INT NOT NULL,
 estado VARCHAR(12) NOT NULL DEFAULT 'PENDIENTE',
 intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
 proximo_intento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 enviado_en DATETIME NULL,
 UNIQUE KEY aviso_unico(entidad,id_documento),
 KEY pendientes(estado,proximo_intento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS envios_entregables (
 id_envio BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 clave_envio CHAR(64) NOT NULL,
 id_cliente INT NOT NULL,
 id_usuario INT NOT NULL,
 destinatario VARCHAR(254) NOT NULL,
 asunto VARCHAR(200) NOT NULL,
 estado VARCHAR(12) NOT NULL DEFAULT 'ENVIANDO',
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY envio_unico(clave_envio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS padron_ssco (
 id_registro INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 ruc CHAR(11) NOT NULL,
 razon_social VARCHAR(250) NOT NULL,
 resolucion VARCHAR(250) NOT NULL,
 fecha_firme DATE NULL,
 fecha_publicacion DATE NULL,
 domicilio_fiscal VARCHAR(500) NOT NULL DEFAULT '',
 representante_legal VARCHAR(250) NOT NULL DEFAULT '',
 UNIQUE KEY registro_unico(ruc,resolucion),
 KEY por_nombre(razon_social)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS actualizacion_padron_ssco (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 origen VARCHAR(30) NOT NULL,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 registros INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
