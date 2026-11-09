<?php

declare(strict_types=1);

require_once __DIR__ . '/cargador_configuracion_privada.php';

$config = [
    'host' => '',
    'puerto' => 587,
    'usuario' => '',
    'password' => '',
    'seguridad' => 'tls',
    'base_url' => '',
];

$privada = asesconCargarConfiguracionPrivada();
if (is_array($privada['smtp'] ?? null)) {
    $config = array_replace($config, $privada['smtp']);
}

$entorno = [
    'host' => getenv('ASESCON_SMTP_HOST') ?: null,
    'puerto' => getenv('ASESCON_SMTP_PORT') ?: null,
    'usuario' => getenv('ASESCON_SMTP_USER') ?: null,
    'password' => getenv('ASESCON_SMTP_PASSWORD') !== false ? (string)getenv('ASESCON_SMTP_PASSWORD') : null,
    'seguridad' => getenv('ASESCON_SMTP_SEGURIDAD') ?: null,
    'base_url' => getenv('ASESCON_URL_PUBLICA') ?: null,
];

foreach ($entorno as $clave => $valor) {
    if ($valor !== null && $valor !== '') {
        $config[$clave] = $clave === 'puerto' ? (int)$valor : $valor;
    }
}

foreach (['host', 'usuario', 'password', 'base_url'] as $clave) {
    if (trim((string)($config[$clave] ?? '')) === '') {
        throw new RuntimeException('Falta la configuración privada de correo de ASESCON en el servidor.');
    }
}

$config['puerto'] = (int)($config['puerto'] ?? 587);
$config['seguridad'] = strtolower(trim((string)($config['seguridad'] ?? 'tls')));
if (!in_array($config['seguridad'], ['tls', 'ssl'], true)) {
    throw new RuntimeException('La seguridad SMTP configurada no es válida.');
}

return $config;
