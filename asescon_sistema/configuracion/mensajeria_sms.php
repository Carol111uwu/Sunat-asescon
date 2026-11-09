<?php

declare(strict_types=1);

require_once __DIR__ . '/cargador_configuracion_privada.php';

$config = [
    'proveedor' => 'twilio',
    'cuenta_sid' => '',
    'token_autenticacion' => '',
    'numero_origen' => '',
];

$privada = asesconCargarConfiguracionPrivada();
if (is_array($privada['sms'] ?? null)) {
    $config = array_replace($config, $privada['sms']);
}

$entorno = [
    'proveedor' => getenv('ASESCON_SMS_PROVEEDOR') ?: null,
    'cuenta_sid' => getenv('ASESCON_TWILIO_ACCOUNT_SID') ?: null,
    'token_autenticacion' => getenv('ASESCON_TWILIO_AUTH_TOKEN') ?: null,
    'numero_origen' => getenv('ASESCON_TWILIO_FROM') ?: null,
];

foreach ($entorno as $clave => $valor) {
    if ($valor !== null && $valor !== '') {
        $config[$clave] = $valor;
    }
}

$config['proveedor'] = strtolower(trim((string)($config['proveedor'] ?? '')));
if ($config['proveedor'] !== 'twilio') {
    throw new RuntimeException('El proveedor SMS configurado no es compatible con ASESCON.');
}

foreach (['cuenta_sid', 'token_autenticacion', 'numero_origen'] as $clave) {
    if (trim((string)($config[$clave] ?? '')) === '') {
        throw new RuntimeException('Falta la configuración privada SMS de ASESCON en el servidor.');
    }
}

return $config;
