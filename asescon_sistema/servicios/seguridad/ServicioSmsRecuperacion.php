<?php

declare(strict_types=1);

require_once __DIR__ . '/mensajeria/ProveedorSms.php';
require_once __DIR__ . '/mensajeria/ProveedorTwilioSms.php';

final class ServicioSmsRecuperacion
{
    public function disponible(): bool
    {
        try {
            $config = require __DIR__ . '/../../configuracion/mensajeria_sms.php';
            return trim((string)($config['cuenta_sid'] ?? '')) !== ''
                && trim((string)($config['token_autenticacion'] ?? '')) !== ''
                && trim((string)($config['numero_origen'] ?? '')) !== '';
        } catch (Throwable) {
            return false;
        }
    }

    public function enviarCodigo(string $telefono, string $codigo): void
    {
        $config = require __DIR__ . '/../../configuracion/mensajeria_sms.php';
        $proveedor = $this->crearProveedor($config);
        $proveedor->enviar(
            $telefono,
            'ASESCON: tu código para recuperar la contraseña es ' . $codigo . '. Vence en 10 minutos. No lo compartas.'
        );
    }

    private function crearProveedor(array $config): ProveedorSms
    {
        return match ((string)$config['proveedor']) {
            'twilio' => new ProveedorTwilioSms($config),
            default => throw new RuntimeException('El proveedor SMS configurado no es compatible con ASESCON.'),
        };
    }
}
