<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/respuesta_api.php';
asesconPrepararRespuestaApi();
require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../controladores/sunat/ControladorAutenticacionSire.php';
header('Content-Type: application/json; charset=utf-8');

try {
    asesconExigirLogin();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        throw new RuntimeException('Método HTTP no permitido.');
    }
    $input = $_POST;
    if (str_contains(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
        $input = json_decode((string)file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new InvalidArgumentException('Entrada JSON inválida.');
    }
    $tokenCsrf = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? ''));
    if (!asesconValidarCsrf($tokenCsrf, 'csrf_sire')) {
        http_response_code(403);
        throw new RuntimeException('Token CSRF inválido o vencido.');
    }
    $data = (new ControladorAutenticacionSire($conexion))->validar($input);
    echo json_encode(['ok'=>true,'data'=>$data] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (ExcepcionSunat $e) {
    if (session_status() !== PHP_SESSION_ACTIVE) asesconIniciarSesion();
    http_response_code(400);
    $mensaje = $e->getMessage();
    $ayuda = (stripos($mensaje, 'autenticacion del usuario') !== false || stripos($mensaje, 'autenticación del usuario') !== false)
        ? 'Revisa que RUC + Usuario SOL + Clave SOL correspondan a la misma empresa y que Client ID + Client Secret sean las credenciales API SIRE vigentes.' : '';
    echo json_encode(['ok'=>false,'mensaje'=>$mensaje,'ayuda'=>$ayuda,'codigo_sunat'=>$e->getCodigoSunat()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (JsonException|InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (session_status() !== PHP_SESSION_ACTIVE) asesconIniciarSesion();
    if (http_response_code() < 400) http_response_code($e->getCode() === 403 ? 403 : 500);
    error_log('ASESCON autenticación SIRE: ' . get_class($e));
    $mensaje = http_response_code() === 403 ? $e->getMessage() : 'No se pudo completar la validación SIRE. Revisa el registro de errores PHP del servidor.';
    echo json_encode(['ok'=>false,'mensaje'=>$mensaje], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
