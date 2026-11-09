<?php
declare(strict_types=1);
require_once __DIR__.'/../../../configuracion/conexiones_bases_datos.php';
require_once __DIR__.'/EsquemaHistoricoSunat.php';
final class RepositorioHistoricoSunat
{
    private bool $preparado=false;
    public function __construct(private ?mysqli $db=null){}
    private function base(): mysqli {return $this->db??=asesconConexionHistoricoSunat();}
    public function disponible(): bool
    {
        try {return EsquemaHistoricoSunat::comprobar($this->base());}
        catch(Throwable $e){error_log('Histórico SUNAT: '.get_class($e));return false;}
    }
    private function preparar(): mysqli
    {
        $db=$this->base();if(!$this->preparado){EsquemaHistoricoSunat::preparar($db);$this->preparado=true;}return $db;
    }
    private function registro(mysqli $db,string $tabla,string $tipo,array $valores,int $consulta): int
    {
        if(!in_array($tabla,['sunat_declaraciones','sunat_pagos'],true))throw new LogicException('Tabla histórica no permitida.');
        $clave=$valores['clave_externa'];$s=$db->prepare("SELECT datos_origen FROM {$tabla} WHERE clave_externa=? FOR UPDATE");$s->bind_param('s',$clave);$s->execute();$anterior=$s->get_result()->fetch_assoc();
        $campos=array_keys($valores);$parametros=array_values($valores);$marcas=implode(',',array_fill(0,count($campos),'?'));
        $actualizar=implode(',',array_map(static fn($c)=>$c.'=VALUES('.$c.')',array_diff($campos,['clave_externa'])));
        $s=$db->prepare("INSERT INTO {$tabla}(".implode(',',$campos).") VALUES({$marcas}) ON DUPLICATE KEY UPDATE {$actualizar},ultima_consulta=CURRENT_TIMESTAMP");
        $tipos=str_repeat('s',count($parametros));$s->bind_param($tipos,...$parametros);$s->execute();
        $nuevo=$valores['datos_origen'];$previo=$anterior['datos_origen']??null;
        if(!$anterior||json_decode((string)$previo,true)!==json_decode($nuevo,true)){
            $accion=$anterior?'ACTUALIZADO':'INSERTADO';$s=$db->prepare('INSERT INTO sunat_historial_cambios(tipo_registro,clave_externa,accion,datos_anteriores,datos_nuevos,id_consulta) VALUES(?,?,?,?,?,?)');
            $s->bind_param('sssssi',$tipo,$clave,$accion,$previo,$nuevo,$consulta);$s->execute();
        }
        $id=$tabla==='sunat_declaraciones'?'id_declaracion':'id_pago';$s=$db->prepare("SELECT {$id} id FROM {$tabla} WHERE clave_externa=?");$s->bind_param('s',$clave);$s->execute();return (int)$s->get_result()->fetch_assoc()['id'];
    }
    public function guardar(array $cliente,array $consulta,int $actor=0): array
    {
        $db=$this->preparar();$id=(int)$cliente['id_cliente'];$ruc=(string)$cliente['ruc'];$empresa=(string)$cliente['razon_social'];
        foreach($consulta['filas'] as $fila)if((string)($fila['numRuc']??'')!==$ruc)throw new DomainException('El histórico recibido pertenece a otro RUC.');
        $desde=substr(str_replace('-','',(string)$consulta['inicio']),0,6);$hasta=substr(str_replace('-','',(string)$consulta['fin']),0,6);
        $db->begin_transaction();
        try{
            $s=$db->prepare("INSERT INTO sunat_historial_consultas(id_cliente_asescon,ruc,tipo_consulta,periodo_desde,periodo_hasta,usuario_asescon) VALUES(?,?,'DECLARACIONES_PAGOS',?,?,?)");$s->bind_param('isssi',$id,$ruc,$desde,$hasta,$actor);$s->execute();$cid=(int)$db->query('SELECT LAST_INSERT_ID() id')->fetch_assoc()['id'];
            foreach($consulta['filas'] as $fila){
                $clave=hash('sha256',$ruc.'|'.$fila['codFor'].'|'.$fila['numOrd']);$fecha=DateTimeImmutable::createFromFormat('!d/m/Y',(string)$fila['strFecPres']);
                if(!$fecha||$fecha->format('d/m/Y')!==$fila['strFecPres'])throw new DomainException('Fecha oficial no válida.');
                $json=json_encode($fila,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                $comun=['clave_externa'=>$clave,'id_cliente_asescon'=>$id,'ruc'=>$ruc,'razon_social'=>$empresa,'periodo'=>$fila['perTri'],'formulario'=>$fila['codFor'],'numero_orden'=>$fila['numOrd']];
                $did=$this->registro($db,'sunat_declaraciones','DECLARACION',$comun+['descripcion'=>$fila['desFor']??null,'fecha_presentacion'=>$fecha->format('Y-m-d H:i:s'),'estado_presentacion'=>'PRESENTADA','datos_origen'=>$json],$cid);
                                                                                                               
                $pago=$comun;$pago['clave_externa']=hash('sha256',$clave.'|RESUMEN_PAGO');
                $pago+=['id_declaracion'=>$did,'monto_pagado'=>(string)$fila['mtoPag'],'medio_pago'=>$fila['nomEntFin']??null,'datos_origen'=>json_encode(['origen'=>'RESUMEN_PAGO_POR_ORDEN','fila'=>$fila],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
                $this->registro($db,'sunat_pagos','PAGO',$pago,$cid);
            }
            $json=json_encode($consulta,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$s=$db->prepare('INSERT INTO sunat_consultas_respuestas(id_consulta,id_cliente_asescon,ruc,respuesta) VALUES(?,?,?,?)');$s->bind_param('iiss',$cid,$id,$ruc,$json);$s->execute();
            $n=count($consulta['filas']);$s=$db->prepare("UPDATE sunat_historial_consultas SET estado='COMPLETADA',cantidad_obtenida=?,fecha_fin=NOW() WHERE id_consulta=?");$s->bind_param('ii',$n,$cid);$s->execute();
            $db->commit();return $consulta;
        }catch(Throwable $e){$db->rollback();throw $e;}
    }
    public function ultima(int $cliente,string $ruc): ?array
    {
        $db=$this->preparar();$s=$db->prepare('SELECT respuesta FROM sunat_consultas_respuestas WHERE id_cliente_asescon=? AND ruc=? ORDER BY id_consulta DESC LIMIT 1');$s->bind_param('is',$cliente,$ruc);$s->execute();$r=$s->get_result()->fetch_assoc();return $r?json_decode($r['respuesta'],true,64,JSON_THROW_ON_ERROR):null;
    }
    public function obtener(int $cliente,string $ruc,string $form,string $orden): ?array
    {
        $db=$this->preparar();$s=$db->prepare('SELECT datos_origen FROM sunat_declaraciones WHERE id_cliente_asescon=? AND ruc=? AND formulario=? AND numero_orden=?');$s->bind_param('isss',$cliente,$ruc,$form,$orden);$s->execute();$fila=$s->get_result()->fetch_assoc();if(!$fila)return null;
        $s=$db->prepare('SELECT respuesta FROM sunat_detalles_declaracion WHERE id_cliente_asescon=? AND ruc=? AND formulario=? AND numero_orden=?');$s->bind_param('isss',$cliente,$ruc,$form,$orden);$s->execute();$detalle=$s->get_result()->fetch_assoc();
        $origen=json_decode((string)$fila['datos_origen'],true,64,JSON_THROW_ON_ERROR);
        if(!is_array($origen)||(string)($origen['numRuc']??'')!==$ruc)throw new DomainException('Este registro histórico no contiene la referencia oficial para descargar. Consulta de nuevo su rango.');
        return ['fila'=>$origen,'detalle'=>$detalle?json_decode($detalle['respuesta'],true,64,JSON_THROW_ON_ERROR):null];
    }
    public function obtenerDetalle(int $cliente,string $ruc,string $form,string $orden):?array
    {
        $db=$this->preparar();$s=$db->prepare('SELECT respuesta FROM sunat_detalles_declaracion WHERE id_cliente_asescon=? AND ruc=? AND formulario=? AND numero_orden=?');$s->bind_param('isss',$cliente,$ruc,$form,$orden);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();return $r?json_decode($r['respuesta'],true,64,JSON_THROW_ON_ERROR):null;
    }
    public function guardarDetalle(int $cliente,string $ruc,string $form,string $orden,array $detalle): void
    {
        $db=$this->preparar();$json=json_encode($detalle,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $s=$db->prepare('INSERT INTO sunat_detalles_declaracion(id_cliente_asescon,ruc,formulario,numero_orden,respuesta) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE respuesta=VALUES(respuesta),consultado_en=NOW()');$s->bind_param('issss',$cliente,$ruc,$form,$orden,$json);$s->execute();
    }
    public function detracciones(array $clientes,string $periodo): array
    {
        if(!$clientes)return [];$db=$this->base();$cond=[];$param=[];
        foreach($clientes as $c){$cond[]='(id_cliente_asescon=? AND ruc=?)';$param[]=(string)$c['id_cliente'];$param[]=(string)$c['ruc'];}
        $sql='SELECT *,id_cliente_asescon id_cliente,descripcion_bien_servicio concepto FROM sunat_detracciones WHERE ('.implode(' OR ',$cond).')';
        if($periodo!==''){$sql.=' AND periodo LIKE ?';$param[]=str_ends_with($periodo,'00')?substr($periodo,0,4).'%':$periodo;}
        $s=$db->prepare($sql.' ORDER BY fecha_deposito DESC,id_detraccion DESC');$tipos=str_repeat('s',count($param));$s->bind_param($tipos,...$param);$s->execute();return $s->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
