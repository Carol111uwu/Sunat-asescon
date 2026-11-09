<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/respuesta_api.php';
asesconPrepararRespuestaApi();
require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../servicios/clientes/BusquedaClientes.php';

try {
    asesconExigirLogin();
    if (!asesconTienePermiso($conexion, 'CLIENTES', 'ver')) {
        http_response_code(403);
        throw new RuntimeException('No tienes permiso para buscar clientes.');
    }

    $q = BusquedaClientes::normalizar((string)($_GET['q'] ?? ''));
    $listar = (string)($_GET['listar'] ?? '') === '1' && $q === '';
    if (!$listar && strlen($q) < 2) {
        echo json_encode(['ok' => true, 'data' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $filtro = asesconFiltroClientesSql($conexion, 'c');
    $busqueda = $listar ? ['sql' => '1=1', 'parametros' => []] : BusquedaClientes::condicionSql($q, ['c.ruc','c.razon_social','c.nombre_contacto','c.nombre_comercial','c.celular','c.correo']);
    $condicion = $busqueda['sql'];
    $sql = "SELECT c.id_cliente,c.ruc,c.razon_social,c.nombre_contacto,c.nombre_comercial,c.estado,c.estado_ruc,
                   c.celular AS telefono,c.correo AS correo
            FROM clientes c
            WHERE {$filtro}
              AND ({$condicion})
            ORDER BY (c.estado='ACTIVO') DESC,c.razon_social
            LIMIT " . ($listar ? '500' : '80');

    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la búsqueda.');
    }
    $parametros = $busqueda['parametros'];
    if ($parametros !== []) { $stmt->bind_param(str_repeat('s', count($parametros)), ...$parametros); }
    if (!$stmt->execute()) { throw new RuntimeException('No se pudo ejecutar la búsqueda.'); }
    $res = $stmt->get_result();
    $rows = [];
    while ($fila = $res->fetch_assoc()) {
        $rows[] = $fila;
    }
    $stmt->close();

    echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    error_log('ASESCON búsqueda de clientes: ' . get_class($e));
    $mensaje = http_response_code() === 403
        ? $e->getMessage()
        : 'No se pudo completar la búsqueda de clientes.';
    echo json_encode(['ok' => false, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
