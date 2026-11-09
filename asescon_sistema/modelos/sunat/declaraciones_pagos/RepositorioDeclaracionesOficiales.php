<?php
declare(strict_types=1);

require_once __DIR__.'/RepositorioHistoricoSunat.php';
final class RepositorioDeclaracionesOficiales
{
    private ?RepositorioHistoricoSunat $historico=null;
    public function __construct(private mysqli $db,?mysqli $historico=null)
    {
        if($historico!==null){$this->historico=new RepositorioHistoricoSunat($historico);return;}
        $privada=asesconCargarConfiguracionPrivada();
        if(!empty($privada['db_sunat']['base_datos'])||getenv('ASESCON_DB_SUNAT_NAME'))$this->historico=new RepositorioHistoricoSunat();
    }
    private function cliente(int $id):array
    {
        $s=$this->db->prepare('SELECT id_cliente,ruc,razon_social FROM clientes WHERE id_cliente=?');$s->bind_param('i',$id);$s->execute();$c=$s->get_result()->fetch_assoc();$s->close();
        if(!$c)throw new DomainException('Empresa no disponible.');return $c;
    }
    public function disponible(): bool
    {
        if($this->historico)return $this->historico->disponible();
        $r=$this->db->query("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('declaraciones_oficiales_sunat','consultas_declaraciones_sunat')");
        return (int)$r->fetch_assoc()['n']===2;
    }
    public function clientes(string $filtro): array
    {
        return $this->db->query("SELECT c.id_cliente,c.ruc,c.razon_social,c.correo FROM clientes c WHERE c.estado='ACTIVO' AND ({$filtro}) ORDER BY c.razon_social")->fetch_all(MYSQLI_ASSOC);
    }
    public function guardar(int $cliente,array $consulta): array
    {
        $c=$this->cliente($cliente);
        foreach($consulta['filas'] as $fila)if((string)($fila['numRuc']??'')!==(string)$c['ruc'])throw new DomainException('La consulta contiene otro RUC.');
        if($this->historico)return $this->historico->guardar($c,$consulta,(int)($_SESSION['id_usuario']??0));
        $this->db->begin_transaction();
        try {
            $s=$this->db->prepare('INSERT INTO declaraciones_oficiales_sunat(id_cliente,formulario,numero_orden,periodo,datos_oficiales,consultado_en) VALUES(?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE datos_oficiales=VALUES(datos_oficiales),consultado_en=UTC_TIMESTAMP()');
            foreach ($consulta['filas'] as $fila) {
                $json=json_encode($fila,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$form=(string)$fila['codFor'];$orden=(string)$fila['numOrd'];$periodo=(string)$fila['perTri'];
                $s->bind_param('issss',$cliente,$form,$orden,$periodo,$json);$s->execute();
            }
            $s->close();
            $json=json_encode($consulta,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $s=$this->db->prepare('INSERT INTO consultas_declaraciones_sunat(id_cliente,consulta_oficial,consultado_en) VALUES(?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE consulta_oficial=VALUES(consulta_oficial),consultado_en=UTC_TIMESTAMP()');
            $s->bind_param('is',$cliente,$json);$s->execute();$s->close();$this->db->commit();return $consulta;
        } catch (Throwable $e) {$this->db->rollback();throw $e;}
    }
    public function ultima(int $cliente): ?array
    {
        if($this->historico){$c=$this->cliente($cliente);$guardado=$this->historico->ultima($cliente,(string)$c['ruc']);if($guardado!==null)return $guardado;if(!$this->legacyDisponible())return null;}
        $s=$this->db->prepare('SELECT consulta_oficial FROM consultas_declaraciones_sunat WHERE id_cliente=?');$s->bind_param('i',$cliente);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();
        return $r?json_decode($r['consulta_oficial'],true,64,JSON_THROW_ON_ERROR):null;
    }
    public function obtener(int $cliente,string $form,string $orden): ?array
    {
        if($this->historico){$c=$this->cliente($cliente);$guardado=$this->historico->obtener($cliente,(string)$c['ruc'],$form,$orden);if($guardado!==null)return $guardado;if(!$this->legacyDisponible())return null;}
        $s=$this->db->prepare('SELECT datos_oficiales,detalle_oficial FROM declaraciones_oficiales_sunat WHERE id_cliente=? AND formulario=? AND numero_orden=?');
        $s->bind_param('iss',$cliente,$form,$orden);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();
        if(!$r)return null;
        $detalle=$r['detalle_oficial']?json_decode($r['detalle_oficial'],true,64,JSON_THROW_ON_ERROR):null;
        if($this->historico){$nuevo=$this->historico->obtenerDetalle($cliente,(string)$c['ruc'],$form,$orden);if($nuevo!==null)$detalle=$nuevo;}
        return ['fila'=>json_decode($r['datos_oficiales'],true,64,JSON_THROW_ON_ERROR),'detalle'=>$detalle];
    }
    public function guardarDetalle(int $cliente,string $form,string $orden,array $detalle): void
    {
        if($this->historico){$c=$this->cliente($cliente);$this->historico->guardarDetalle($cliente,(string)$c['ruc'],$form,$orden,$detalle);return;}
        $json=json_encode($detalle,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $s=$this->db->prepare('UPDATE declaraciones_oficiales_sunat SET detalle_oficial=?,detalle_consultado_en=UTC_TIMESTAMP() WHERE id_cliente=? AND formulario=? AND numero_orden=?');
        $s->bind_param('siss',$json,$cliente,$form,$orden);$s->execute();$s->close();
    }
    private function legacyDisponible():bool
    {
        $r=$this->db->query("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('declaraciones_oficiales_sunat','consultas_declaraciones_sunat')");
        return (int)$r->fetch_assoc()['n']===2;
    }

}
