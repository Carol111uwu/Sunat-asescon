<?php
declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/RepositorioLecturaSire.php';
require_once __DIR__ . '/../../servicios/sunat/DocumentoComprobanteRvie.php';

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
$esCorreo = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$fallar = static function (int $codigo, string $mensaje) use ($esCorreo): never {
    http_response_code($codigo);
    if ($esCorreo) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $mensaje;
    }
    exit;
};

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    $fallar(405, 'Método no permitido.');
}
if (empty($_SESSION['id_usuario']) || !asesconTienePermiso($conexion, 'RVIE', 'ver')) {
    $fallar(403, 'Se requiere acceso al módulo RVIE.');
}
$entrada = $esCorreo ? $_POST : $_GET;
$idCliente = filter_var($entrada['cliente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$idDetalle = filter_var($entrada['detalle'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$idCliente || !$idDetalle || !asesconPuedeVerCliente($conexion, $idCliente)) {
    $fallar(404, 'El comprobante no está disponible.');
}

if ($esCorreo) {
    if (!asesconValidarCsrf((string)($_POST['csrf_token'] ?? ''), 'csrf_rvie_correo')) {
        $fallar(403, 'Recarga la página antes de enviar el correo.');
    }
    if (!asesconTienePermiso($conexion, 'RVIE', 'editar')) {
        $fallar(403, 'Tu cuenta no tiene permiso para enviar correos desde RVIE.');
    }
    $destinatario = trim((string)($_POST['destinatario'] ?? ''));
    if (strlen($destinatario) > 254 || !filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        $fallar(422, 'Ingresa una dirección de correo válida.');
    }
} else {
    $formato = $_GET['formato'] ?? 'pdf';
    if (!in_array($formato, ['pdf', 'xlsx'], true)) {
        $fallar(400, 'Formato no disponible.');
    }
}

session_write_close();
try {
    $registro = (new RepositorioLecturaSire($conexion))->obtenerComprobanteRvie($idCliente, $idDetalle);
    if (!$registro) {
        $fallar(404, 'El comprobante ya no está disponible. Vuelve a abrir el detalle de RVIE.');
    }
    $documento = new DocumentoComprobanteRvie($registro);
    if ($esCorreo) {
        if (!is_file(__DIR__ . '/../../vendor/autoload.php')) {
            $fallar(503, 'Faltan las dependencias de correo. Ejecuta composer install en asescon_sistema.');
        }
        require_once __DIR__ . '/../../servicios/comunicaciones/ServicioCorreoSmtp.php';
        (new ServicioCorreoSmtp())->enviar(
            $destinatario,
            'ASESCON | Registro de venta ' . $documento->numero(),
            "Hola,\n\nAdjuntamos el reporte del registro de venta consultado en SUNAT SIRE.\n\nASESCON",
            'ASESCON',
            '',
            [['bytes' => $documento->pdf(), 'nombre' => $documento->nombreArchivo() . '.pdf', 'tipo' => 'application/pdf']]
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'mensaje' => 'El servidor de correo aceptó el mensaje con el PDF adjunto.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $esPdf = $formato === 'pdf';
    $bytes = $esPdf ? $documento->pdf() : $documento->excel();
    $disposicion = $esPdf && ($_GET['vista'] ?? '') === 'previa' ? 'inline' : 'attachment';
    header('Content-Type: ' . ($esPdf ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
    header('Content-Disposition: ' . $disposicion . '; filename="' . $documento->nombreArchivo() . '.' . $formato . '"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
} catch (Throwable $error) {
    error_log('Comprobante RVIE: ' . get_class($error));
    $fallar(500, $esCorreo
        ? 'El servidor no confirmó el envío. Revisa la configuración SMTP antes de reintentar.'
        : 'No se pudo generar el archivo del comprobante.');
}
