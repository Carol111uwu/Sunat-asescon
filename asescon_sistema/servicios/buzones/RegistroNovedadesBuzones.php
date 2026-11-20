<?php
declare(strict_types=1);

/**
 * Un evento se crea por notificación realmente nueva respecto del último inventario.
 * El primer sondeo establece la línea base y NUNCA anuncia el historial.
 * Fallos del subsistema de avisos no abortan el guardado del buzón.
 */
final class RegistroNovedadesBuzones
{
    public function __construct(private mysqli $db) {}

    public function disponible(): bool
    {
        $r=$this->db->query("SHOW TABLES LIKE 'buzon_sondeos'");
        if (!$r || $r->num_rows===0) return false;
        $r=$this->db->query("SHOW TABLES LIKE 'buzon_novedades'");
        return $r && $r->num_rows>0;
    }

    public function ultimaConsultaExitosa(string $buzon,int $idCliente): ?string
    {
        if(!$this->disponible() || $idCliente<1 || !in_array($buzon,['SOL','SUNAFIL'],true))return null;
        $q=$this->db->prepare("SELECT ultima_consulta_exitosa FROM buzon_sondeos WHERE id_cliente=? AND buzon=? LIMIT 1");
        $q->bind_param('is',$idCliente,$buzon);$q->execute();
        $fila=$q->get_result()->fetch_assoc();$q->close();
        return $fila?((string)$fila['ultima_consulta_exitosa']):null;
    }

    public function marcadorAnterior(string $buzon,int $idCliente): int
    {
        if (!in_array($buzon,['SOL','SUNAFIL'],true) || $idCliente<1) return 0;
        $tabla=$buzon==='SOL'?'buzon_sol_sunat':'buzon_sunafil';
        $q=$this->db->prepare("SELECT COALESCE(MAX(id_documento),0) mayor FROM {$tabla} WHERE id_cliente=?");
        $q->bind_param('i',$idCliente); $q->execute();
        $max=(int)($q->get_result()->fetch_assoc()['mayor']??0);$q->close();
        return $max;
    }

    public function registrarExito(string $buzon,int $idCliente,int $mayorAnterior): void
    {
        if (!$this->disponible()) return;
        if (!in_array($buzon,['SOL','SUNAFIL'],true) || $idCliente<1) return;
        $tabla=$buzon==='SOL'?'buzon_sol_sunat':'buzon_sunafil';
        $this->db->begin_transaction();
        try {
            $q=$this->db->prepare("SELECT inicializado FROM buzon_sondeos WHERE id_cliente=? AND buzon=? FOR UPDATE");
            $q->bind_param('is',$idCliente,$buzon);$q->execute();
            $yaInicializado=$q->get_result()->fetch_assoc()!==null;$q->close();
            if($yaInicializado) {
                // El contador se basa en PK internas, no en asunto, fecha ni posición.
                $q=$this->db->prepare("INSERT IGNORE INTO buzon_novedades(buzon,id_cliente,id_documento)
                    SELECT ?, ?, b.id_documento FROM {$tabla} b
                    WHERE b.id_cliente=? AND b.id_documento > ?");
                $q->bind_param('siii',$buzon,$idCliente,$idCliente,$mayorAnterior);
                $q->execute();$q->close();
            }
            $q=$this->db->prepare("INSERT INTO buzon_sondeos(id_cliente,buzon,ultima_consulta_exitosa,inicializado)
                VALUES(?,?,NOW(),1)
                ON DUPLICATE KEY UPDATE ultima_consulta_exitosa=VALUES(ultima_consulta_exitosa),inicializado=1");
            $q->bind_param('is',$idCliente,$buzon);$q->execute();$q->close();
            $this->db->commit();
        } catch(Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
