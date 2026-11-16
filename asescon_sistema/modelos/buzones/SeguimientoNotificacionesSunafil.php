<?php
declare(strict_types=1);

final class SeguimientoNotificacionesSunafil
{
    public function __construct(private mysqli $conexion) {}

    public function disponible(): bool
    {
        try {
            $r = $this->conexion->query("SELECT COUNT(*) total FROM information_schema.tables
                WHERE table_schema=DATABASE()
                  AND table_name IN ('buzon_sunafil_casos','buzon_sunafil_avances')");
            return (int)$r->fetch_assoc()['total'] === 2;
        } catch (Throwable) { return false; }
    }

    public function habilitar(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../base_datos/migraciones/2026_11_16_seguimiento_sunafil.sql');
        if ($sql === false) {
            throw new RuntimeException('Falta la migración de seguimiento de SUNAFIL.');
        }
        foreach (explode(';', $sql) as $sentencia) {
            if (trim($sentencia) !== '') {
                if (!$this->conexion->query($sentencia)) {
                    throw new RuntimeException('No se pudo preparar el seguimiento.');
                }
            }
        }
    }

    public function caso(int $idDocumento, int $idCliente): array
    {
        if (!$this->disponible()) return [];
        $s=$this->conexion->prepare('SELECT cs.*,u.usuario AS responsable FROM buzon_sunafil_casos cs LEFT JOIN usuarios u ON u.id_usuario=cs.id_responsable WHERE cs.id_documento=? AND cs.id_cliente=?');
        $s->bind_param('ii',$idDocumento,$idCliente);$s->execute();$res=$s->get_result()->fetch_assoc();$s->close();
        return $res?:[];
    }

    public function responsables(int $idCliente): array
    {
        $sql="SELECT DISTINCT u.id_usuario,u.usuario FROM usuarios u
            INNER JOIN roles r ON r.id_rol=u.id_rol AND r.estado=1
            INNER JOIN modulos m ON m.codigo='BUZON_SUNAFIL' AND m.estado=1
            LEFT JOIN usuario_modulos um ON um.id_usuario=u.id_usuario AND um.id_modulo=m.id_modulo
            LEFT JOIN rol_modulos rm ON rm.id_rol=u.id_rol AND rm.id_modulo=m.id_modulo
            LEFT JOIN usuario_clientes uc ON uc.id_usuario=u.id_usuario AND uc.id_cliente=? AND uc.puede_ver=1
            WHERE u.estado=1 AND u.tipo_cuenta='EQUIPO' AND (u.acceso_todos_clientes=1 OR uc.id_cliente IS NOT NULL)
              AND COALESCE(um.puede_ver,rm.puede_ver,0)=1 AND COALESCE(um.puede_editar,rm.puede_editar,0)=1 ORDER BY u.usuario";
        $s=$this->conexion->prepare($sql);$s->bind_param('i',$idCliente);$s->execute();$res=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $res;
    }

    public function guardarAsignacion(int $idDocumento,int $idCliente,?int $responsable,int $actor): void
    {
        if($responsable!==null){
            $permitidos=array_column($this->responsables($idCliente),'id_usuario');
            if(!in_array($responsable,array_map('intval',$permitidos),true))throw new DomainException('Selecciona un usuario del equipo con acceso autorizado a este cliente y al Buzón SUNAFIL.');
        }
        $s=$this->conexion->prepare("INSERT INTO buzon_sunafil_casos (id_documento,id_cliente,id_responsable,estado,creado_por,modificado_por) VALUES (?,?,?,'ABIERTO',?,?) ON DUPLICATE KEY UPDATE id_responsable=VALUES(id_responsable),modificado_por=VALUES(modificado_por),actualizado_en=NOW()");
        $s->bind_param('iiiii',$idDocumento,$idCliente,$responsable,$actor,$actor);$s->execute();$s->close();
    }

    public function cambiarEstado(int $idDocumento,int $idCliente,int $actor,bool $cerrar): void
    {
        $estado=$cerrar?'CERRADO':'ABIERTO';
        $s=$this->conexion->prepare('INSERT INTO buzon_sunafil_casos (id_documento,id_cliente,estado,creado_por,modificado_por,cerrado_en) VALUES (?,?,?,?,?,IF(?=1,NOW(),NULL)) ON DUPLICATE KEY UPDATE estado=VALUES(estado),modificado_por=VALUES(modificado_por),cerrado_en=VALUES(cerrado_en),actualizado_en=NOW()');
        $flag=$cerrar?1:0;$s->bind_param('iisiii',$idDocumento,$idCliente,$estado,$actor,$actor,$flag);$s->execute();$s->close();
    }

    public function agregarAvance(int $idDocumento, int $idCliente, int $actor, string $contenido, bool $principal): void
    {
        $gestor = (int)$principal;
        $s = $this->conexion->prepare("INSERT INTO buzon_sunafil_avances(id_documento,id_cliente,id_usuario,contenido)
            SELECT id_documento,id_cliente,?,? FROM buzon_sunafil_casos
            WHERE id_documento=? AND id_cliente=? AND estado='ABIERTO'
              AND (?=1 OR id_responsable=?)");
        $s->bind_param('isiiii',$actor,$contenido,$idDocumento,$idCliente,$gestor,$actor);
        $s->execute();
        $registrado = $s->affected_rows === 1;
        $s->close();
        if (!$registrado) {
            throw new DomainException('El caso está cerrado o no está asignado a tu cuenta.');
        }
    }

    public function borrarPorCliente(int $cliente): void
    {
        foreach (['buzon_sunafil_avances', 'buzon_sunafil_casos'] as $tabla) {
            $existe = $this->conexion->query("SELECT 1 FROM information_schema.tables
                WHERE table_schema=DATABASE() AND table_name='{$tabla}'");
            if ($existe->num_rows > 0) {
                $s = $this->conexion->prepare("DELETE FROM {$tabla} WHERE id_cliente=?");
                $s->bind_param('i', $cliente);
                $s->execute();
                $s->close();
            }
        }
    }

    public function listarCasosVisibles(string $filtroClientes,int $actor,bool $principal): array
    {
        if(!$this->disponible())return [];
        $limite=$principal?'1=1':'cs.id_responsable='.(int)$actor;
        $sql="SELECT cs.id_documento,cs.id_cliente,cs.estado AS estado_caso,cs.id_responsable,cs.actualizado_en,
                     b.asunto,b.categoria,b.prioridad,c.razon_social,u.usuario AS responsable
              FROM buzon_sunafil_casos cs
              INNER JOIN buzon_sunafil b ON b.id_documento=cs.id_documento AND b.id_cliente=cs.id_cliente
              INNER JOIN clientes c ON c.id_cliente=cs.id_cliente
              LEFT JOIN usuarios u ON u.id_usuario=cs.id_responsable
              WHERE ($filtroClientes) AND ($limite)
              ORDER BY cs.estado='CERRADO',cs.actualizado_en DESC LIMIT 200";
        $r=$this->conexion->query($sql);return $r?$r->fetch_all(MYSQLI_ASSOC):[];
    }

    public function avances(int $idDocumento, int $idCliente): array
    {
        if(!$this->disponible())return [];
        $s=$this->conexion->prepare("SELECT a.id_avance,a.id_usuario,a.contenido,a.creado_en,COALESCE(u.usuario,'Usuario eliminado') AS usuario FROM buzon_sunafil_avances a LEFT JOIN usuarios u ON u.id_usuario=a.id_usuario WHERE a.id_documento=? AND a.id_cliente=? ORDER BY a.id_avance ASC");
        $s->bind_param('ii',$idDocumento,$idCliente);$s->execute();$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $rows;
    }
}
