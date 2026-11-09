<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/respuesta_api.php';
asesconPrepararRespuestaApi();
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/sunat/RepositorioValidacionIgvSire.php';
require_once __DIR__.'/../../servicios/sunat/PeriodosSire.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

try {
    asesconExigirLogin();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        throw new DomainException('Solo se permite guardar mediante POST.');
    }
    if (!asesconValidarCsrf((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''), 'csrf_sire')) {
        http_response_code(403);
        throw new DomainException('Sesión vencida. Vuelve a cargar el Dashboard.');
    }
    if (!asesconTienePermiso($conexion,'SIRE','editar')) {
        http_response_code(403);
        throw new DomainException('No tienes permiso para guardar revisiones tributarias.');
    }
    $input = json_decode((string)file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new InvalidArgumentException('Datos inválidos.');
    $idCliente = (int)($input['id_cliente'] ?? 0);
    $periodo = trim((string)($input['periodo'] ?? ''));
    if ($idCliente <= 0 || !asesconPuedeVerCliente($conexion, $idCliente)
        || !PeriodosSire::permitido($periodo,false)) {
        throw new InvalidArgumentException('Selecciona una empresa y un mes válidos.');
    }
    $valores = [];
    foreach (['credito_validado','saldo_favor_anterior','retenciones_aplicables','percepciones_aplicables','otros_ajustes'] as $campo) {
        $texto = trim((string)($input[$campo] ?? ''));
        if (!preg_match('/^-?\d{1,13}(?:\.\d{1,2})?$/D', $texto)) {
            throw new InvalidArgumentException('Importe inválido en: '.$campo.'. Introduce soles, sin comas, con hasta dos decimales.');
        }
        if ($campo !== 'otros_ajustes' && str_starts_with($texto, '-')) {
            throw new InvalidArgumentException('Los importes de crédito y saldos no pueden ser negativos.');
        }
        $valores[$campo] = $texto;
    }
    $valores['observaciones'] = trim((string)($input['observaciones'] ?? ''));
    if ((function_exists('mb_strlen') ? mb_strlen($valores['observaciones'],'UTF-8') : strlen($valores['observaciones'])) > 1000) {
        throw new InvalidArgumentException('Las observaciones no pueden superar 1000 caracteres.');
    }
    (new RepositorioValidacionIgvSire($conexion))->guardar(
        $idCliente,$periodo,$valores,(int)($_SESSION['id_usuario'] ?? 0)
    );
    echo json_encode(['ok'=>true,'mensaje'=>'Revisión contable guardada. Los libros SIRE no se modificaron.'], JSON_UNESCAPED_UNICODE);
} catch (JsonException|InvalidArgumentException|DomainException $e) {
    if (http_response_code() < 400) http_response_code(422);
    echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Revision IGV SIRE: '.get_class($e));
    echo json_encode(['ok'=>false,'mensaje'=>'No se pudo guardar la revisión. Revisa el registro del servidor.'], JSON_UNESCAPED_UNICODE);
}
