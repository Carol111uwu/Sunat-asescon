<?php

declare(strict_types=1);

final class RecordatorioWhatsApp
{
    public function __construct(private mysqli $conexion)
    {
    }

    private function baseSelect(string $filtro): string
    {
        return "SELECT w.*,c.ruc,c.razon_social,c.celular AS telefono_cliente FROM recordatorios_whatsapp w INNER JOIN clientes c ON c.id_cliente=w.id_cliente WHERE ({$filtro}) AND w.estado<>'CANCELADO' AND COALESCE(w.mensaje,'')<>''";
    }

    public function listar(string $filtro): array
    {
        $r = $this->conexion->query($this->baseSelect($filtro) . " ORDER BY w.fecha_programada IS NULL,w.fecha_programada DESC,w.id_recordatorio DESC LIMIT 300");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }

                                                                                       
    public function archivados(string $filtro,array $ids):array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn($n)=>$n>0)));
        if($ids===[])return [];
        $r=$this->conexion->query($this->baseSelect($filtro).' AND w.id_recordatorio IN ('.implode(',',$ids).') ORDER BY w.id_recordatorio DESC');
        $resultado=[];
        if($r)while($fila=$r->fetch_assoc())$resultado[]=$fila;
        return $resultado;
    }

    public function programados(string $filtro): array
    {
        $r = $this->conexion->query($this->baseSelect($filtro) . " AND w.estado='PENDIENTE' AND w.fecha_programada IS NOT NULL ORDER BY w.fecha_programada ASC,w.id_recordatorio ASC LIMIT 300");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }

    public function historial(string $filtro): array
    {
        $r = $this->conexion->query($this->baseSelect($filtro) . " AND (w.estado<>'PENDIENTE' OR w.fecha_enviado IS NOT NULL) ORDER BY COALESCE(w.fecha_enviado,w.fecha_programada) DESC,w.id_recordatorio DESC LIMIT 300");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }

    public function crear(int $idCliente, int $idUsuario, string $telefono, string $mensaje, string $tipo, ?string $fecha): int
    {
        $s = $this->conexion->prepare("INSERT INTO recordatorios_whatsapp(id_cliente,id_usuario,telefono,mensaje,tipo,estado,fecha_programada) VALUES(?,?,?,?,?,'PENDIENTE',?)");
        if (!$s) {
            throw new RuntimeException('No se pudo preparar el recordatorio.');
        }
        $s->bind_param('iissss', $idCliente, $idUsuario, $telefono, $mensaje, $tipo, $fecha);
        if (!$s->execute()) {
            $e = $s->error;
            $s->close();
            throw new RuntimeException($e);
        }
        $id = (int)$this->conexion->insert_id;
        $s->close();
        return $id;
    }

    public function marcarEnviado(int $idRecordatorio, string $filtro): bool
    {
        $stmt = $this->conexion->prepare("UPDATE recordatorios_whatsapp w INNER JOIN clientes c ON c.id_cliente=w.id_cliente SET w.estado='ENVIADO',w.fecha_enviado=NOW() WHERE w.id_recordatorio=? AND ({$filtro})");
        if (!$stmt) {
            return false;
        }
$stmt->bind_param('i', $idRecordatorio);
        $ok = $stmt->execute() && $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function editar(int $id,string $filtro,string $tel,string $tipo,string $mensaje,?string $fecha):void
    {
        $s=$this->conexion->prepare("UPDATE recordatorios_whatsapp w INNER JOIN clientes c ON c.id_cliente=w.id_cliente SET w.telefono=?,w.tipo=?,w.mensaje=?,w.fecha_programada=? WHERE w.id_recordatorio=? AND ({$filtro})");
        $s->bind_param('ssssi',$tel,$tipo,$mensaje,$fecha,$id);$s->execute();$s->close();
    }

    public function reabrir(int $id,string $filtro):void
    {
        $s=$this->conexion->prepare("UPDATE recordatorios_whatsapp w INNER JOIN clientes c ON c.id_cliente=w.id_cliente SET w.estado='PENDIENTE',w.fecha_enviado=NULL WHERE w.id_recordatorio=? AND ({$filtro})");
        $s->bind_param('i',$id);$s->execute();$s->close();
    }

    public function eliminar(int $id,string $filtro):void
    {
        $s=$this->conexion->prepare("DELETE w FROM recordatorios_whatsapp w INNER JOIN clientes c ON c.id_cliente=w.id_cliente WHERE w.id_recordatorio=? AND ({$filtro})");
        $s->bind_param('i',$id);$s->execute();$n=$s->affected_rows;$s->close();
        if($n!==1)throw new DomainException('Recordatorio no disponible o sin permiso.');
    }
}
