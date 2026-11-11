<?php
declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/RepositorioLecturaSire.php';
require_once __DIR__ . '/../../servicios/sunat/DocumentoComprobanteRce.php';

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

$responderError = static function (int $codigo, string $mensaje): never {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $responderError(405, 'Método no permitido.');
}
if (empty($_SESSION['id_usuario']) || !asesconTienePermiso($conexion, 'RCE', 'ver')) {
    $responderError(403, 'No tienes permiso para consultar RCE.');
}
if (!asesconValidarCsrf((string)($_POST['csrf_token'] ?? ''), 'csrf_rce_lote')) {
    $responderError(403, 'La sesión de descarga venció. Recarga la página.');
}
$accion = (string)($_POST['accion'] ?? '');
if (!in_array($accion, ['pdf', 'excel', 'correo'], true)) {
    $responderError(400, 'Selecciona una acción válida.');
}
if ($accion === 'correo' && !asesconTienePermiso($conexion, 'RCE', 'editar')) {
    $responderError(403, 'Tu usuario no tiene permiso para enviar correos.');
}

$seleccion = json_decode((string)($_POST['seleccion'] ?? ''), true);
if (!is_array($seleccion) || !array_is_list($seleccion) || count($seleccion) < 1 || count($seleccion) > 60) {
    $responderError(422, 'Selecciona entre 1 y 60 comprobantes de la página actual.');
}
$destinatario = '';
if ($accion === 'correo') {
    $destinatario = trim((string)($_POST['destinatario'] ?? ''));
    if (strlen($destinatario) > 254 || !filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        $responderError(422, 'Escribe un correo de destino válido.');
    }
}

try {
    $repositorio = new RepositorioLecturaSire($conexion);
    $registros = [];
    $vistos = [];
    $clientesPermitidos = [];
    foreach ($seleccion as $entrada) {
        if (!is_array($entrada)) {
            $responderError(422, 'La selección contiene un elemento inválido.');
        }
        $idCliente = filter_var($entrada['cliente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $idDetalle = filter_var($entrada['detalle'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$idCliente || !$idDetalle) {
            $responderError(422, 'La selección contiene un comprobante inválido.');
        }
        $clave = $idCliente . ':' . $idDetalle;
        if (isset($vistos[$clave])) {
            continue;
        }
        $vistos[$clave] = true;
        if (!array_key_exists($idCliente, $clientesPermitidos)) {
            $clientesPermitidos[$idCliente] = asesconPuedeVerCliente($conexion, $idCliente);
        }
        if (!$clientesPermitidos[$idCliente]) {
            $responderError(403, 'No tienes acceso a una empresa de la selección.');
        }
        $registro = $repositorio->obtenerComprobanteRce($idCliente, $idDetalle);
        if (!$registro) {
            $responderError(404, 'Un comprobante ya no está disponible. Actualiza RCE y selecciona nuevamente.');
        }
        $registro['id_cliente'] = $idCliente;
        $registro['id_detalle'] = $idDetalle;
        $registros[] = $registro;
    }
    session_write_close();

    if ($accion === 'excel') {
        require_once __DIR__ . '/../../servicios/sunat/ExportadorExcelSire.php';
        $filas = [];
        foreach ($registros as $registro) {
            $filas[] = [
                (string)($registro['empresa_asescon'] ?? ''),
                (string)($registro['ruc_empresa'] ?? ''),
                (string)($registro['razon_social_proveedor'] ?? ''),
                (string)($registro['ruc_proveedor'] ?? ''),
                (string)($registro['tipo_comprobante'] ?? ''),
                (string)($registro['serie'] ?? ''),
                (string)($registro['numero'] ?? ''),
                (string)($registro['fecha_emision'] ?? ''),
                (string)($registro['periodo'] ?? ''),
                (string)($registro['estado_registro'] ?? ''),
                (float)($registro['base_imponible'] ?? 0),
                (float)($registro['igv'] ?? 0),
                (float)($registro['importe_total'] ?? 0),
            ];
        }
        $bytes = (new ExportadorExcelSire())->crear(
            'RCE | Comprobantes seleccionados',
            'Reporte de compras · ASESCON',
            ['Empresa', 'RUC empresa', 'Proveedor', 'RUC proveedor', 'Tipo', 'Serie', 'Número',
             'Fecha de emisión', 'Período', 'Estado', 'Base imponible (S/)', 'IGV (S/)', 'Total (S/)'],
            $filas,
            ['text', 'text', 'text', 'text', 'text', 'text', 'text', 'date', 'text', 'text', 'money', 'money', 'money'],
            'Compras seleccionadas'
        );
        $nombre = 'RCE-seleccion-' . date('Ymd-His') . '.xlsx';
        $tipo = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    } else {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('No está disponible la extensión ZIP de PHP.');
        }
        $temporal = tempnam(sys_get_temp_dir(), 'asescon_rce_');
        if ($temporal === false) {
            throw new RuntimeException('No se pudo generar el archivo temporal de descarga.');
        }
        try {
            $zip = new ZipArchive();
            if ($zip->open($temporal, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
                throw new RuntimeException('No se pudo crear el ZIP de los comprobantes.');
            }
            foreach ($registros as $registro) {
                $documento = new DocumentoComprobanteRce($registro);
                $archivo = $documento->nombreArchivo() . '-'
                    . (int)$registro['id_cliente'] . '-' . (int)$registro['id_detalle'] . '.pdf';
                $zip->addFromString($archivo, $documento->pdf());
            }
            if (!$zip->close()) {
                throw new RuntimeException('No se pudo finalizar el ZIP.');
            }
            $bytes = file_get_contents($temporal);
            if ($bytes === false) {
                throw new RuntimeException('No se pudo leer el ZIP.');
            }
        } finally {
            if (is_file($temporal)) {
                unlink($temporal);
            }
        }
        $nombre = 'RCE-PDF-seleccion-' . date('Ymd-His') . '.zip';
        $tipo = 'application/zip';
    }

    if ($accion === 'correo') {
        if (!is_file(__DIR__ . '/../../vendor/autoload.php')) {
            $responderError(503, 'Falta instalar las dependencias de correo.');
        }
        require_once __DIR__ . '/../../servicios/comunicaciones/ServicioCorreoSmtp.php';
        (new ServicioCorreoSmtp())->enviar(
            $destinatario,
            'ASESCON | ' . count($registros) . ' registros de compra RCE',
            "Hola,\n\nAdjuntamos los reportes informativos de los registros de compras RCE seleccionados.\nPara efectos tributarios, verifica los comprobantes originales.\n\nASESCON",
            'ASESCON',
            '',
            [['bytes' => $bytes, 'nombre' => $nombre, 'tipo' => $tipo]]
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'mensaje' => 'El servidor de correo aceptó el ZIP de los comprobantes seleccionados.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: ' . $tipo);
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
} catch (Throwable $error) {
    error_log('Lote RCE: ' . get_class($error));
    $responderError(500, 'No se pudo preparar la descarga o el envío. Comprueba el servicio y vuelve a intentar.');
}
