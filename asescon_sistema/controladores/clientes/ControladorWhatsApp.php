<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/clientes/RecordatorioWhatsApp.php';
require_once __DIR__ . '/../../modelos/clientes/PlantillaWhatsApp.php';
require_once __DIR__ . '/../../servicios/textos/ArchivoTextos.php';

final class ControladorWhatsApp
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(): array
    {
        asesconExigirPermiso($this->conexion, 'WHATSAPP', 'ver');

        $f = asesconFiltroClientesSql($this->conexion, 'c');
        $model = new RecordatorioWhatsApp($this->conexion);
        $filas = $model->listar($f);
        $programados = $model->programados($f);
        $historial = $model->historial($f);
        $plantillas = (new PlantillaWhatsApp($this->conexion))->listar();
        $archivo=new ArchivoTextos((int)asesconUsuarioActual($this->conexion)['id_usuario']);
        $ocultos=$archivo->listar();$archivados=['recordatorios'=>[],'plantillas'=>[]];
        $idsArchivados=[];
        foreach(array_keys($ocultos) as $clave){
            if(str_starts_with((string)$clave,'recordatorio:'))$idsArchivados[]=(int)substr((string)$clave,strlen('recordatorio:'));
        }
        $archivados['recordatorios']=$model->archivados($f,$idsArchivados);
        $visibles=[];
        foreach($filas as $r){if(!isset($ocultos['recordatorio:'.$r['id_recordatorio']]))$visibles[]=$r;}
        $filas=$visibles;
        $programados=array_values(array_filter($programados,static fn($r)=>!isset($ocultos['recordatorio:'.$r['id_recordatorio']])));
        $historial=array_values(array_filter($historial,static fn($r)=>!isset($ocultos['recordatorio:'.$r['id_recordatorio']])));
        $visibles=[];
        foreach($plantillas as $r){if(isset($ocultos['plantilla:'.$r['id_plantilla']]))$archivados['plantillas'][]=$r;else $visibles[]=$r;}
        $plantillas=$visibles;
        $clientes = [];
        $r = asesconClientesVisibles($this->conexion, true);
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $clientes[] = $x;
            }
        }
        return compact('filas', 'programados', 'historial', 'plantillas', 'clientes','archivados');
    }

    public function crearRecordatorio(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_whatsapp')) {
            asesconRedirigir('vistas/clientes/comunicacion_informes.php', ['seccion' => 'recordatorios', 'error' => 'csrf']);
        }
        asesconExigirPermiso($this->conexion, 'WHATSAPP', 'crear');
        $id = (int)($p['id_cliente'] ?? 0);
        if (!asesconPuedeVerCliente($this->conexion, $id)) {
            http_response_code(403);
            exit('Cliente no autorizado.');
        }
        $tel = preg_replace('/\D+/', '', (string)($p['telefono'] ?? '')) ?? '';
        $mensaje = trim((string)($p['mensaje'] ?? ''));
        if ($mensaje === '') {
            asesconRedirigir('vistas/clientes/comunicacion_informes.php', ['seccion' => 'recordatorios', 'error' => 'mensaje']);
        }
        $tipo = trim((string)($p['tipo'] ?? 'VENCIMIENTO'));
        $fecha = trim((string)($p['fecha_programada'] ?? '')) ?: null;
        (new RecordatorioWhatsApp($this->conexion))->crear($id, (int)$_SESSION['id_usuario'], $tel, $mensaje, $tipo, $fecha);
        asesconRegistrarAuditoria($this->conexion, 'CREAR_RECORDATORIO', 'WHATSAPP', $id, 'recordatorios_whatsapp', null, $tipo);
        asesconRedirigir('vistas/clientes/comunicacion_informes.php', ['seccion' => 'recordatorios', 'guardado' => 1]);
    }

    public function crearPlantilla(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_whatsapp')) {
            asesconRedirigir('vistas/clientes/comunicacion_informes.php', ['seccion' => 'recordatorios', 'tab' => 'plantillas','error' => 'csrf']);
        }
        asesconExigirPermiso($this->conexion, 'WHATSAPP', 'crear');
        $nombre = trim((string)($p['nombre'] ?? ''));
        $mensaje = trim((string)($p['mensaje'] ?? ''));
        $categoria = strtoupper(trim((string)($p['categoria'] ?? '')));
        $permitidas = ['VENCIMIENTOS','PENDIENTES','DIAS_FESTIVOS','DIA_TRABAJADOR','RECORDATORIO_VENCIMIENTO','SALUDO_NAVIDAD','NAVIDAD','TIP_SUNAT'];
        if ($nombre === '' || $mensaje === '' || !in_array($categoria, $permitidas, true)) {
            asesconRedirigir('vistas/clientes/comunicacion_informes.php', ['seccion' => 'recordatorios', 'tab' => 'plantillas','error' => 'plantilla']);
        }
        (new PlantillaWhatsApp($this->conexion))->crear($nombre, $categoria, $mensaje, (int)$_SESSION['id_usuario']);
        asesconRegistrarAuditoria($this->conexion, 'CREAR_PLANTILLA', 'WHATSAPP', null, 'plantillas_whatsapp', null, $categoria . ' · ' . $nombre);
        asesconRedirigir('vistas/clientes/comunicacion_informes.php', ['seccion' => 'recordatorios', 'tab' => 'plantillas','plantilla_guardada' => 1]);
    }

    public function marcarEnviado(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_whatsapp')) {
            asesconRedirigir('vistas/clientes/comunicacion_informes.php', ['seccion' => 'recordatorios', 'tab' => 'historial','error' => 'csrf']);
        }
        asesconExigirPermiso($this->conexion, 'WHATSAPP', 'editar');
        $id = (int)($p['id_recordatorio'] ?? 0);
        $f = asesconFiltroClientesSql($this->conexion, 'c');
        if ($id > 0) {
            (new RecordatorioWhatsApp($this->conexion))->marcarEnviado($id, $f);
        }
        asesconRedirigir('vistas/clientes/comunicacion_informes.php', ['seccion' => 'recordatorios', 'tab' => 'historial','enviado' => 1]);
    }

    public function modificar(array $p,string $accion):void
    {
        if(!asesconValidarCsrf((string)($p['csrf_token']??''),'csrf_whatsapp'))throw new DomainException('Sesión caducada. Recarga la página.');
        $partes=explode('_',$accion,2);$operacion=$partes[0]??'';$tipo=$partes[1]??'';
        if(!in_array($operacion,['editar','eliminar','archivar','restaurar','reabrir'],true)||!in_array($tipo,['recordatorio','plantilla'],true))throw new DomainException('Acción desconocida.');
        if($operacion==='reabrir'&&$tipo!=='recordatorio')throw new DomainException('Acción inválida.');
        $permiso=$operacion==='eliminar'?'eliminar':'editar';
        asesconExigirPermiso($this->conexion,'WHATSAPP',$permiso);
        $id=(int)($p[$tipo==='recordatorio'?'id_recordatorio':'id_plantilla']??0);
        if($id<1)throw new DomainException('Registro inválido.');
        $archivo=new ArchivoTextos((int)asesconUsuarioActual($this->conexion)['id_usuario']);
        if($tipo==='recordatorio'){
            $filtro=asesconFiltroClientesSql($this->conexion,'c');
            $s=$this->conexion->prepare("SELECT w.id_recordatorio FROM recordatorios_whatsapp w INNER JOIN clientes c ON c.id_cliente=w.id_cliente WHERE w.id_recordatorio=? AND ({$filtro}) LIMIT 1");
            $s->bind_param('i',$id);$s->execute();$existe=(bool)$s->get_result()->fetch_assoc();$s->close();
            if(!$existe)throw new DomainException('No puedes modificar este recordatorio.');
            $modelo=new RecordatorioWhatsApp($this->conexion);
            if($operacion==='editar'){
                $tel=preg_replace('/\D+/','',(string)($p['telefono']??''))??'';
                $tipoMensaje=strtoupper(trim((string)($p['tipo']??'GENERAL')));
                $mensaje=trim((string)($p['mensaje']??''));$fecha=trim((string)($p['fecha_programada']??''));
                if($mensaje===''||strlen($mensaje)>20000||!in_array($tipoMensaje,['VENCIMIENTO','PENDIENTE','DOCUMENTO','HONORARIO','GENERAL'],true))throw new DomainException('Revisa el mensaje y su tipo.');
                $fecha=$fecha!==''?str_replace('T',' ',$fecha):null;
                $modelo->editar($id,$filtro,$tel,$tipoMensaje,$mensaje,$fecha);
            }elseif($operacion==='eliminar')$modelo->eliminar($id,$filtro);
            elseif($operacion==='reabrir')$modelo->reabrir($id,$filtro);
        }else{
            if(!asesconEsPrincipal($this->conexion))throw new DomainException('Solo la cuenta principal puede modificar plantillas compartidas.');
            $modelo=new PlantillaWhatsApp($this->conexion);
            $s=$this->conexion->prepare("SELECT id_plantilla FROM plantillas_whatsapp WHERE id_plantilla=? AND estado='ACTIVA' LIMIT 1");
            $s->bind_param('i',$id);$s->execute();$existe=(bool)$s->get_result()->fetch_assoc();$s->close();
            if(!$existe)throw new DomainException('Plantilla no disponible.');
            if($operacion==='editar'){
                $nombre=trim((string)($p['nombre']??''));$mensaje=trim((string)($p['mensaje']??''));$categoria=strtoupper(trim((string)($p['categoria']??'')));
                $permitidas=['VENCIMIENTOS','PENDIENTES','DIAS_FESTIVOS','DIA_TRABAJADOR','RECORDATORIO_VENCIMIENTO','SALUDO_NAVIDAD','NAVIDAD','TIP_SUNAT'];
                if($nombre===''||$mensaje===''||strlen($mensaje)>20000||!in_array($categoria,$permitidas,true))throw new DomainException('Plantilla incompleta.');
                $modelo->editar($id,$nombre,$categoria,$mensaje);
            }elseif($operacion==='eliminar')$modelo->eliminar($id);
        }
        if($operacion==='archivar'||$operacion==='restaurar')$archivo->cambiar($tipo,$id,$operacion==='archivar');
        if($operacion==='eliminar')$archivo->cambiar($tipo,$id,false);
        asesconRegistrarAuditoria($this->conexion,strtoupper($operacion).'_'.strtoupper($tipo),'WHATSAPP',null,$tipo==='recordatorio'?'recordatorios_whatsapp':'plantillas_whatsapp',(string)$id,'Actualización interna, sin copiar contenido');
        $tab=$operacion==='restaurar'?'archivados':($tipo==='plantilla'?'plantillas':'recordatorios');
        asesconRedirigir('vistas/clientes/comunicacion_informes.php',['seccion'=>'recordatorios','tab'=>$tab,'actualizado'=>1]);
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    $c = new ControladorWhatsApp($conexion);
    $accion = (string)($_POST['accion'] ?? 'crear_recordatorio');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear_plantilla') {
        $c->crearPlantilla($_POST);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'marcar_enviado') {
        $c->marcarEnviado($_POST);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try{
            if(preg_match('/^(editar|eliminar|archivar|restaurar|reabrir)_(recordatorio|plantilla)$/',$accion))$c->modificar($_POST,$accion);
            else $c->crearRecordatorio($_POST);
        }catch(Throwable $e){error_log('WhatsApp: '.get_class($e));$_SESSION['error_whatsapp']=$e instanceof DomainException?$e->getMessage():'No se pudo realizar la acción. Comprueba los datos.';asesconRedirigir('vistas/clientes/comunicacion_informes.php',['seccion'=>'recordatorios']);}
    }
    asesconRedirigir('vistas/clientes/comunicacion_informes.php', ['seccion' => 'recordatorios']);
}
