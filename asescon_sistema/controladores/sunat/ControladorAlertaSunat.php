<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/AlertaSunat.php';
final class ControladorAlertaSunat
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(string $estado = ''): array
    {
        asesconExigirPermiso($this->conexion, 'ALERTAS_SUNAT', 'ver');
        return ['filas' => (new AlertaSunat($this->conexion))->listar(asesconFiltroClientesSql($this->conexion, 'c'), $estado)];
    }

    public function procesar(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_alertas')) {
            asesconRedirigir('vistas/alertas/alertas_sunat.php', ['error' => 'csrf']);
        }
asesconExigirPermiso($this->conexion, 'ALERTAS_SUNAT', 'editar');
        (new AlertaSunat($this->conexion))->atender((int)($p['id_alerta'] ?? 0), asesconFiltroClientesSql($this->conexion, 'c'));
        asesconRedirigir('vistas/alertas/alertas_sunat.php', ['guardado' => 1]);
    }
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    (new ControladorAlertaSunat($conexion))->procesar($_POST);
}
