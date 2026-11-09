<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/VencimientoSunat.php';
final class ControladorVencimientoSunat
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(string $estado = ''): array
    {
        asesconExigirPermiso($this->conexion, 'VENCIMIENTOS_SUNAT', 'ver');
        return ['filas' => (new VencimientoSunat($this->conexion))->listar(asesconFiltroClientesSql($this->conexion, 'c'), $estado)];
    }

    public function procesar(array $p): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            asesconRedirigir('vistas/vencimientos/vencimientos_sunat.php', ['error'=>'metodo']);
        }
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_vencimientos')) {
            asesconRedirigir('vistas/vencimientos/vencimientos_sunat.php', ['error' => 'csrf']);
        }
asesconExigirPermiso($this->conexion, 'VENCIMIENTOS_SUNAT', 'editar');
        try {
            (new VencimientoSunat($this->conexion))->actualizarEstado(
                (int)($p['id_vencimiento'] ?? 0), strtoupper(trim((string)($p['estado'] ?? ''))),
                asesconFiltroClientesSql($this->conexion, 'c')
            );
            asesconRedirigir('vistas/vencimientos/vencimientos_sunat.php', ['guardado' => 1]);
        } catch (Throwable $e) {
            error_log('[ASESCON Vencimientos] '.get_class($e));
            asesconRedirigir('vistas/vencimientos/vencimientos_sunat.php', ['error' => 'actualizar']);
        }
    }
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    (new ControladorVencimientoSunat($conexion))->procesar($_POST);
}
