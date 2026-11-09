<?php
declare(strict_types=1);
require_once __DIR__.'/../../servicios/clientes/BusquedaClientes.php';

final class PadronSsco
{
    public function __construct(private mysqli $conexion) {}

    public function recientes(): array
    {
        $total=(int)$this->conexion->query('SELECT COUNT(*) n FROM padron_ssco')->fetch_assoc()['n'];
        $filas=$this->conexion->query('SELECT ruc,razon_social,resolucion FROM padron_ssco ORDER BY fecha_publicacion DESC,id_registro DESC LIMIT 5')->fetch_all(MYSQLI_ASSOC);
        return compact('total','filas');
    }

    public function datos(string $buscar, int $pagina): array
    {
        $condicion=BusquedaClientes::condicionSql($buscar,['p.ruc','p.razon_social','p.resolucion','p.domicilio_fiscal','p.representante_legal']);
        $where=$condicion['sql'] ?: '1=1'; $parametros=$condicion['parametros'];
        $s=$this->conexion->prepare('SELECT COUNT(*) total FROM padron_ssco p WHERE '.$where);
        if ($parametros) { $s->bind_param(str_repeat('s',count($parametros)),...$parametros); }
        $s->execute(); $total=(int)$s->get_result()->fetch_assoc()['total']; $s->close();
        $paginas=max(1,(int)ceil($total/50)); $pagina=max(1,min($pagina,$paginas)); $desde=($pagina-1)*50;
        $s=$this->conexion->prepare('SELECT p.* FROM padron_ssco p WHERE '.$where.' ORDER BY fecha_publicacion DESC,razon_social,ruc LIMIT 50 OFFSET '.$desde);
        if ($parametros) { $s->bind_param(str_repeat('s',count($parametros)),...$parametros); }
        $s->execute(); $filas=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
        $ultima=$this->conexion->query('SELECT * FROM actualizacion_padron_ssco WHERE id=1')->fetch_assoc();
        return compact('filas','total','pagina','paginas','ultima');
    }

    public function guardar(array $registros, string $origen): int
    {
        if (!$registros) { throw new DomainException('El padrón no tiene registros.'); }
        $this->conexion->begin_transaction();
        try {
                                                                                          
            $s=$this->conexion->prepare('INSERT INTO padron_ssco(ruc,razon_social,resolucion,fecha_firme,fecha_publicacion,domicilio_fiscal,representante_legal) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE razon_social=VALUES(razon_social),fecha_firme=VALUES(fecha_firme),fecha_publicacion=VALUES(fecha_publicacion),domicilio_fiscal=VALUES(domicilio_fiscal),representante_legal=VALUES(representante_legal)');
            foreach ($registros as $d) {
                $s->bind_param('sssssss',$d['ruc'],$d['razon_social'],$d['resolucion'],$d['fecha_firme'],$d['fecha_publicacion'],$d['domicilio_fiscal'],$d['representante_legal']);
                $s->execute();
            }
            $s->close(); $n=count($registros);
            $s=$this->conexion->prepare('INSERT INTO actualizacion_padron_ssco(id,origen,registros) VALUES(1,?,?) ON DUPLICATE KEY UPDATE origen=VALUES(origen),registros=VALUES(registros),actualizado_en=NOW()');
            $s->bind_param('si',$origen,$n);$s->execute();$s->close();$this->conexion->commit();return $n;
        } catch (Throwable $e) { $this->conexion->rollback();throw $e; }
    }
}
