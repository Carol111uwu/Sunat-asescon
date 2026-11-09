<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/clientes/Cotizacion.php';

final class ControladorCotizaciones
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(): array
    {
        asesconExigirPermiso($this->conexion, 'COTIZACIONES', 'ver');

        $m = new Cotizacion($this->conexion);
        $filas = $m->listar(asesconFiltroClientesSql($this->conexion, 'c'));
        $itemsCotizacion = $m->listarItems($filas);
        $servicios = $m->listarServicios();
        $prospectos = $m->listarProspectos();
        $clientes = [];
        $r = asesconClientesVisibles($this->conexion, true);
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $clientes[] = $x;
            }
        }
        $codigoSugerido = $m->siguienteCodigo();
        return compact('filas', 'servicios', 'prospectos', 'clientes', 'codigoSugerido','itemsCotizacion');
    }

    private function responderAjax(array $data): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true,'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function crearCotizacion(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_cotizaciones')) {
            asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'cotizaciones', 'error' => 'csrf']);
        }
        asesconExigirPermiso($this->conexion, 'COTIZACIONES', 'crear');

        $idCliente = null;
        $idProspecto = null;
        $destino = trim((string)($p['prospecto'] ?? ''));
        if (preg_match('/^C:(\d+)$/', $destino, $m)) {
            $idCliente = (int)$m[1];
            if (!asesconPuedeVerCliente($this->conexion, $idCliente)) {
                exit('Cliente no autorizado.');
            }
        } elseif (preg_match('/^P:(\d+)$/', $destino, $m)) {
            $idProspecto = (int)$m[1];
        } else {
            asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'cotizaciones', 'error' => 'prospecto']);
        }

        $descs = $p['item_descripcion'] ?? [];
        $cants = $p['item_cantidad'] ?? [];
        $precios = $p['item_precio'] ?? [];
        $tipos = $p['item_tipo'] ?? [];
        $servIds = $p['item_servicio'] ?? [];
        $items = [];
        if (is_array($descs)) {
            foreach ($descs as $i => $desc) {
                $desc = trim((string)$desc);
                $cant = max(.01, (float)($cants[$i] ?? 1));
                $precio = max(0, (float)($precios[$i] ?? 0));
                if ($desc === '' || $precio < 0) {
                    continue;
                }
$sid = (int)($servIds[$i] ?? 0);
                $items[] = ['tipo' => in_array((string)($tipos[$i] ?? ''), ['CATALOGO','LIBRE'], true) ? (string)$tipos[$i] : 'LIBRE','id_servicio' => $sid > 0 ? $sid : null,'descripcion' => $desc,'cantidad' => $cant,'precio_unitario' => $precio];
            }
        }
        if ($items === []) {
            asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'cotizaciones', 'error' => 'items']);
        }

        (new Cotizacion($this->conexion))->crearCotizacion(
            $idCliente,
            $idProspecto,
            trim((string)($p['codigo'] ?? '')) ?: null,
            trim((string)($p['fecha_emision'] ?? date('Y-m-d'))),
            trim((string)($p['valida_hasta'] ?? '')) ?: null,
            strtoupper(trim((string)($p['moneda'] ?? 'PEN'))),
            trim((string)($p['notas_condiciones'] ?? '')) ?: null,
            $items
        );
        asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'cotizaciones', 'guardado' => 1]);
    }

    public function crearServicio(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_cotizaciones')) {
            asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'cotizaciones', 'error' => 'csrf']);
        }
        asesconExigirPermiso($this->conexion, 'COTIZACIONES', 'crear');
        $nombre = trim((string)($p['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('Nombre de servicio obligatorio.');
        }
        $descripcion = trim((string)($p['descripcion_servicio'] ?? '')) ?: null;
        $precio = max(0, (float)($p['precio_referencial'] ?? 0));
        $moneda = strtoupper(trim((string)($p['moneda'] ?? 'PEN')));
        $id = (new Cotizacion($this->conexion))->crearServicio($nombre, $descripcion, $precio, $moneda);
        if ((string)($p['ajax'] ?? '') === '1') {
            $this->responderAjax(['id_servicio' => $id,'nombre' => $nombre,'descripcion' => $descripcion,'precio_referencial' => $precio,'moneda' => $moneda]);
        }
        asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'cotizaciones', 'servicio_guardado' => 1]);
    }

    public function editarCotizacion(array $p):void
    {
        if(!asesconValidarCsrf((string)($p['csrf_token']??''),'csrf_cotizaciones'))throw new DomainException('Sesión caducada.');
        asesconExigirPermiso($this->conexion,'COTIZACIONES','editar');
        $id=(int)($p['id_cotizacion']??0);if($id<1)throw new DomainException('Cotización inválida.');
        $destino=(string)($p['prospecto']??'');$idCliente=null;$idProspecto=null;
        if(preg_match('/^C:(\d+)$/',$destino,$m)){$idCliente=(int)$m[1];if(!asesconPuedeVerCliente($this->conexion,$idCliente))throw new DomainException('Cliente no autorizado.');}
        elseif(preg_match('/^P:(\d+)$/',$destino,$m))$idProspecto=(int)$m[1];
        else throw new DomainException('Selecciona cliente o prospecto.');
        $items=[];$descs=$p['item_descripcion']??[];
        if(!is_array($descs))throw new DomainException('Ítems inválidos.');
        foreach($descs as $n=>$d){
            $d=trim((string)$d);$cant=(float)($p['item_cantidad'][$n]??0);$precio=(float)($p['item_precio'][$n]??0);
            if($d===''||$cant<=0||$precio<0||!is_finite($cant)||!is_finite($precio))throw new DomainException('Revisa los ítems.');
            $sid=(int)($p['item_servicio'][$n]??0);$tipo=(string)($p['item_tipo'][$n]??'LIBRE');
            $items[]=['descripcion'=>$d,'cantidad'=>$cant,'precio_unitario'=>$precio,'id_servicio'=>$sid>0?$sid:null,'tipo'=>in_array($tipo,['CATALOGO','LIBRE'],true)?$tipo:'LIBRE'];
        }
        $codigo=trim((string)($p['codigo']??''));$fecha=trim((string)($p['fecha_emision']??''));$hasta=trim((string)($p['valida_hasta']??''))?:null;
        $moneda=strtoupper(trim((string)($p['moneda']??'PEN')));if($codigo===''||$fecha===''||!in_array($moneda,['PEN','USD'],true))throw new DomainException('Código, fecha o moneda inválidos.');
        (new Cotizacion($this->conexion))->editarCotizacion($id,asesconFiltroClientesSql($this->conexion,'c'),$idCliente,$idProspecto,$codigo,$fecha,$hasta,$moneda,trim((string)($p['notas_condiciones']??''))?:null,$items);
        asesconRegistrarAuditoria($this->conexion,'EDITAR_COTIZACION','COTIZACIONES',$idCliente,'cotizaciones',(string)$id,'Corrección de cotización sin copiar contenido');
        asesconRedirigir('vistas/clientes/servicios_cobros.php',['seccion'=>'cotizaciones','actualizado'=>1]);
    }

    public function eliminarCotizacion(array $p):void
    {
        if(!asesconValidarCsrf((string)($p['csrf_token']??''),'csrf_cotizaciones'))throw new DomainException('Sesión caducada.');
        asesconExigirPermiso($this->conexion,'COTIZACIONES','eliminar');
        $id=(int)($p['id_cotizacion']??0);if($id<1)throw new DomainException('Cotización inválida.');
        (new Cotizacion($this->conexion))->eliminarCotizacion($id,asesconFiltroClientesSql($this->conexion,'c'));
        asesconRegistrarAuditoria($this->conexion,'ELIMINAR_COTIZACION','COTIZACIONES',null,'cotizaciones',(string)$id,'Cotización interna eliminada');
        asesconRedirigir('vistas/clientes/servicios_cobros.php',['seccion'=>'cotizaciones','actualizado'=>1]);
    }

    public function crearProspecto(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_cotizaciones')) {
            asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'cotizaciones', 'error' => 'csrf']);
        }
        asesconExigirPermiso($this->conexion, 'COTIZACIONES', 'crear');
        $nombre = trim((string)($p['nombre_prospecto'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('Nombre de prospecto obligatorio.');
        }
        $ruc = preg_replace('/\D+/', '', (string)($p['ruc_prospecto'] ?? '')) ?? '';
        if ($ruc !== '' && !preg_match('/^\d{11}$/', $ruc)) {
            throw new InvalidArgumentException('RUC de prospecto inválido.');
        }
        $contacto = trim((string)($p['contacto_prospecto'] ?? '')) ?: null;
        $telefono = trim((string)($p['telefono_prospecto'] ?? '')) ?: null;
        $correo = trim((string)($p['correo_prospecto'] ?? '')) ?: null;
        $id = (new Cotizacion($this->conexion))->crearProspecto($nombre, $ruc ?: null, $contacto, $telefono, $correo);
        if ((string)($p['ajax'] ?? '') === '1') {
            $this->responderAjax(['id_prospecto' => $id,'nombre' => $nombre,'ruc' => $ruc]);
        }
        asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'cotizaciones', 'prospecto_guardado' => 1]);
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    $c = new ControladorCotizaciones($conexion);
    $accion = (string)($_POST['accion'] ?? 'crear_cotizacion');
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear_servicio') {
            $c->crearServicio($_POST);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear_prospecto') {
            $c->crearProspecto($_POST);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear_cotizacion') {
            $c->crearCotizacion($_POST);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'editar_cotizacion') {
            $c->editarCotizacion($_POST);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'eliminar_cotizacion') {
            $c->eliminarCotizacion($_POST);
        }
    } catch (Throwable $e) {
        if ((string)($_POST['ajax'] ?? '') === '1') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(422);
            echo json_encode(['ok' => false,'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'cotizaciones', 'error' => 'guardar']);
    }
    asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'cotizaciones']);
}
