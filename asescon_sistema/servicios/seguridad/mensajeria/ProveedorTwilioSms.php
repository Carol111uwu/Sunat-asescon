<?php

declare(strict_types=1);

require_once __DIR__ . '/ProveedorSms.php';

final class ProveedorTwilioSms implements ProveedorSms
{
    public function __construct(private array $configuracion)
    {
    }

    public function enviar(string $destino, string $mensaje): void
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('La extensión cURL es necesaria para enviar SMS.');
        }

        $sid = trim((string)($this->configuracion['cuenta_sid'] ?? ''));
        $token = (string)($this->configuracion['token_autenticacion'] ?? '');
        $origen = trim((string)($this->configuracion['numero_origen'] ?? ''));
        if ($sid === '' || $token === '' || $origen === '') {
            throw new RuntimeException('Falta la configuración SMS de ASESCON en el servidor.');
        }

        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException('No se pudo iniciar el envío del SMS.');
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERPWD => $sid . ':' . $token,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_POSTFIELDS => http_build_query([
                'To' => $destino,
                'From' => $origen,
                'Body' => $mensaje,
            ], '', '&', PHP_QUERY_RFC3986),
        ]);

        $respuesta = curl_exec($curl);
        $errorCurl = curl_error($curl);
        $estadoHttp = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($respuesta === false) {
            throw new RuntimeException($errorCurl !== '' ? $errorCurl : 'No fue posible enviar el SMS.');
        }
        if ($estadoHttp < 200 || $estadoHttp >= 300) {
            $json = json_decode((string)$respuesta, true);
            $detalle = is_array($json) ? trim((string)($json['message'] ?? '')) : '';
            throw new RuntimeException($detalle !== '' ? $detalle : 'El proveedor SMS rechazó el envío.');
        }
    }
}
