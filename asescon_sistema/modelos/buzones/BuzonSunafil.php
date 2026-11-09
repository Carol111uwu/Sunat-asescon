<?php

declare(strict_types=1);

final class BuzonSunafil
{
    public function __construct(private mysqli $conexion)
    {
    }
    public function listar(string $filtro, string $estado = '', int $idCliente = 0,
                           string $tipo = '', string $desde = '', string $hasta = ''): array
    {
        if ($idCliente <= 0) return [];
        $where = $this->condicionListado($filtro,$estado,$idCliente,$tipo,$desde,$hasta);
        $r = $this->conexion->query("SELECT b.*,c.ruc,c.razon_social FROM buzon_sunafil b
            INNER JOIN clientes c ON c.id_cliente=b.id_cliente WHERE ({$where})
            ORDER BY COALESCE(b.fecha_recepcion,b.fecha_documento) DESC,b.id_documento DESC");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }

    public function clientesConNotificaciones(string $filtro,string $estado,string $tipo,string $desde,string $hasta):array
    {
        $where=$this->condicionListado($filtro,$estado,0,$tipo,$desde,$hasta);
        $r=$this->conexion->query("SELECT DISTINCT c.id_cliente,c.ruc,c.razon_social FROM buzon_sunafil b
            INNER JOIN clientes c ON c.id_cliente=b.id_cliente WHERE ({$where})
            ORDER BY c.razon_social,c.ruc,c.id_cliente");
        $filas=[];
        if($r)while($fila=$r->fetch_assoc())$filas[]=$fila;
        return $filas;
    }

    public function contarFiltrados(string $filtro, string $estado, int $idCliente,
                                   string $tipo, string $desde, string $hasta): int
    {
        $where = $this->condicionListado($filtro, $estado, $idCliente, $tipo, $desde, $hasta);
        $consulta = $this->conexion->query("SELECT COUNT(*) AS cantidad FROM buzon_sunafil b
            INNER JOIN clientes c ON c.id_cliente=b.id_cliente WHERE ({$where})");
        return $consulta ? (int)($consulta->fetch_assoc()['cantidad'] ?? 0) : 0;
    }

    public function tiposDisponibles(string $filtro):array
    {
        $r=$this->conexion->query("SELECT DISTINCT b.tipo_documento FROM buzon_sunafil b
            INNER JOIN clientes c ON c.id_cliente=b.id_cliente WHERE ({$filtro})
            AND b.tipo_documento IS NOT NULL AND b.tipo_documento<>'' ORDER BY b.tipo_documento");
        $tipos=[];
        if($r)while($fila=$r->fetch_assoc())$tipos[]=(string)$fila['tipo_documento'];
        return $tipos;
    }

    private function condicionListado(string $filtro,string $estado,int $idCliente,string $tipo,string $desde,string $hasta):string
    {
        $where="({$filtro})";
        if($estado!=='')$where.=" AND b.estado='".$this->conexion->real_escape_string($estado)."'";
        if($idCliente>0)$where.=' AND b.id_cliente='.(int)$idCliente;
        if($tipo!=='')$where.=" AND b.tipo_documento='".$this->conexion->real_escape_string($tipo)."'";
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$desde))$where.=" AND DATE(COALESCE(b.fecha_recepcion,b.fecha_documento)) >= '".$this->conexion->real_escape_string($desde)."'";
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$hasta))$where.=" AND DATE(COALESCE(b.fecha_recepcion,b.fecha_documento)) <= '".$this->conexion->real_escape_string($hasta)."'";
        return $where;
    }
    public function resumen(string $filtro): array
    {
        $r = $this->conexion->query("SELECT COUNT(*) total,SUM(b.estado='NO_REVISADO') sin_leer,COUNT(DISTINCT b.id_cliente) clientes FROM buzon_sunafil b LEFT JOIN clientes c ON c.id_cliente=b.id_cliente WHERE {$filtro}");
        $x = $r ? $r->fetch_assoc() : [];
        return ['total' => (int)($x['total'] ?? 0),'sin_leer' => (int)($x['sin_leer'] ?? 0),'clientes' => (int)($x['clientes'] ?? 0)];
    }
    public function marcarRevisado(int $id, ?array $clientesPermitidos): void
    {
        if ($id <= 0 || $clientesPermitidos === []) {
            throw new DomainException('La notificación no está autorizada.');
        }
        if ($clientesPermitidos === null) {
            $sql = "UPDATE buzon_sunafil SET estado='REVISADO' WHERE id_documento=?";
        } else {
            $ids = array_values(array_unique(array_filter(array_map('intval', $clientesPermitidos), static fn(int $n): bool => $n > 0)));
            if ($ids === []) {
                throw new DomainException('La notificación no está autorizada.');
            }
            $sql = "UPDATE buzon_sunafil SET estado='REVISADO' WHERE id_documento=? AND id_cliente IN (" . implode(',', $ids) . ')';
        }
        $s = $this->conexion->prepare($sql);
        if (!$s) {
            throw new RuntimeException('No se pudo preparar la actualización de la notificación.');
        }
        $s->bind_param('i', $id);
        if (!$s->execute()) { throw new RuntimeException('No se pudo consultar o actualizar la notificación SUNAFIL.'); }
        $actualizadas = $s->affected_rows;
        $s->close();
        if ($actualizadas === 0) {
            throw new DomainException('La notificación no está disponible o ya fue revisada.');
        }
    }

    public function guardarSincronizados(int $idCliente, array $documentos): int
    {
        $guardados = 0;
        foreach ($documentos as $d) {
            if (!is_array($d)) {
                throw new RuntimeException('La respuesta contiene una notificación no válida.');
            }
            $ident = trim((string)($d['identificador_externo'] ?? ''));
            $asunto = trim((string)($d['asunto'] ?? ''));
            if ($ident === '' || $asunto === '') {
                throw new RuntimeException('La notificación no tiene identificador o asunto.');
            }
            $tipo = trim((string)($d['tipo_documento'] ?? ''));
            $categoria = trim((string)($d['categoria'] ?? 'SUNAFIL'));
            $fecha = trim((string)($d['fecha_recepcion'] ?? ''));
            $prioridad = trim((string)($d['prioridad'] ?? 'NORMAL')) ?: 'NORMAL';
            $archivo = trim((string)($d['archivo'] ?? ''));
            $obs = trim((string)($d['observaciones'] ?? ''));

            $s = $this->conexion->prepare('SELECT id_documento FROM buzon_sunafil WHERE id_cliente=? AND identificador_externo=? LIMIT 1');
            if (!$s) {
                throw new RuntimeException('No se pudo verificar una notificación SUNAFIL.');
            }
            $s->bind_param('is', $idCliente, $ident);
            if (!$s->execute()) { throw new RuntimeException('No se pudo consultar o actualizar la notificación SUNAFIL.'); }
            $ex = $s->get_result()->fetch_assoc();
            $s->close();

            if ($ex) {
                $id = (int)$ex['id_documento'];
                $q = $this->conexion->prepare("UPDATE buzon_sunafil SET asunto=?,tipo_documento=?,categoria=?,fecha_recepcion=COALESCE(NULLIF(?,''),fecha_recepcion),prioridad=?,archivo=COALESCE(NULLIF(?,''),archivo),observaciones=COALESCE(NULLIF(?,''),observaciones),fecha_sincronizacion=NOW() WHERE id_documento=?");
                if (!$q) {
                    throw new RuntimeException('No se pudo actualizar una notificación SUNAFIL.');
                }
                $q->bind_param('sssssssi', $asunto, $tipo, $categoria, $fecha, $prioridad, $archivo, $obs, $id);
                if (!$q->execute()) { throw new RuntimeException('No se pudo guardar la notificación SUNAFIL.'); }
                $q->close();
            } else {
                $q = $this->conexion->prepare("INSERT INTO buzon_sunafil(id_cliente,identificador_externo,asunto,tipo_documento,categoria,fecha_recepcion,estado,prioridad,archivo,observaciones,fecha_sincronizacion) VALUES(?,?,?,?,?,NULLIF(?,''),'NO_REVISADO',?,NULLIF(?,''),NULLIF(?,''),NOW())");
                if (!$q) {
                    throw new RuntimeException('No se pudo guardar una notificación SUNAFIL.');
                }
                $q->bind_param('issssssss', $idCliente, $ident, $asunto, $tipo, $categoria, $fecha, $prioridad, $archivo, $obs);
                if (!$q->execute()) { throw new RuntimeException('No se pudo guardar la notificación SUNAFIL.'); }
                $q->close();
                $guardados++;
            }
        }
        return $guardados;
    }
}
