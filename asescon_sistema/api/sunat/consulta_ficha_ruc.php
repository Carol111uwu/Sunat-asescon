<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/respuesta_api.php';
asesconPrepararRespuestaApi();
require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../servicios/sunat/ServicioConsultaRuc.php';
header('Content-Type: application/json; charset=utf-8');

try {
    asesconExigirLogin();

    $input = $_POST;
    if (str_contains(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
        $j = json_decode((string)file_get_contents('php://input'), true);
        if (is_array($j)) {
            $input = $j;
        }
    }

    $tokenCsrf = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? ''));
    if (!asesconValidarCsrf($tokenCsrf, 'csrf_ficha_ruc')) {
        http_response_code(403);
        throw new RuntimeException('Token CSRF inválido o vencido.');
    }

    $idCliente = (int)($input['id_cliente'] ?? 0);
    $ruc = preg_replace('/\D+/', '', (string)($input['ruc'] ?? '')) ?? '';

    if ($idCliente > 0) {
        if (!asesconTienePermiso($conexion, 'CLIENTES', 'editar') || !asesconPuedeGestionarCliente($conexion, $idCliente)) {
            http_response_code(403);
            throw new RuntimeException('No tienes permiso para actualizar los datos tributarios de esta empresa.');
        }
        $stmt = $conexion->prepare('SELECT ruc FROM clientes WHERE id_cliente=? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('No se pudo consultar el cliente.');
        }
        $stmt->bind_param('i', $idCliente);
        $stmt->execute();
        $rucRegistrado = (string)($stmt->get_result()->fetch_assoc()['ruc'] ?? '');
        $stmt->close();
        if ($rucRegistrado === '' || $rucRegistrado !== $ruc) {
            http_response_code(422);
            throw new InvalidArgumentException('El RUC no corresponde a la empresa seleccionada.');
        }
    } elseif (!asesconTienePermiso($conexion, 'CLIENTES', 'crear')) {
        http_response_code(403);
        throw new RuntimeException('No tienes permiso para consultar datos SUNAT de un cliente nuevo.');
    }

    if (!preg_match('/^[0-9]{11}$/', $ruc)) {
        http_response_code(422);
        throw new InvalidArgumentException('El RUC debe contener exactamente 11 dígitos.');
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $publica = (new ServicioConsultaRuc())->consultarPorRuc($ruc);

    $data = [
        'ruc' => $ruc,
        'razon_social' => trim((string)($publica['razon_social'] ?? '')),
        'nombre_comercial' => trim((string)($publica['nombre_comercial'] ?? '')),
        'tipo_contribuyente' => trim((string)($publica['tipo_contribuyente'] ?? '')),
        'estado_ruc' => strtoupper(trim((string)($publica['estado_ruc'] ?? ''))),
        'condicion_ruc' => strtoupper(trim((string)($publica['condicion_ruc'] ?? ''))),
        'ubigeo' => trim((string)($publica['ubigeo'] ?? '')),
        'domicilio_fiscal' => trim((string)($publica['domicilio_fiscal'] ?? '')),
        'actividad_economica' => trim((string)($publica['actividad_economica'] ?? '')),
        'fecha_inscripcion' => trim((string)($publica['fecha_inscripcion'] ?? '')),
        'fecha_inicio_actividades' => trim((string)($publica['fecha_inicio_actividades'] ?? '')),
        'fuente' => trim((string)($publica['fuente'] ?? 'Consulta RUC')),
        'origen_tecnico' => trim((string)($publica['origen_tecnico'] ?? '')),
    ];

    if ($data['razon_social'] === '') {
        throw new RuntimeException('No fue posible obtener la razón social del RUC en este momento.');
    }

    asesconIniciarSesion();
    if (!isset($_SESSION['ficha_ruc_sunat']) || !is_array($_SESSION['ficha_ruc_sunat'])) {
        $_SESSION['ficha_ruc_sunat'] = [];
    }
    $_SESSION['ficha_ruc_sunat'][$ruc] = ['time' => time(),'data' => $data];

    echo json_encode(['ok' => true,'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        asesconIniciarSesion();
    }
    if (http_response_code() < 400) {
        http_response_code(502);
    }
    error_log('ASESCON consulta RUC: ' . get_class($e));
    $mensaje = $e instanceof ExcepcionSunat || $e instanceof InvalidArgumentException
        ? $e->getMessage()
        : 'No fue posible completar la consulta RUC. Revisa los registros PHP del servidor.';
    echo json_encode(['ok' => false,'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
