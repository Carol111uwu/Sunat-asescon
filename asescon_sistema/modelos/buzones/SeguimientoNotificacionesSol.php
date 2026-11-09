<?php
declare(strict_types=1);

final class SeguimientoNotificacionesSol
{
    public function __construct(private mysqli $conexion) {}

    public function disponible(): bool
    {
        try {
            $r = $this->conexion->query("SHOW TABLES LIKE 'buzon_sol_casos'");
            $r2 = $this->conexion->query("SHOW TABLES LIKE 'buzon_sol_avances'");
            return $r && $r->num_rows > 0 && $r2 && $r2->num_rows > 0;
        } catch (Throwable) { return false; }
    }

    public function habilitar(): void
    {
        $consultas = [
            "CREATE TABLE IF NOT EXISTS buzon_sol_casos (id_documento BIGINT NOT NULL PRIMARY KEY,id_cliente INT NOT NULL,id_responsable INT NULL,estado VARCHAR(20) NOT NULL DEFAULT 'ABIERTO',creado_por INT NOT NULL,modificado_por INT NOT NULL,creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,cerrado_en DATETIME NULL,INDEX idx_casos_cliente(id_cliente),INDEX idx_casos_responsable(id_responsable)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS buzon_sol_avances (id_avance BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,id_documento BIGINT NOT NULL,id_cliente INT NOT NULL,id_usuario INT NOT NULL,contenido LONGTEXT NOT NULL,creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_avances_documento(id_documento,creado_en),INDEX idx_avances_cliente(id_cliente)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($consultas as $sql) { if (!$this->conexion->query($sql)) throw new RuntimeException('No se pudo preparar el seguimiento de notificaciones.'); }
        $r=$this->conexion->query("SHOW COLUMNS FROM buzon_sol_sunat LIKE 'observaciones'");
        $col=$r?$r->fetch_assoc():null;
        if($col && strtolower((string)$col['Type'])!=='longtext'){
            if(!$this->conexion->query('ALTER TABLE buzon_sol_sunat MODIFY COLUMN observaciones LONGTEXT NULL'))
                throw new RuntimeException('No se pudo ampliar el contenido de las notificaciones.');
        }
    }

    public function caso(int $idDocumento): array
    {
        if (!$this->disponible()) return [];
        $s=$this->conexion->prepare('SELECT cs.*,u.usuario AS responsable FROM buzon_sol_casos cs LEFT JOIN usuarios u ON u.id_usuario=cs.id_responsable WHERE cs.id_documento=?');
        $s->bind_param('i',$idDocumento);$s->execute();$res=$s->get_result()->fetch_assoc();$s->close();
        return $res?:[];
    }

    public function responsables(int $idCliente): array
    {
        $sql="SELECT DISTINCT u.id_usuario,u.usuario FROM usuarios u
            INNER JOIN roles r ON r.id_rol=u.id_rol
            INNER JOIN modulos m ON m.codigo='BUZON_SOL' AND m.estado=1
            LEFT JOIN usuario_modulos um ON um.id_usuario=u.id_usuario AND um.id_modulo=m.id_modulo
            LEFT JOIN rol_modulos rm ON rm.id_rol=u.id_rol AND rm.id_modulo=m.id_modulo
            LEFT JOIN usuario_clientes uc ON uc.id_usuario=u.id_usuario AND uc.id_cliente=? AND uc.puede_ver=1
            WHERE u.estado=1 AND u.tipo_cuenta='EQUIPO' AND (u.acceso_todos_clientes=1 OR uc.id_cliente IS NOT NULL)
              AND COALESCE(um.puede_ver,rm.puede_ver,0)=1 ORDER BY u.usuario";
        $s=$this->conexion->prepare($sql);$s->bind_param('i',$idCliente);$s->execute();$res=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $res;
    }

    public function guardarAsignacion(int $idDocumento,int $idCliente,?int $responsable,int $actor): void
    {
        if($responsable!==null){
            $permitidos=array_column($this->responsables($idCliente),'id_usuario');
            if(!in_array($responsable,array_map('intval',$permitidos),true))throw new DomainException('Selecciona un usuario del equipo con acceso autorizado a este cliente y al Buzón SOL.');
        }
        $s=$this->conexion->prepare("INSERT INTO buzon_sol_casos (id_documento,id_cliente,id_responsable,estado,creado_por,modificado_por) VALUES (?,?,?,'ABIERTO',?,?) ON DUPLICATE KEY UPDATE id_responsable=VALUES(id_responsable),modificado_por=VALUES(modificado_por),actualizado_en=NOW()");
        $s->bind_param('iiiii',$idDocumento,$idCliente,$responsable,$actor,$actor);$s->execute();$s->close();
    }

    public function cambiarEstado(int $idDocumento,int $idCliente,int $actor,bool $cerrar): void
    {
        $estado=$cerrar?'CERRADO':'ABIERTO';
        $s=$this->conexion->prepare('INSERT INTO buzon_sol_casos (id_documento,id_cliente,estado,creado_por,modificado_por,cerrado_en) VALUES (?,?,?,?,?,IF(?=1,NOW(),NULL)) ON DUPLICATE KEY UPDATE estado=VALUES(estado),modificado_por=VALUES(modificado_por),cerrado_en=VALUES(cerrado_en),actualizado_en=NOW()');
        $flag=$cerrar?1:0;$s->bind_param('iisiii',$idDocumento,$idCliente,$estado,$actor,$actor,$flag);$s->execute();$s->close();
    }

    public function agregarAvance(int $idDocumento,int $idCliente,int $actor,string $contenido): void
    {
        $s=$this->conexion->prepare('INSERT INTO buzon_sol_avances(id_documento,id_cliente,id_usuario,contenido) VALUES (?,?,?,?)');
        $s->bind_param('iiis',$idDocumento,$idCliente,$actor,$contenido);$s->execute();$s->close();
    }

    public function listarCasosVisibles(string $filtroClientes,int $actor,bool $principal): array
    {
        if(!$this->disponible())return [];
        $limite=$principal?'1=1':'cs.id_responsable='.(int)$actor;
        $sql="SELECT cs.id_documento,cs.id_cliente,cs.estado AS estado_caso,cs.id_responsable,cs.actualizado_en,
                     b.asunto,b.categoria,b.prioridad,c.razon_social,u.usuario AS responsable
              FROM buzon_sol_casos cs
              INNER JOIN buzon_sol_sunat b ON b.id_documento=cs.id_documento AND b.id_cliente=cs.id_cliente
              INNER JOIN clientes c ON c.id_cliente=cs.id_cliente
              LEFT JOIN usuarios u ON u.id_usuario=cs.id_responsable
              WHERE ($filtroClientes) AND ($limite)
              ORDER BY cs.estado='CERRADO',cs.actualizado_en DESC LIMIT 200";
        $r=$this->conexion->query($sql);return $r?$r->fetch_all(MYSQLI_ASSOC):[];
    }

    public function avances(int $idDocumento): array
    {
        if(!$this->disponible())return [];
        $s=$this->conexion->prepare('SELECT a.id_avance,a.id_usuario,a.contenido,a.creado_en,u.usuario FROM buzon_sol_avances a INNER JOIN usuarios u ON u.id_usuario=a.id_usuario WHERE a.id_documento=? ORDER BY a.id_avance ASC');
        $s->bind_param('i',$idDocumento);$s->execute();$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $rows;
    }
}
