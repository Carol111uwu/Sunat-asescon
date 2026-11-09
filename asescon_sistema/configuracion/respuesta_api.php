<?php

declare(strict_types=1);

function asesconPrepararRespuestaApi(): void
{
    ob_start();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    register_shutdown_function(static function (): void {
        $salida = (string) ob_get_contents();
        ob_end_clean();

        $respuesta = json_decode(trim($salida), true);
        if (is_array($respuesta) && array_key_exists('ok', $respuesta)) {
            echo $salida;
            return;
        }

        $codigo = http_response_code();
        if ($codigo < 400 || $codigo > 599) {
            $codigo = 502;
        }

        foreach (headers_list() as $cabecera) {
            if (stripos($cabecera, 'Location:') === 0) {
                header_remove('Location');
                $codigo = 401;
                break;
            }
        }

        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            error_log('ASESCON API: error PHP en ' . basename((string) $error['file']) . ':' . (int) $error['line']);
        } elseif ($salida !== '') {
            error_log('ASESCON API: respuesta distinta de JSON (HTTP ' . $codigo . ', longitud ' . strlen($salida) . ').');
        }

        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        $mensaje = match ($codigo) {
            401 => 'Tu sesión terminó. Vuelve a iniciar sesión e intenta nuevamente.',
            403 => 'El servidor denegó el acceso a la consulta (HTTP 403). Revisa permisos y reglas de seguridad del hosting.',
            503 => 'ASESCON no pudo iniciar el servicio. Revisa la conexión de MySQL y los registros de errores PHP del servidor.',
            default => 'La consulta no pudo completarse en el servidor (HTTP ' . $codigo . '). Revisa el registro de errores PHP de cPanel.',
        };
        echo json_encode(['ok' => false, 'mensaje' => $mensaje, 'codigo' => 'RESPUESTA_SERVIDOR_' . $codigo], JSON_UNESCAPED_UNICODE);
    });
}
