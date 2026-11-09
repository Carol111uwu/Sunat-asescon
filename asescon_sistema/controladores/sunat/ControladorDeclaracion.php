<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/DeclaracionSunat.php';
require_once __DIR__ . '/../../servicios/sunat/ServicioControlDeclaracionesSunat.php';
final class ControladorDeclaracion
{
    public function __construct(private mysqli $conexion)
    {
    }
    public function datosVista(string $periodo, string $estado = '', string $buscar = ''): array
    {
        asesconExigirPermiso($this->conexion, 'DECLARACIONES', 'ver');
        $f = asesconFiltroClientesSql($this->conexion, 'c');
        $m = new DeclaracionSunat($this->conexion);
        return ['filas' => $m->listar($f, $periodo, $estado, $buscar),'resumen' => $m->resumen($f, $periodo)];
    }
    public function generar(array $post): void
    {
        if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_declaraciones')) {
            asesconRedirigir('vistas/sunat/control_declaraciones.php', ['error' => 'csrf']);
        }
asesconExigirPermiso($this->conexion, 'DECLARACIONES', 'ejecutar');
        $p = preg_replace('/\D+/', '', (string)($post['periodo'] ?? '')) ?? '';
        $cliente = filter_var($post['id_cliente'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        try {
            if ($cliente > 0 && !asesconPuedeGestionarCliente($this->conexion, $cliente)) throw new DomainException('Cliente no autorizado.');
            $filtro = asesconFiltroClientesSql($this->conexion, 'c');
            if ($cliente > 0) $filtro .= ' AND c.id_cliente=' . $cliente;
            $n = (new DeclaracionSunat($this->conexion))->generarPeriodo($filtro, $p);
            asesconRegistrarAuditoria($this->conexion, 'GENERAR_CONTROL', 'DECLARACIONES', null, 'declaraciones_sunat', $p, 'Filas nuevas: ' . $n);
            asesconRedirigir('vistas/sunat/control_declaraciones.php', ['periodo' => $p,'cliente'=>$cliente,'generado' => $n]);
        } catch (Throwable $e) {
            error_log('[ASESCON Generar Control] '.get_class($e));
            asesconRedirigir('vistas/sunat/control_declaraciones.php', ['periodo' => $p,'cliente'=>$cliente,'error' => 'generar']);
        }
    }

    public function sincronizar(array $post): void
    {
        if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_declaraciones')) {
            asesconRedirigir('vistas/sunat/control_declaraciones.php', ['error'=>'csrf']);
        }
        asesconExigirPermiso($this->conexion, 'DECLARACIONES', 'ejecutar');
        $cliente = filter_var($post['id_cliente'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $periodo = preg_replace('/\D+/', '', (string)($post['periodo'] ?? '')) ?? '';
        try {
            if ($cliente <= 0 || !asesconPuedeGestionarCliente($this->conexion, $cliente)) {
                throw new DomainException('Selecciona un cliente autorizado.');
            }
            $resultado = (new ServicioControlDeclaracionesSunat($this->conexion))->sincronizar($cliente, $periodo);
            asesconRegistrarAuditoria($this->conexion, 'CONSULTAR_CONTROL_SOL', 'DECLARACIONES', $cliente,
                'declaraciones_sunat', $periodo, 'Coincidencias: '.$resultado['coincidencias'].'; no vinculados: '.$resultado['sin_vincular']);
            asesconRedirigir('vistas/sunat/control_declaraciones.php', [
                'periodo'=>$periodo, 'cliente'=>$cliente, 'consultado'=>1,
                'coincidencias'=>$resultado['coincidencias'], 'sin_vincular'=>$resultado['sin_vincular'],
                'oficiales'=>$resultado['oficiales'],
            ]);
        } catch (Throwable $e) {
            error_log('[ASESCON Control Declaraciones SOL] '.get_class($e).': '.$e->getMessage());
            asesconRedirigir('vistas/sunat/control_declaraciones.php', [
                'periodo'=>$periodo, 'cliente'=>$cliente, 'error'=>'consulta_sol',
            ]);
        }
    }
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    $c = new ControladorDeclaracion($conexion);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'generar') {
        $c->generar($_POST);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'sincronizar') {
        $c->sincronizar($_POST);
    }
asesconRedirigir('vistas/sunat/control_declaraciones.php');
}
