<?php
declare(strict_types=1);

require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';
require_once __DIR__.'/../../servicios/sunafil/ServicioColaBuzonSunafil.php';
require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';

asesconExigirLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    asesconExigirPermiso($conexion, 'BUZON_SUNAFIL', 'ver');

    $id = (int)($_POST['id_documento'] ?? $_GET['id_documento'] ?? 0);

    $repo = new RepositorioDocumentoBuzon($conexion);

    $d = $repo->obtener(
        'SUNAFIL',
        $id,
        asesconFiltroClientesSql($conexion, 'c')
    );

    if (!$d) {
        throw new DomainException('Documento no autorizado.');
    }

    $archivo = (new ServicioArchivosPrivados())->resolver(
        (string)($d['archivo'] ?? ''),
        'SUNAFIL'
    );

    if ($archivo) {
        echo json_encode([
            'ok' => true,
            'estado' => 'DISPONIBLE',
            'url' => asesconUrl('api/buzones/archivo_documento.php')
                .'?buzon=SUNAFIL&id_documento='.$id.'&descargar=1'
        ]);
        exit;
    }

    $cola = new ServicioColaBuzonSunafil();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        asesconExigirPermiso($conexion, 'BUZON_SUNAFIL', 'editar');

        if (!asesconValidarCsrf(
            (string)($_POST['csrf_token'] ?? ''),
            'csrf_buzon_sunafil'
        )) {
            throw new DomainException('Solicitud no válida.');
        }

        $estado = $cola->solicitar(
            (int)$d['id_cliente'],
            (int)$_SESSION['id_usuario'],
            $id
        );
    } else {
        $estado = $cola->obtenerEstadoDocumento((int)$d['id_cliente'], $id);

        if ((int)($estado['id_documento'] ?? 0) !== $id) {
            throw new DomainException('No hay solicitud para este documento.');
        }
    }

    echo json_encode([
        'ok' => true,
        'estado' => $estado['estado'] ?? '',
        'mensaje' => $estado['mensaje'] ?? ''
    ]);

} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'mensaje' => $e instanceof DomainException
            ? $e->getMessage()
            : 'No se pudo procesar el PDF.'
    ]);
}