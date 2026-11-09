<?php
declare(strict_types=1);
require_once __DIR__.'/ArchivoTextos.php';
require_once __DIR__.'/../casos_sunat/AccesoCasosSol.php';
require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';
                                                                                                  
final class CatalogoTextos
{
    public const CAMPOS=[
        'nota_sunafil'=>['buzon_sunafil','id_documento','observaciones','BUZON_SUNAFIL','sunafil'],
        'observacion_cliente'=>['observaciones_clientes','id_observacion','observacion','CLIENTES','cliente'],
        'informe'=>['informes_entregables','id_informe','observaciones','INFORMES','cliente'],
        'cotizacion_descripcion'=>['cotizaciones','id_cotizacion','descripcion','COTIZACIONES','cliente'],
        'pago_motivo'=>['pagos_tributarios','id_pago','motivo_anulacion','DECLARACIONES','cliente'],
        'tarea'=>['tareas','id_tarea','descripcion','TAREAS','cliente'],
        'plan'=>['planes_servicio','id_plan','descripcion','PLANES','global'],
        'recordatorio'=>['recordatorios_whatsapp','id_recordatorio','mensaje','WHATSAPP','cliente'],
        'plantilla'=>['plantillas_whatsapp','id_plantilla','mensaje','WHATSAPP','global'],
        'honorario'=>['honorarios','id_honorario','observaciones','HONORARIOS','cliente'],
        'cotizacion'=>['cotizaciones','id_cotizacion','notas_condiciones','COTIZACIONES','cliente'],
        'servicio'=>['servicios_cotizacion','id_servicio','descripcion','COTIZACIONES','global'],
        'detraccion'=>['detracciones','id_detraccion','observaciones','DETRACCIONES','cliente'],
        'liquidacion'=>['liquidaciones','id_liquidacion','observaciones','LIQUIDACIONES','cliente'],
        'recibo'=>['recibos_honorarios','id_recibo_honorario','descripcion','RHE','cliente'],
        'correo'=>['configuracion_comunicaciones','id','plantilla_mensaje','CORREO','principal'],
        'nota_sol'=>['sunat_casos_notas','id_nota','contenido','BUZON_SOL','caso'],
        'observacion_caso'=>['sunat_casos_buzon','id_documento','observaciones_equipo','BUZON_SOL','caso'],
        'avance_sol'=>['buzon_sol_avances','id_avance','contenido','BUZON_SOL','caso_local'],
    ];
    public function __construct(private mysqli $db){}
    private function base(string $tipo):mysqli{return in_array($tipo,['nota_sol','observacion_caso'],true)?asesconConexionPrincipal():$this->db;}
    public function obtener(string $tipo,int $id):?array
    {
        if($tipo==='nota_sunafil')return $this->notaSunafil($id);
        [$tabla,$pk,$campo,$modulo,$alcance]=self::CAMPOS[$tipo]??throw new DomainException('Campo de texto no permitido.');
        if($id<1||($alcance!=='principal'&&!asesconTienePermiso($this->db,$modulo,'ver'))||($alcance==='principal'&&!asesconEsPrincipal($this->db)))return null;
        $db=$this->base($tipo);$s=$db->prepare("SELECT * FROM {$tabla} WHERE {$pk}=? LIMIT 1");$s->bind_param('i',$id);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();if(!$r)return null;
        if(in_array($alcance,['caso','caso_local'],true)){
            $doc=(new RepositorioDocumentoBuzon($this->db))->obtener('SOL',(int)$r['id_documento'],AccesoCasosSol::filtro($this->db,asesconFiltroClientesSql($this->db,'c')));if(!$doc)return null;
        }elseif($alcance==='cliente'){
            $cliente=(int)($r['id_cliente']??0);$propia=$tipo==='tarea'&&(int)($r['id_usuario']??0)===(int)asesconUsuarioActual($this->db)['id_usuario'];
            if(!$propia&&(($cliente>0&&!asesconPuedeVerCliente($this->db,$cliente))||($cliente<1&&!asesconEsPrincipal($this->db))))return null;
        }
        $texto=(string)($r[$campo]??'');
        if($tipo==='liquidacion'&&str_starts_with($texto,"ASESCON_LIQ_V1\n")){$datos=json_decode(substr($texto,15),true,512,JSON_THROW_ON_ERROR);$texto=(string)($datos['notas']??'');}
        $principal=asesconEsPrincipal($this->db);$puede=$alcance==='principal'?$principal:asesconTienePermiso($this->db,$modulo,'editar');
        if(in_array($alcance,['global','caso','caso_local'],true))$puede=$puede&&$principal;
        $puede=$puede&&$tipo!=='pago_motivo';
        $puedeEditar=$puede;
                                                                                  
                                                                                 
        return ['tipo'=>$tipo,'id'=>$id,'texto'=>$texto,'revision'=>hash('sha256',(string)($r[$campo]??'')),'titulo'=>$r['titulo']??$r['nombre']??$r['concepto']??$r['numero_cotizacion']??ucfirst(str_replace('_',' ',$tipo)).' #'.$id,'id_cliente'=>(int)($r['id_cliente']??0),'puede_eliminar'=>$puede,'puede_editar'=>$puedeEditar];
    }
    public function listar(string $tipo,int $desde=0):array
    {
        if(!isset(self::CAMPOS[$tipo]))throw new DomainException('Tipo no permitido.');
        [$tabla,$pk,$campo,$modulo,$alcance]=self::CAMPOS[$tipo];
        if(($alcance==='principal'&&!asesconEsPrincipal($this->db))||($alcance!=='principal'&&!asesconTienePermiso($this->db,$modulo,'ver')))return ['textos'=>[],'siguiente'=>null];
        $db=$this->base($tipo);$textoNoVacio=$tipo==='nota_sunafil'?'':" AND COALESCE({$campo},'')<>''";$s=$db->prepare("SELECT {$pk} id FROM {$tabla} WHERE {$pk}>?{$textoNoVacio} ORDER BY {$pk} LIMIT 201");$s->bind_param('i',$desde);$s->execute();$filas=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();$hayMas=count($filas)>200;$filas=array_slice($filas,0,200);$salida=[];
        $archivados=(new ArchivoTextos((int)asesconUsuarioActual($this->db)['id_usuario']))->listar();
        if($tipo==='nota_sunafil'){foreach($filas as $f){$doc=(new RepositorioDocumentoBuzon($this->db))->obtener('SUNAFIL',(int)$f['id'],asesconFiltroClientesSql($this->db,'c'));if(!$doc)continue;require_once __DIR__.'/../buzones/ServicioNotasBuzon.php';foreach((new ServicioNotasBuzon())->listar('SUNAFIL',(int)$doc['id_cliente'],(int)$doc['id_documento']) as $nota){$d=$this->notaSunafil((int)$nota['id_nota']);if($d){$d['archivado']=isset($archivados[$tipo.':'.$d['id']]);$salida[]=$d;}}}return ['textos'=>$salida,'siguiente'=>$hayMas?(int)end($filas)['id']:null];}
        foreach($filas as $f){$d=$this->obtener($tipo,(int)$f['id']);if($d&&$d['texto']!==''){$d['archivado']=isset($archivados[$tipo.':'.$d['id']]);$salida[]=$d;}}
        return ['textos'=>$salida,'siguiente'=>$hayMas?(int)end($filas)['id']:null];
    }
    public function eliminar(string $tipo,int $id,string $revision):void
    {
        $d=$this->obtener($tipo,$id);if(!$d||!$d['puede_eliminar'])throw new DomainException('No tienes permiso para eliminar este texto.');
        if(!hash_equals($d['revision'],$revision))throw new DomainException('El texto cambió. Recarga antes de eliminarlo.');
        if($tipo==='nota_sunafil'){require_once __DIR__.'/../buzones/ServicioNotasBuzon.php';(new ServicioNotasBuzon())->eliminar('SUNAFIL',$d['id_cliente'],intdiv($id,100000),$id,$revision);asesconRegistrarAuditoria($this->db,'ELIMINAR_TEXTO','BUZON_SUNAFIL',$d['id_cliente'],'notas_sunafil',(string)$id,'Texto eliminado; hash anterior '.$revision);(new ArchivoTextos((int)asesconUsuarioActual($this->db)['id_usuario']))->cambiar($tipo,$id,false);return;}
        [$tabla,$pk,$campo]=self::CAMPOS[$tipo];$db=$this->base($tipo);
        $db->begin_transaction();
        try{
            $s=$db->prepare("SELECT * FROM {$tabla} WHERE {$pk}=? FOR UPDATE");$s->bind_param('i',$id);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();
            if(!$r||!hash_equals($revision,hash('sha256',(string)$r[$campo])))throw new DomainException('El texto cambió. Recarga antes de eliminarlo.');
            $nuevo='';$extra='';
            if($tipo==='liquidacion'&&str_starts_with((string)$r[$campo],"ASESCON_LIQ_V1\n")){$datos=json_decode(substr($r[$campo],15),true,512,JSON_THROW_ON_ERROR);$datos['notas']='';$nuevo="ASESCON_LIQ_V1\n".json_encode($datos,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
            if($tipo==='recordatorio')$extra=",estado='CANCELADO'";
            if($tipo==='plantilla')$extra=",estado='INACTIVA'";
            if($tipo==='cotizacion')$extra=",descripcion=''";
                                                                                  
                                                                              
            if(in_array($tipo,['nota_sol','avance_sol','observacion_cliente'],true)){
                $s=$db->prepare("DELETE FROM {$tabla} WHERE {$pk}=?");$s->bind_param('i',$id);
            }else{$s=$db->prepare("UPDATE {$tabla} SET {$campo}=?{$extra} WHERE {$pk}=?");$s->bind_param('si',$nuevo,$id);}
            $s->execute();$s->close();
                                                                              
            if(in_array($tipo,['nota_sol','observacion_caso'],true)){
                $actor=asesconUsuarioActual($this->db);$uid=(int)$actor['id_usuario'];$nombre=(string)$actor['usuario'];$doc=$tipo==='nota_sol'?(int)($r['id_documento']??0):$id;$detalle=json_encode(['tipo'=>$tipo,'id'=>$id,'hash_anterior'=>$revision]);
                $s=$db->prepare("INSERT INTO sunat_casos_historial(id_documento,id_actor,actor,accion,detalle) VALUES(?,?,?,'ELIMINAR_TEXTO',?)");$s->bind_param('iiss',$doc,$uid,$nombre,$detalle);$s->execute();$s->close();
            }else{asesconRegistrarAuditoria($this->db,'ELIMINAR_TEXTO',self::CAMPOS[$tipo][3],$d['id_cliente']?:null,$tabla,(string)$id,'Texto eliminado; hash anterior '.$revision);}
            $db->commit();
        }catch(Throwable $e){$db->rollback();throw $e;}
        (new ArchivoTextos((int)asesconUsuarioActual($this->db)['id_usuario']))->cambiar($tipo,$id,false);
    }
                                                                                              
    public function editar(string $tipo,int $id,string $revision,string $texto):void
    {
        $d=$this->obtener($tipo,$id);
        if(!$d||!($d['puede_editar']??false))throw new DomainException('No tienes permiso para editar este contenido.');
        if(!hash_equals($d['revision'],$revision))throw new DomainException('Alguien modificó el texto. Actualiza antes de editar.');
        $texto=trim($texto);
        if($texto===''||strlen($texto)>20000||preg_match('//u',$texto)!==1)throw new DomainException('Escribe un texto válido de hasta 20.000 bytes. Para quitarlo, usa Eliminar.');
        if($tipo==='nota_sunafil'){
            require_once __DIR__.'/../buzones/ServicioNotasBuzon.php';
            (new ServicioNotasBuzon())->editar('SUNAFIL',$d['id_cliente'],intdiv($id,100000),$id,$revision,$texto);
            asesconRegistrarAuditoria($this->db,'EDITAR_TEXTO','BUZON_SUNAFIL',$d['id_cliente'],'notas_sunafil',(string)$id,'Texto editado; hash anterior '.$revision);
            return;
        }
        $config=self::CAMPOS[$tipo];[$tabla,$pk,$campo]=$config;$db=$this->base($tipo);
        $db->begin_transaction();
        try{
            $s=$db->prepare("SELECT {$campo} texto FROM {$tabla} WHERE {$pk}=? FOR UPDATE");$s->bind_param('i',$id);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();
            if(!$r||!hash_equals(hash('sha256',(string)$r['texto']),$revision))throw new DomainException('El contenido cambió durante la edición. Actualiza la página.');
            $nuevo=$texto;
            if($tipo==='liquidacion'&&str_starts_with((string)$r['texto'],"ASESCON_LIQ_V1\n")){
                $datos=json_decode(substr($r['texto'],15),true,512,JSON_THROW_ON_ERROR);
                $datos['notas']=$texto;
                $nuevo="ASESCON_LIQ_V1\n".json_encode($datos,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            }
            if($tipo==='cotizacion'){
                                                                                            
                $s=$db->prepare("UPDATE {$tabla} SET {$campo}=?,descripcion=? WHERE {$pk}=?");$s->bind_param('ssi',$nuevo,$nuevo,$id);
            }else{$s=$db->prepare("UPDATE {$tabla} SET {$campo}=? WHERE {$pk}=?");$s->bind_param('si',$nuevo,$id);}
            $s->execute();$s->close();
            if(in_array($tipo,['nota_sol','observacion_caso'],true)){
                $actor=asesconUsuarioActual($this->db);$uid=(int)$actor['id_usuario'];$nombre=(string)$actor['usuario'];
                $doc=$tipo==='nota_sol'?(int)$db->query('SELECT id_documento FROM sunat_casos_notas WHERE id_nota='.(int)$id)->fetch_assoc()['id_documento']:$id;
                $detalle=json_encode(['tipo'=>$tipo,'id'=>$id,'hash_anterior'=>$revision]);
                $s=$db->prepare("INSERT INTO sunat_casos_historial(id_documento,id_actor,actor,accion,detalle) VALUES(?,?,?,'EDITAR_TEXTO',?)");
                $s->bind_param('iiss',$doc,$uid,$nombre,$detalle);$s->execute();$s->close();
            }else asesconRegistrarAuditoria($this->db,'EDITAR_TEXTO',$config[3],$d['id_cliente']?:null,$tabla,(string)$id,'Texto corregido; hash anterior '.$revision);
            $db->commit();
        }catch(Throwable $e){$db->rollback();throw $e;}
    }
    private function notaSunafil(int $id):?array
    {
        if(!asesconTienePermiso($this->db,'BUZON_SUNAFIL','ver'))return null;
        $documento=intdiv($id,100000);$d=(new RepositorioDocumentoBuzon($this->db))->obtener('SUNAFIL',$documento,asesconFiltroClientesSql($this->db,'c'));if(!$d)return null;
        require_once __DIR__.'/../buzones/ServicioNotasBuzon.php';
        foreach((new ServicioNotasBuzon())->listar('SUNAFIL',(int)$d['id_cliente'],$documento) as $n){if((int)$n['id_nota']===$id){$puede=asesconTienePermiso($this->db,'BUZON_SUNAFIL','editar');return ['tipo'=>'nota_sunafil','id'=>$id,'id_cliente'=>(int)$d['id_cliente'],'texto'=>$n['texto'],'titulo'=>$d['asunto'],'revision'=>hash('sha256',$n['texto']),'puede_eliminar'=>$puede,'puede_editar'=>$puede];}}
        return null;
    }

}
