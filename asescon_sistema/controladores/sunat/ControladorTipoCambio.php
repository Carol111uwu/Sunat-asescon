<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/TipoCambio.php';
final class ControladorTipoCambio
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(): array
    {
        asesconExigirPermiso($this->conexion, 'TIPO_CAMBIO', 'ver');
        return ['filas' => (new TipoCambio($this->conexion))->listar()];
    }

    public function guardar(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_tipo_cambio')) {
            asesconRedirigir('vistas/sunat/tipo_cambio.php', ['error' => 'csrf']);
        }
asesconExigirPermiso($this->conexion, 'TIPO_CAMBIO', 'editar');
        $fecha = trim((string)($p['fecha'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new InvalidArgumentException('Fecha inválida.');
        }(new TipoCambio($this->conexion))->guardar($fecha, (float)($p['compra'] ?? 0), (float)($p['venta'] ?? 0), 'REGISTRO_ASESCON');
        asesconRedirigir('vistas/sunat/tipo_cambio.php', ['guardado' => 1]);
    }
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    (new ControladorTipoCambio($conexion))->guardar($_POST);
}
