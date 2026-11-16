<?php
declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/RepositorioLoteSire.php';
require_once __DIR__ . '/../../servicios/sunat/ExportadorZipPeriodoSire.php';

header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$fallar = static function (int $codigo, string $mensaje): never {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $fallar(405, 'Utiliza el botón de descarga del módulo SIRE.');
}
if (empty($_SESSION['id_usuario'])) {
    $fallar(401, 'Debes iniciar sesión nuevamente.');
}
if (!asesconValidarCsrf((string)($_POST['csrf_token'] ?? ''), 'csrf_sire_masivo')) {
    $fallar(403, 'La sesión de descarga venció. Recarga la página.');
}
$libro = strtoupper((string)($_POST['libro'] ?? ''));
if (!in_array($libro, ['RCE', 'RVIE'], true) || !asesconTienePermiso($conexion, $libro, 'ver')) {
    $fallar(403, 'No tienes acceso a este libro SIRE.');
}
$periodo = (string)($_POST['periodo'] ?? '');
if (!preg_match('/^20\d{2}(?:00|0[1-9]|1[0-2])$/D', $periodo)) {
    $fallar(422, 'Selecciona un mes o año válido.');
}
$cliente = filter_var($_POST['cliente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($cliente === false || $cliente === null || ($cliente > 0 && !asesconPuedeVerCliente($conexion, $cliente))) {
    $fallar(403, 'No tienes permiso para la empresa solicitada.');
}
$offsetTexto = (string)($_POST['offset'] ?? '0');
if (!ctype_digit($offsetTexto) || strlen($offsetTexto) > 7 || (int)$offsetTexto > 1000000) {
    $fallar(422, 'El número de lote es inválido.');
}
$offset = (int)$offsetTexto;
$excluir = (string)($_POST['excluir_anteriores'] ?? '0') === '1';
$tipo = trim((string)($_POST['tipo'] ?? ''));
if (!preg_match('/^.{0,80}$/usD', $tipo)) {
    $fallar(422, 'El filtro del comprobante es demasiado largo.');
}
if (!class_exists('ZipArchive')) {
    $fallar(503, 'La extensión ZIP de PHP no está disponible.');
}

$archivoTemporal = null;
try {
    $resultado = (new RepositorioLoteSire($conexion))->obtener($libro, $periodo, $cliente, $excluir, $tipo,
        asesconFiltroClientesSql($conexion, 'c'), $offset, ExportadorZipPeriodoSire::TAMANO_LOTE);
    $total = $resultado['total'];
    $filas = $resultado['filas'];
    $cantidad = $filas->num_rows;
    if ($cantidad === 0) {
        $fallar(404, 'No quedan comprobantes para este lote. Vuelve a consultar el período.');
    }
    session_write_close();
    $archivoTemporal = tempnam(sys_get_temp_dir(), 'asescon_sire_');
    if ($archivoTemporal === false) {
        throw new RuntimeException('No se pudo reservar espacio temporal.');
    }
    ['generados' => $generados, 'errores' => $errores] =
        (new ExportadorZipPeriodoSire())->crear($libro, $periodo, $offset, $filas, $archivoTemporal);
    $siguiente = min($total, $offset + $cantidad);
    $numeroLote = intdiv($offset, ExportadorZipPeriodoSire::TAMANO_LOTE) + 1;
    $totalLotes = (int)ceil($total / ExportadorZipPeriodoSire::TAMANO_LOTE);
    $nombre = "ASESCON-{$libro}-{$periodo}-lote-{$numeroLote}-de-{$totalLotes}.zip";
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Content-Length: ' . filesize($archivoTemporal));
    header('X-ASESCON-Total: ' . $total);
    header('X-ASESCON-Siguiente: ' . $siguiente);
    header('X-ASESCON-Generados: ' . $generados);
    header('X-ASESCON-Errores: ' . $errores);
    ignore_user_abort(true);
    readfile($archivoTemporal);
} catch (Throwable $error) {
    error_log('ASESCON ZIP masivo SIRE ' . $libro . ': ' . get_class($error));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'mensaje' => 'La descarga masiva no pudo completarse.']);
    }
} finally {
    if ($archivoTemporal !== null && is_file($archivoTemporal)) {
        unlink($archivoTemporal);
    }
}
