<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/clientes/Honorario.php';
require_once __DIR__ . '/../../servicios/textos/ArchivoTextos.php';
final class ControladorHonorarios
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(): array
    {
        asesconExigirPermiso($this->conexion, 'HONORARIOS', 'ver');
        $filas = (new Honorario($this->conexion))->listar(asesconFiltroClientesSql($this->conexion, 'c'));
        $clientes = [];
        $r = asesconClientesVisibles($this->conexion, true);
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $clientes[] = $x;
            }
        }
return compact('filas', 'clientes');
    }

    public function procesar(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_honorarios')) {
            asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'honorarios', 'error' => 'csrf']);
        }
$a = (string)($p['accion'] ?? '');
        $m = new Honorario($this->conexion);
        if ($a === 'crear') {
            asesconExigirPermiso($this->conexion, 'HONORARIOS', 'crear');
            $c = (int)($p['id_cliente'] ?? 0);
            if (!asesconPuedeGestionarCliente($this->conexion, $c)) {
                exit('Cliente no autorizado.');
            }
$m->crear($c, trim((string)$p['concepto']), (float)$p['monto'], trim((string)($p['fecha_emision'] ?? '')) ?: null, trim((string)($p['fecha_vencimiento'] ?? '')) ?: null, trim((string)($p['observaciones'] ?? '')) ?: null);
            asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'honorarios', 'guardado' => 1]);
        }
if (in_array($a,['pagar','reabrir','editar','eliminar','archivar','restaurar'],true)) {
            asesconExigirPermiso($this->conexion, 'HONORARIOS', $a==='eliminar'?'eliminar':'editar');
            $id=(int)($p['id_honorario']??0);
            if($id<1)throw new DomainException('Honorario inválido.');
            $filtro=asesconFiltroClientesSql($this->conexion,'c');
            if(in_array($a,['archivar','restaurar'],true)){
                $s=$this->conexion->prepare("SELECT h.id_honorario FROM honorarios h INNER JOIN clientes c ON c.id_cliente=h.id_cliente WHERE h.id_honorario=? AND ({$filtro}) LIMIT 1");
                $s->bind_param('i',$id);$s->execute();$existe=(bool)$s->get_result()->fetch_assoc();$s->close();
                if(!$existe)throw new DomainException('Honorario no disponible.');
                (new ArchivoTextos((int)asesconUsuarioActual($this->conexion)['id_usuario']))->cambiar('honorario',$id,$a==='archivar');
            }elseif($a==='pagar')$m->pagar($id,$filtro);
            elseif($a==='reabrir')$m->reabrir($id,$filtro);
            elseif($a==='eliminar')$m->eliminar($id,$filtro);
            else{
                $concepto=trim((string)($p['concepto']??''));$monto=(float)($p['monto']??0);
                if($concepto===''||strlen($concepto)>180||!is_finite($monto)||$monto<0)throw new DomainException('Concepto o monto inválidos.');
                $m->editar($id,$filtro,$concepto,$monto,trim((string)($p['fecha_emision']??''))?:null,trim((string)($p['fecha_vencimiento']??''))?:null,trim((string)($p['observaciones']??''))?:null);
            }
            asesconRegistrarAuditoria($this->conexion,strtoupper($a).'_HONORARIO','HONORARIOS',null,'honorarios',(string)$id,'Corrección de honorario; sin conservar el contenido escrito');
            asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'honorarios', 'ver'=>$a==='restaurar'?'archivados':'visibles', 'pagado' => 1]);
        }
asesconRedirigir('vistas/clientes/servicios_cobros.php', ['seccion' => 'honorarios']);
    }
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    try{(new ControladorHonorarios($conexion))->procesar($_POST);}
    catch(Throwable $e){error_log('Honorarios: '.get_class($e));$_SESSION['error_honorarios']='No se pudo actualizar el honorario. Verifica los datos o el permiso.';asesconRedirigir('vistas/clientes/servicios_cobros.php',['seccion'=>'honorarios']);}
}
