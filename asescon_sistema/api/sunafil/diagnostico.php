<?php
declare(strict_types=1);
require_once __DIR__ . '/../../configuracion/respuesta_api.php';
asesconPrepararRespuestaApi();
require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../servicios/sunafil/DiagnosticoBuzonSunafil.php';
try {
    asesconExigirLogin();
    asesconExigirPermiso($conexion, 'BUZON_SUNAFIL', 'ver');
    echo json_encode(['ok' => true, 'data' => (new DiagnosticoBuzonSunafil())->comprobar()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo revisar el entorno SUNAFIL.']);
}
