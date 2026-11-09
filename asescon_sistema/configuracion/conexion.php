<?php

declare(strict_types=1);

require_once __DIR__ . '/cargador_configuracion_privada.php';
require_once __DIR__ . '/rutas_privadas.php';

$config = [
    'host' => '',
    'usuario' => '',
    'password' => '',
    'base_datos' => '',
    'puerto' => 3306,
];

$privada = asesconCargarConfiguracionPrivada();
if (is_array($privada['db'] ?? null)) {
    $config = array_replace($config, $privada['db']);
}

$entorno = [
    'host' => getenv('ASESCON_DB_HOST') ?: null,
    'usuario' => getenv('ASESCON_DB_USER') ?: null,
    'password' => getenv('ASESCON_DB_PASSWORD') !== false ? (string)getenv('ASESCON_DB_PASSWORD') : null,
    'base_datos' => getenv('ASESCON_DB_NAME') ?: null,
    'puerto' => getenv('ASESCON_DB_PORT') ?: null,
];

foreach ($entorno as $clave => $valor) {
    if ($valor !== null && $valor !== '') {
        $config[$clave] = $clave === 'puerto' ? (int)$valor : $valor;
    }
}

foreach (['host', 'usuario', 'base_datos'] as $clave) {
    if (trim((string)($config[$clave] ?? '')) === '') {
        http_response_code(503);
        exit('Falta la configuración privada MySQL de ASESCON en el servidor.');
    }
}

$config['password'] = (string)($config['password'] ?? '');
$config['puerto'] = (int)($config['puerto'] ?? 3306);
if ($config['puerto'] <= 0 || $config['puerto'] > 65535) {
    http_response_code(503);
    exit('El puerto MySQL configurado para ASESCON no es válido.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conexion = new mysqli(
        (string)$config['host'],
        (string)$config['usuario'],
        (string)$config['password'],
        (string)$config['base_datos'],
        (int)$config['puerto']
    );
    $conexion->set_charset('utf8mb4');
    $conexion->query("SET time_zone = '-05:00'");
} catch (mysqli_sql_exception $error) {
    error_log('Conexión ASESCON: ' . $error->getMessage());
    http_response_code(503);
    exit('No se pudo conectar con la base de datos de ASESCON. Revisa la configuración privada del servidor.');
}
