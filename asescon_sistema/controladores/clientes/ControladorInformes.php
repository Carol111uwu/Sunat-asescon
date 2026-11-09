<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/clientes/Informe.php';

final class ControladorInformes
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(array $filtros = []): array
    {
        asesconExigirPermiso($this->conexion, 'INFORMES', 'ver');

        $filas = (new Informe($this->conexion))->listar(asesconFiltroClientesSql($this->conexion, 'c'), $filtros);
        $clientes = [];
        $r = asesconClientesVisibles($this->conexion, true);
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $clientes[] = $x;
            }
        }
        $responsables = [];
        $r = $this->conexion->query("SELECT u.id_usuario,u.usuario,r.codigo AS rol_codigo,r.nombre AS rol_nombre,COALESCE(NULLIF(e.nombre,''),u.usuario) AS nombre_mostrar FROM usuarios u INNER JOIN roles r ON r.id_rol=u.id_rol LEFT JOIN equipo e ON e.id_usuario=u.id_usuario WHERE u.estado=1 ORDER BY nombre_mostrar");
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $responsables[] = $x;
            }
        }
        $actor = asesconUsuarioActual($this->conexion);
        return compact('filas', 'clientes', 'responsables', 'actor');
    }

    public function crear(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_informes')) {
            asesconRedirigir('vistas/clientes/comunicacion_informes.php', ['seccion' => 'informes', 'error' => 'csrf']);
        }
        asesconExigirPermiso($this->conexion, 'INFORMES', 'crear');
        $c = (int)($p['id_cliente'] ?? 0);
        if (!asesconPuedeVerCliente($this->conexion, $c)) {
            exit('Cliente no autorizado.');
        }
        $responsable = (int)($p['id_responsable'] ?? 0);
        if ($responsable <= 0) {
            $responsable = null;
        }
        $firma = trim((string)($p['firma_informe'] ?? 'ESTUDIO_CONTABLE')) ?: 'ESTUDIO_CONTABLE';
        (new Informe($this->conexion))->crear($c, trim((string)($p['titulo'] ?? '')), trim((string)($p['tipo_informe'] ?? '')) ?: null, preg_replace('/\D+/', '', (string)($p['periodo'] ?? '')) ?: null, trim((string)($p['fecha_generacion'] ?? '')) ?: null, $responsable, $firma);
        asesconRedirigir('vistas/clientes/comunicacion_informes.php', ['seccion' => 'informes', 'guardado' => 1]);
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        (new ControladorInformes($conexion))->crear($_POST);
    }
asesconRedirigir('vistas/clientes/comunicacion_informes.php', ['seccion' => 'informes']);
}
