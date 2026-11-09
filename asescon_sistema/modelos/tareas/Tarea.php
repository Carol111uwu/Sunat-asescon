<?php

declare(strict_types=1);

final class Tarea
{
    public function __construct(private mysqli $conexion)
    {
    }
    public function listar(string $filtroClientes, int $idUsuario, bool $esPrincipal): array
    {
        $extra = $esPrincipal ? '1=1' : "(t.id_usuario={$idUsuario} OR (t.id_cliente IS NOT NULL AND {$filtroClientes}))";
        $res = $this->conexion->query("SELECT t.*,c.razon_social,u.usuario AS responsable FROM tareas t LEFT JOIN clientes c ON c.id_cliente=t.id_cliente LEFT JOIN usuarios u ON u.id_usuario=t.id_usuario WHERE {$extra} ORDER BY t.estado='COMPLETADA',t.fecha_vencimiento IS NULL,t.fecha_vencimiento,t.id_tarea DESC");
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
return $rows;
    }
    public function crear(?int $idCliente, ?int $idUsuario, int $creadaPor, string $titulo, ?string $descripcion, string $prioridad, ?string $inicio, ?string $vence): int
    {
        $stmt = $this->conexion->prepare("INSERT INTO tareas(id_cliente,id_usuario,creada_por,titulo,descripcion,prioridad,estado,fecha_inicio,fecha_vencimiento) VALUES(?,?,?,?,?,?,'PENDIENTE',?,?)");
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar la tarea.');
        }
$stmt->bind_param('iiisssss', $idCliente, $idUsuario, $creadaPor, $titulo, $descripcion, $prioridad, $inicio, $vence);
        if (!$stmt->execute()) {
            $e = $stmt->error;
            $stmt->close();
            throw new RuntimeException($e);
        } $id = (int)$this->conexion->insert_id;
        $stmt->close();
        return $id;
    }
    public function completar(int $id, int $idUsuario, bool $esPrincipal, string $filtroClientes): void
    {
        $filtro = $esPrincipal ? '1=1' : "(t.id_usuario=? OR (t.id_cliente IS NOT NULL AND {$filtroClientes}))";
        $stmt = $this->conexion->prepare("UPDATE tareas t LEFT JOIN clientes c ON c.id_cliente=t.id_cliente SET t.estado='COMPLETADA',t.fecha_completada=NOW() WHERE t.id_tarea=? AND t.estado<>'COMPLETADA' AND {$filtro}");
        if (!$stmt) {
            throw new RuntimeException('No se pudo completar.');
        }
if ($esPrincipal) {
            $stmt->bind_param('i', $id);
        } else {
            $stmt->bind_param('ii', $id, $idUsuario);
        }
        if (!$stmt->execute()) {
            throw new RuntimeException('No se pudo actualizar la tarea.');
        }
        $stmt->close();
    }
    public function reabrir(int $id,int $idUsuario,bool $esPrincipal,string $filtroClientes):void
    {
        $filtro=$esPrincipal?'1=1':"(t.id_usuario=? OR (t.id_cliente IS NOT NULL AND {$filtroClientes}))";
        $stmt=$this->conexion->prepare("UPDATE tareas t LEFT JOIN clientes c ON c.id_cliente=t.id_cliente SET t.estado='PENDIENTE',t.fecha_completada=NULL WHERE t.id_tarea=? AND t.estado='COMPLETADA' AND {$filtro}");
        if($esPrincipal)$stmt->bind_param('i',$id);else $stmt->bind_param('ii',$id,$idUsuario);
        $stmt->execute();$stmt->close();
    }
    public function editar(int $id,int $idUsuario,bool $esPrincipal,string $filtroClientes,string $titulo,string $descripcion,string $prioridad,string $inicio,string $vence):void
    {
        $filtro=$esPrincipal?'1=1':"(t.id_usuario=? OR (t.id_cliente IS NOT NULL AND {$filtroClientes}))";
        $sql="UPDATE tareas t LEFT JOIN clientes c ON c.id_cliente=t.id_cliente
              SET t.titulo=?,t.descripcion=NULLIF(?,''),t.prioridad=?,
                  t.fecha_inicio=NULLIF(?,''),t.fecha_vencimiento=NULLIF(?,'')
              WHERE t.id_tarea=? AND {$filtro}";
        $stmt=$this->conexion->prepare($sql);
        if(!$stmt)throw new RuntimeException('No se pudo preparar la edición de la tarea.');
        if($esPrincipal)$stmt->bind_param('sssssi',$titulo,$descripcion,$prioridad,$inicio,$vence,$id);
        else $stmt->bind_param('sssssii',$titulo,$descripcion,$prioridad,$inicio,$vence,$id,$idUsuario);
        if(!$stmt->execute()){
            $error=$stmt->error;
            $stmt->close();
            throw new RuntimeException('No se pudo guardar la tarea: '.$error);
        }
        $afectadas=$stmt->affected_rows;
        $stmt->close();
        if($afectadas===0){
                                                                                
                                                                                    
            $verificar=$this->conexion->prepare("SELECT t.id_tarea FROM tareas t LEFT JOIN clientes c ON c.id_cliente=t.id_cliente WHERE t.id_tarea=? AND {$filtro} LIMIT 1");
            if(!$verificar)throw new RuntimeException('No se pudo verificar la tarea.');
            if($esPrincipal)$verificar->bind_param('i',$id);
            else $verificar->bind_param('ii',$id,$idUsuario);
            $verificar->execute();$existe=$verificar->get_result()->fetch_assoc();$verificar->close();
            if(!$existe)throw new DomainException('La tarea no existe o no tienes autorización para editarla.');
        }
    }
    public function eliminar(int $id,int $idUsuario,bool $esPrincipal,string $filtroClientes):void
    {
        $filtro=$esPrincipal?'1=1':"(t.id_usuario=? OR (t.id_cliente IS NOT NULL AND {$filtroClientes}))";
        $stmt=$this->conexion->prepare("DELETE t FROM tareas t LEFT JOIN clientes c ON c.id_cliente=t.id_cliente WHERE t.id_tarea=? AND {$filtro}");
        if($esPrincipal)$stmt->bind_param('i',$id);else $stmt->bind_param('ii',$id,$idUsuario);
        $stmt->execute();$afectadas=$stmt->affected_rows;$stmt->close();
        if($afectadas!==1)throw new DomainException('La tarea no existe o no tienes autorización para eliminarla.');
    }

    public function existeVisible(int $id,int $idUsuario,bool $esPrincipal,string $filtroClientes):bool
    {
        $filtro=$esPrincipal?'1=1':"(t.id_usuario=? OR (t.id_cliente IS NOT NULL AND {$filtroClientes}))";
        $s=$this->conexion->prepare("SELECT t.id_tarea FROM tareas t LEFT JOIN clientes c ON c.id_cliente=t.id_cliente WHERE t.id_tarea=? AND {$filtro} LIMIT 1");
        if($esPrincipal)$s->bind_param('i',$id);else $s->bind_param('ii',$id,$idUsuario);
        $s->execute();$existe=(bool)$s->get_result()->fetch_assoc();$s->close();return $existe;
    }
}
