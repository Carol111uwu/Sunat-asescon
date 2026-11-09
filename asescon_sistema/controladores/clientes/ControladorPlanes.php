<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/clientes/PlanServicio.php';
final class ControladorPlanes
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(): array
    {
        asesconExigirPermiso($this->conexion, 'PLANES', 'ver');
        return ['filas' => (new PlanServicio($this->conexion))->listar()];
    }

    public function crear(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_planes')) {
            asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'planes', 'error' => 'csrf']);
        }
        $accion=(string)($p['accion']??'crear');
        asesconExigirPermiso($this->conexion, 'PLANES', $accion==='crear'?'crear':($accion==='eliminar'?'eliminar':'editar'));
        $modelo=new PlanServicio($this->conexion);
        $id=(int)($p['id_plan']??0);
        if($accion==='eliminar' || $accion==='archivar' || $accion==='restaurar' || $accion==='editar'){
            if($id<1)throw new DomainException('Plan inválido.');
            if($accion==='eliminar')$modelo->eliminar($id);
            elseif($accion==='archivar' || $accion==='restaurar')$modelo->estado($id,$accion==='archivar'?'INACTIVO':'ACTIVO');
            else{
                [$nombre,$descripcion,$precio,$periodicidad]=$this->campos($p);
                $modelo->editar($id,$nombre,$descripcion,$precio,$periodicidad);
            }
            asesconRegistrarAuditoria($this->conexion,strtoupper($accion).'_PLAN','PLANES',null,'planes_servicio',(string)$id,'Gestión de plan comercial');
        }elseif($accion==='crear'){
            [$nombre,$descripcion,$precio,$periodicidad]=$this->campos($p);
            $modelo->crear($nombre,$descripcion,$precio,$periodicidad);
        }else throw new DomainException('Acción de plan no permitida.');
        asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'planes', 'guardado' => 1]);
    }

    private function campos(array $p): array
    {
        $nombre=trim((string)($p['nombre']??''));$descripcion=trim((string)($p['descripcion']??''))?:null;
        $precio=(float)($p['precio']??0);$per=strtoupper((string)($p['periodicidad']??'MENSUAL'));
        if($nombre===''||strlen($nombre)>180||!is_finite($precio)||$precio<0||!in_array($per,['MENSUAL','TRIMESTRAL','ANUAL','UNICO'],true))throw new DomainException('Revisa nombre, precio y periodicidad del plan.');
        return [$nombre,$descripcion,$precio,$per];
    }
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    try{(new ControladorPlanes($conexion))->crear($_POST);}
    catch(Throwable $e){error_log('Plan: '.get_class($e));$_SESSION['error_planes']='No se pudo completar la acción. Si el plan está en uso, primero desvincúlalo.';asesconRedirigir('vistas/clientes/servicios_cobros.php',['seccion'=>'planes']);}
}
