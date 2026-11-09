<?php

declare(strict_types=1);

final class BuzonSol
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(string $filtro, string $estado = '', string $categoria = '', int $idCliente=0, string $desde='', string $hasta='', int $limite=50, int $offset=0, bool $clienteCompleto=false): array
    {
        $where = $this->condicionListado($filtro,$estado,$categoria,$idCliente,$desde,$hasta);
        $limite=max(1,min(100,$limite));$offset=max(0,$offset);
        $limiteSql=$clienteCompleto&&$idCliente>0?'':" LIMIT {$limite} OFFSET {$offset}";
        $existeCaso = false;
        try {$tablaCaso=$this->conexion->query("SHOW TABLES LIKE 'buzon_sol_casos'");$existeCaso=$tablaCaso && $tablaCaso->num_rows>0;}catch(Throwable){}
        $extraCaso=$existeCaso?',cs.estado AS estado_caso,cs.id_responsable AS id_responsable_caso':'';
        $unionCaso=$existeCaso?' LEFT JOIN buzon_sol_casos cs ON cs.id_documento=b.id_documento':'';
        $r = $this->conexion->query("SELECT b.*,c.ruc,c.razon_social,c.correo AS correo_cliente,c.celular AS celular_cliente{$extraCaso} FROM buzon_sol_sunat b LEFT JOIN clientes c ON c.id_cliente=b.id_cliente{$unionCaso} WHERE ({$where}) ORDER BY COALESCE(b.fecha_recepcion,b.fecha_documento) DESC,b.id_documento DESC{$limiteSql}");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }

    public function contarFiltrados(string $filtro,string $estado,string $categoria,int $idCliente,string $desde,string $hasta):int
    {
        $where=$this->condicionListado($filtro,$estado,$categoria,$idCliente,$desde,$hasta);
        $r=$this->conexion->query("SELECT COUNT(*) n FROM buzon_sol_sunat b LEFT JOIN clientes c ON c.id_cliente=b.id_cliente WHERE ({$where})");
        return $r?(int)($r->fetch_assoc()['n']??0):0;
    }

                                                                                                    
    public function clientesConNotificaciones(string $filtro,string $estado,string $categoria,string $desde,string $hasta):array
    {
        $where=$this->condicionListado($filtro,$estado,$categoria,0,$desde,$hasta);
        $r=$this->conexion->query("SELECT DISTINCT c.id_cliente,c.ruc,c.razon_social FROM buzon_sol_sunat b
            INNER JOIN clientes c ON c.id_cliente=b.id_cliente WHERE ({$where})
            ORDER BY c.razon_social,c.ruc,c.id_cliente");
        $filas=[];
        if($r)while($fila=$r->fetch_assoc())$filas[]=$fila;
        return $filas;
    }

    private function condicionListado(string $filtro,string $estado,string $categoria,int $idCliente,string $desde,string $hasta):string
    {
        $where=$filtro;
        if($estado!=='')$where.=" AND b.estado='".$this->conexion->real_escape_string($estado)."'";
        if($categoria!=='')$where.=" AND b.categoria='".$this->conexion->real_escape_string($categoria)."'";
        if($idCliente>0)$where.=' AND b.id_cliente='.(int)$idCliente;
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$desde))$where.=" AND DATE(COALESCE(b.fecha_recepcion,b.fecha_documento)) >= '".$this->conexion->real_escape_string($desde)."'";
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$hasta))$where.=" AND DATE(COALESCE(b.fecha_recepcion,b.fecha_documento)) <= '".$this->conexion->real_escape_string($hasta)."'";
        return $where;
    }

    public function resumen(string $filtro): array
    {
        $sql = "SELECT COUNT(*) total,SUM(b.estado='NO_REVISADO') sin_leer,COUNT(DISTINCT b.id_cliente) clientes FROM buzon_sol_sunat b LEFT JOIN clientes c ON c.id_cliente=b.id_cliente WHERE ({$filtro})";
        $r = $this->conexion->query($sql);
        $x = $r ? $r->fetch_assoc() : [];
        return ['total' => (int)($x['total'] ?? 0),'sin_leer' => (int)($x['sin_leer'] ?? 0),'clientes' => (int)($x['clientes'] ?? 0)];
    }

    public function actualizarRevision(int $id, ?array $clientesPermitidos, string $estado): void
    {
        if (!in_array($estado, ['REVISADO', 'NO_REVISADO'], true)) {
            throw new DomainException('Estado de revisión no válido.');
        }
        if ($id <= 0 || $clientesPermitidos === []) {
            throw new DomainException('La notificación no está autorizada.');
        }
        if ($clientesPermitidos === null) {
            $sql = "UPDATE buzon_sol_sunat SET estado=? WHERE id_documento=?";
        } else {
            $ids = array_values(array_unique(array_filter(array_map('intval', $clientesPermitidos), static fn(int $n): bool => $n > 0)));
            if ($ids === []) {
                throw new DomainException('La notificación no está autorizada.');
            }
            $sql = "UPDATE buzon_sol_sunat SET estado=? WHERE id_documento=? AND id_cliente IN (" . implode(',', $ids) . ')';
        }
        $s = $this->conexion->prepare($sql);
        if (!$s) {
            throw new RuntimeException('No se pudo preparar la actualización de la notificación.');
        }
        $s->bind_param('si', $estado, $id);
        $s->execute();
        $actualizadas = $s->affected_rows;
        $s->close();
        if ($actualizadas === 0) {
            throw new DomainException('No se encontró la notificación o ya tenía ese estado.');
        }
    }

    public function marcarRevisado(int $id, ?array $clientesPermitidos): void
    {
        $this->actualizarRevision($id, $clientesPermitidos, 'REVISADO');
    }

    public function guardarSincronizados(int $idCliente, array $documentos): int
    {
        $guardados = 0;
        foreach ($documentos as $d) {
            if (!is_array($d)) {
                continue;
            }
            $ident = trim((string)($d['identificador_externo'] ?? ''));
            $asunto = trim((string)($d['asunto'] ?? ''));
            if ($ident === '' || $asunto === '') { throw new DomainException('La respuesta SOL contiene una notificación sin identidad o asunto; se conserva la bandeja anterior.'); }
            $tipo = trim((string)($d['tipo_documento'] ?? 'Notificación SOL'));
            $categoria = trim((string)($d['categoria'] ?? 'SUNAT'));
            $fecha = trim((string)($d['fecha_recepcion'] ?? ''));
            $prioridad = trim((string)($d['prioridad'] ?? 'NORMAL')) ?: 'NORMAL';
            $archivo = trim((string)($d['archivo'] ?? ''));
            $obs = trim((string)($d['observaciones'] ?? ''));

            $s = $this->conexion->prepare('SELECT id_documento FROM buzon_sol_sunat WHERE id_cliente=? AND identificador_externo=? LIMIT 1');
            if (!$s) {
                throw new RuntimeException('No se pudo verificar una notificación SOL.');
            }
            $s->bind_param('is', $idCliente, $ident);
            $s->execute();
            $ex = $s->get_result()->fetch_assoc();
            $s->close();

                                                                            
                                                                               
                                                                                       
            if (!$ex && $fecha !== '') {
                $s=$this->conexion->prepare("SELECT id_documento FROM buzon_sol_sunat WHERE id_cliente=? AND asunto=? AND tipo_documento=? AND fecha_recepcion=? AND identificador_externo NOT LIKE 'SUNAT2-%' AND identificador_externo NOT LIKE 'SUNAT3-%' LIMIT 2");
                $s->bind_param('isss',$idCliente,$asunto,$tipo,$fecha);$s->execute();$coincidencias=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
                if(count($coincidencias)===1){$ex=$coincidencias[0];$idAnterior=(int)$ex['id_documento'];$s=$this->conexion->prepare('UPDATE buzon_sol_sunat SET identificador_externo=? WHERE id_documento=?');$s->bind_param('si',$ident,$idAnterior);$s->execute();$s->close();}
            }

                                                                           
                                                                           
                                                                             
                                                                         
            if (!$ex && str_starts_with($ident, 'SUNAT3-') && $fecha !== '' && $obs !== '') {
                $s=$this->conexion->prepare("SELECT id_documento,observaciones FROM buzon_sol_sunat WHERE id_cliente=? AND asunto=? AND tipo_documento=? AND fecha_recepcion=? AND identificador_externo LIKE 'SUNAT2-%' LIMIT 25");
                $s->bind_param('isss',$idCliente,$asunto,$tipo,$fecha);$s->execute();$anteriores=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
                $identicos=array_values(array_filter($anteriores,static fn(array $f):bool=>(string)($f['observaciones']??'')===$obs));
                if(count($identicos)===1){
                    $ex=$identicos[0];$idAnterior=(int)$ex['id_documento'];
                    $s=$this->conexion->prepare('UPDATE buzon_sol_sunat SET identificador_externo=? WHERE id_documento=?');
                    $s->bind_param('si',$ident,$idAnterior);$s->execute();$s->close();
                }
            }

            if ($ex) {
                $id = (int)$ex['id_documento'];
                $q = $this->conexion->prepare("UPDATE buzon_sol_sunat SET asunto=?,tipo_documento=?,categoria=?,fecha_recepcion=COALESCE(NULLIF(?,''),fecha_recepcion),prioridad=?,archivo=COALESCE(NULLIF(?,''),archivo),observaciones=COALESCE(NULLIF(?,''),observaciones),fecha_sincronizacion=NOW() WHERE id_documento=?");
                if (!$q) {
                    throw new RuntimeException('No se pudo actualizar una notificación SOL.');
                }
                $q->bind_param('sssssssi', $asunto, $tipo, $categoria, $fecha, $prioridad, $archivo, $obs, $id);
                $q->execute();
                $q->close();
            } else {
                $q = $this->conexion->prepare("INSERT INTO buzon_sol_sunat(id_cliente,identificador_externo,asunto,tipo_documento,categoria,fecha_recepcion,estado,prioridad,archivo,observaciones,fecha_sincronizacion) VALUES(?,?,?,?,?,NULLIF(?,''),'NO_REVISADO',?,NULLIF(?,''),NULLIF(?,''),NOW())");
                if (!$q) {
                    throw new RuntimeException('No se pudo guardar una notificación SOL.');
                }
                $q->bind_param('issssssss', $idCliente, $ident, $asunto, $tipo, $categoria, $fecha, $prioridad, $archivo, $obs);
                $q->execute();
                $q->close();
                $guardados++;
            }
        }
        return $guardados;
    }
}
