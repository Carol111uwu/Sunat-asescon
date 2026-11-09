<?php
declare(strict_types=1);
require_once __DIR__.'/MigracionCasosSol.php';
final class RepositorioCasosSol
{
    private ?bool $preparado=null;
    public function __construct(private mysqli $db){}
    public function disponible():bool
    {
        if($this->preparado!==null)return $this->preparado;
        foreach(['sunat_casos_buzon','sunat_casos_accesos','sunat_casos_historial','sunat_casos_notas','sunat_casos_notificaciones','sunat_casos_correos','sunat_casos_migraciones'] as $tabla){
            $r=$this->db->query("SHOW TABLES LIKE '{$tabla}'");
            if(!$r instanceof mysqli_result||!$r->fetch_assoc())return $this->preparado=false;
        }
        return $this->preparado=true;
    }
    public function instalar():void
    {
        if(!$this->disponible()){
            $ruta=__DIR__.'/../../base_datos/migraciones/2026_10_22_casos_en_base_principal.sql';
            $sql=file_get_contents($ruta);
            if($sql===false||trim($sql)==='')throw new RuntimeException('No se encontró la migración del seguimiento de casos SUNAT.');
            foreach(explode(';',$sql) as $q){
                if(trim($q)==='')continue;
                if(!$this->db->query($q))throw new RuntimeException('No se pudieron crear las tablas del seguimiento de casos SUNAT.');
            }
            $this->preparado=null;
            if(!$this->disponible())throw new RuntimeException('La estructura del seguimiento de casos SUNAT quedó incompleta.');
        }
                                                                                         
        MigracionCasosSol::importar($this->db);
    }
    public function caso(int $id):?array
    {
        if(!$this->disponible())return null;$s=$this->db->prepare('SELECT * FROM sunat_casos_buzon WHERE id_documento=?');$s->bind_param('i',$id);$s->execute();return $s->get_result()->fetch_assoc();
    }
    public function accesos(int $id):array
    {
        if(!$this->disponible())return [];$s=$this->db->prepare('SELECT id_usuario FROM sunat_casos_accesos WHERE id_documento=?');$s->bind_param('i',$id);$s->execute();return array_map('intval',array_column($s->get_result()->fetch_all(MYSQLI_ASSOC),'id_usuario'));
    }
    public function ids(int $actor,bool $todos=false):array
    {
        if(!$this->disponible())return [];
        $sql=$todos?'SELECT id_documento FROM sunat_casos_buzon':'SELECT DISTINCT cs.id_documento FROM sunat_casos_buzon cs LEFT JOIN sunat_casos_accesos ca ON ca.id_documento=cs.id_documento WHERE cs.id_responsable=? OR ca.id_usuario=?';
        $s=$this->db->prepare($sql);if(!$todos)$s->bind_param('ii',$actor,$actor);$s->execute();return array_map('intval',array_column($s->get_result()->fetch_all(MYSQLI_ASSOC),'id_documento'));
    }
    public function alcance(int $actor,bool $todos=false):array
    {
        if(!$this->disponible())return [];
        $sql=$todos?'SELECT id_documento,id_cliente FROM sunat_casos_buzon':'SELECT DISTINCT cs.id_documento,cs.id_cliente FROM sunat_casos_buzon cs LEFT JOIN sunat_casos_accesos ca ON ca.id_documento=cs.id_documento WHERE cs.id_responsable=? OR ca.id_usuario=?';
        $s=$this->db->prepare($sql);if(!$todos)$s->bind_param('ii',$actor,$actor);$s->execute();return $s->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    public function historial(int $id):array
    {
        if(!$this->disponible())return [];$s=$this->db->prepare('SELECT id_actor,actor,accion,detalle,creado_en FROM sunat_casos_historial WHERE id_documento=? ORDER BY id_historial DESC LIMIT 200');$s->bind_param('i',$id);$s->execute();return $s->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    public function notas(int $id):array
    {
        if(!$this->disponible())return [];$s=$this->db->prepare('SELECT id_nota,id_usuario,autor,contenido AS texto,creado_en AS fecha FROM sunat_casos_notas WHERE id_documento=? AND contenido<>\'\' ORDER BY id_nota');$s->bind_param('i',$id);$s->execute();return $s->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    public function agregarNota(int $id,int $usuario,string $nombre,string $texto,string $clave):array
    {
        $this->instalar();$s=$this->db->prepare('INSERT IGNORE INTO sunat_casos_notas(id_documento,id_usuario,autor,contenido,clave) VALUES(?,?,?,?,?)');$s->bind_param('iisss',$id,$usuario,$nombre,$texto,$clave);$s->execute();return $this->notas($id);
    }
    public function eliminarNota(int $documento,int $nota,array $actor): array
    {
        $this->db->begin_transaction();
        try {
            $s=$this->db->prepare("UPDATE sunat_casos_notas SET contenido='' WHERE id_documento=? AND id_nota=?");$s->bind_param('ii',$documento,$nota);$s->execute();
            if($s->affected_rows!==1)throw new DomainException('La nota ya no está disponible.');
            $uid=(int)$actor['id_usuario'];$nombre=(string)$actor['usuario'];$detalle=json_encode(['id_nota'=>$nota],JSON_THROW_ON_ERROR);
            $s=$this->db->prepare("INSERT INTO sunat_casos_historial(id_documento,id_actor,actor,accion,detalle) VALUES(?,?,?,'ELIMINAR_NOTA',?)");$s->bind_param('iiss',$documento,$uid,$nombre,$detalle);$s->execute();$this->db->commit();
        }catch(Throwable $e){$this->db->rollback();throw $e;}
        return $this->notas($documento);
    }
    public function asignar(array $doc,array $responsable,array $compartidos,array $p,array $actor,string $enlace):void
    {
        $this->instalar();$id=(int)$doc['id_documento'];$cliente=(int)$doc['id_cliente'];$uid=(int)$responsable['id_usuario'];$aid=(int)$actor['id_usuario'];
        $nombre=$responsable['nombre'];$empresa=(string)$doc['razon_social'];$ruc=(string)$doc['ruc'];$asunto=(string)$doc['asunto'];$prioridad=$p['prioridad'];$fecha=$p['fecha_limite'];$observaciones=$p['observaciones'];$baseVersion=(int)($p['version']??0);
        $this->db->begin_transaction();
        try{
            $s=$this->db->prepare('SELECT version,id_cliente FROM sunat_casos_buzon WHERE id_documento=? FOR UPDATE');$s->bind_param('i',$id);$s->execute();$actual=$s->get_result()->fetch_assoc();
            if($actual&&(int)$actual['id_cliente']!==$cliente)throw new DomainException('El registro histórico pertenece a otra empresa; no se modificó.');
            if((int)($actual['version']??0)!==$baseVersion)throw new DomainException('Otra persona actualizó este caso. Vuelve a abrir la asignación.');
            $s=$this->db->prepare("INSERT INTO sunat_casos_buzon(id_documento,id_cliente,id_responsable,responsable,cliente,ruc,asunto,prioridad,fecha_limite,observaciones_equipo,creado_por,modificado_por) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id_responsable=VALUES(id_responsable),responsable=VALUES(responsable),prioridad=VALUES(prioridad),fecha_limite=VALUES(fecha_limite),observaciones_equipo=VALUES(observaciones_equipo),modificado_por=VALUES(modificado_por),version=version+1");
            $s->bind_param('iiisssssssii',$id,$cliente,$uid,$nombre,$empresa,$ruc,$asunto,$prioridad,$fecha,$observaciones,$aid,$aid);$s->execute();
            $s=$this->db->prepare('DELETE FROM sunat_casos_accesos WHERE id_documento=?');$s->bind_param('i',$id);$s->execute();
            foreach($compartidos as $otro){$s=$this->db->prepare('INSERT INTO sunat_casos_accesos(id_documento,id_usuario) VALUES(?,?)');$s->bind_param('ii',$id,$otro);$s->execute();}
            $accion=$actual?'REASIGNACION':'ASIGNACION';$detalle=json_encode(['responsable'=>$uid,'prioridad'=>$prioridad,'fecha_limite'=>$fecha,'compartidos'=>$compartidos,'observaciones'=>$observaciones],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$actorNombre=(string)$actor['usuario'];
            $s=$this->db->prepare('INSERT INTO sunat_casos_historial(id_documento,id_actor,actor,accion,detalle) VALUES(?,?,?,?,?)');$s->bind_param('iisss',$id,$aid,$actorNombre,$accion,$detalle);$s->execute();
            foreach(array_unique(array_merge([$uid],$compartidos)) as $destino){$titulo=$destino===$uid?'Nueva asignación de SUNAT':'Caso SUNAT compartido';$s=$this->db->prepare('INSERT INTO sunat_casos_notificaciones(id_usuario,id_documento,titulo,cliente,prioridad,fecha_limite) VALUES(?,?,?,?,?,?)');$s->bind_param('iissss',$destino,$id,$titulo,$empresa,$prioridad,$fecha);$s->execute();}
            $correo=trim((string)($responsable['correo']??''));
                                                                                                                                        
            if(filter_var($correo,FILTER_VALIDATE_EMAIL)){
                $titulo='ASESCON: nuevo caso SUNAT asignado';
                $mensaje="Hola {$nombre},\n\nSe te asignó un caso de SUNAT.\nCliente: {$empresa}\nRUC: {$ruc}\nAsunto: {$asunto}\nPrioridad: {$prioridad}\nFecha límite: {$fecha}\n\nIngresa con tu cuenta para ver el caso:\n{$enlace}";
                $s=$this->db->prepare('INSERT INTO sunat_casos_correos(id_documento,id_usuario,destinatario,asunto,mensaje) VALUES(?,?,?,?,?)');$s->bind_param('iisss',$id,$uid,$correo,$titulo,$mensaje);$s->execute();
            }
            $this->db->commit();
        }catch(Throwable $e){$this->db->rollback();throw $e;}
    }
    public function estadoCorreo(int $documento):?array
    {
        if(!$this->disponible())return null;
        $s=$this->db->prepare('SELECT estado,intentos,ultimo_error,actualizado_en FROM sunat_casos_correos WHERE id_documento=? ORDER BY id_correo DESC LIMIT 1');$s->bind_param('i',$documento);$s->execute();return $s->get_result()->fetch_assoc();
    }
    public function cambiarEstado(int $id,string $estado,array $actor):void
    {
        if(!in_array($estado,['ABIERTO','CERRADO'],true))throw new DomainException('Estado de caso no válido.');
        $uid=(int)$actor['id_usuario'];$nombre=(string)$actor['usuario'];$accion=$estado==='CERRADO'?'CIERRE':'REAPERTURA';$detalle=json_encode(['estado'=>$estado]);
        $this->db->begin_transaction();
        try{
            $s=$this->db->prepare('SELECT * FROM sunat_casos_buzon WHERE id_documento=? FOR UPDATE');$s->bind_param('i',$id);$s->execute();$caso=$s->get_result()->fetch_assoc();if(!$caso)throw new DomainException('El caso ya no existe.');
            $s=$this->db->prepare('UPDATE sunat_casos_buzon SET estado=?,modificado_por=?,version=version+1 WHERE id_documento=?');$s->bind_param('sii',$estado,$uid,$id);$s->execute();
            $s=$this->db->prepare('INSERT INTO sunat_casos_historial(id_documento,id_actor,actor,accion,detalle) VALUES(?,?,?,?,?)');$s->bind_param('iisss',$id,$uid,$nombre,$accion,$detalle);$s->execute();
            $destino=(int)$caso['creado_por'];
            if($destino>0&&$destino!==$uid){$titulo=$estado==='CERRADO'?'Caso SUNAT resuelto por el responsable':'Caso SUNAT reabierto';$empresa=$caso['cliente'];$prioridad=$caso['prioridad'];$fecha=$caso['fecha_limite'];$s=$this->db->prepare('INSERT INTO sunat_casos_notificaciones(id_usuario,id_documento,titulo,cliente,prioridad,fecha_limite) VALUES(?,?,?,?,?,?)');$s->bind_param('iissss',$destino,$id,$titulo,$empresa,$prioridad,$fecha);$s->execute();}
            $this->db->commit();
        }catch(Throwable $e){$this->db->rollback();throw $e;}
    }
    public function misCasos(int $actor,bool $principal):array
    {
        if(!$this->disponible())return [];$ids=$this->ids($actor,$principal);if(!$ids)return [];
        return $this->db->query('SELECT * FROM sunat_casos_buzon WHERE id_documento IN('.implode(',',$ids).") ORDER BY estado='CERRADO',fecha_limite,actualizado_en DESC LIMIT 500")->fetch_all(MYSQLI_ASSOC);
    }
    public function notificaciones(int $actor,bool $principal=false):array
    {
        if(!$this->disponible())return [];$ids=$this->ids($actor,$principal);if(!$ids)return [];
        $s=$this->db->prepare('SELECT * FROM sunat_casos_notificaciones WHERE id_usuario=? AND id_documento IN('.implode(',',$ids).') ORDER BY id_notificacion DESC LIMIT 100');$s->bind_param('i',$actor);$s->execute();return $s->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    public function marcarLeida(int $actor,int $id):void
    {
        $s=$this->db->prepare('UPDATE sunat_casos_notificaciones SET leida=1 WHERE id_notificacion=? AND id_usuario=?');$s->bind_param('ii',$id,$actor);$s->execute();
    }
}
