<?php

declare(strict_types=1);

final class AlertaSunat
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(string $filtro, string $estado = ''): array
    {
        $extra = $estado !== '' ? " AND a.estado='" . $this->conexion->real_escape_string($estado) . "'" : '';
        $r = $this->conexion->query("SELECT a.*,c.ruc,c.razon_social FROM alertas_sunat a LEFT JOIN clientes c ON c.id_cliente=a.id_cliente WHERE ({$filtro}){$extra} ORDER BY a.estado='ATENDIDA',a.fecha_alerta DESC");
        $x = [];
        if ($r) {
            while ($f = $r->fetch_assoc()) {
                $x[] = $f;
            }
        }
return $x;
    }

    public function listarBuzon(string $buzon, string $filtro, string $estado): array
    {
        $tabla = match ($buzon) {
            'SOL' => 'buzon_sol_sunat',
            'SUNAFIL' => 'buzon_sunafil',
        };
        $estadoBuzon = match ($estado) {
            'PENDIENTE' => " AND b.estado='NO_REVISADO'",
            'ATENDIDA' => " AND b.estado='REVISADO'",
            default => '',
        };
        $sql = "SELECT b.id_cliente, b.id_documento, c.ruc, c.razon_social, b.asunto AS titulo,
                COALESCE(b.fecha_recepcion,b.fecha_documento,b.fecha_sincronizacion) AS fecha_alerta,
                CASE WHEN b.estado='REVISADO' THEN 'ATENDIDA' ELSE 'PENDIENTE' END AS estado,
                COALESCE(NULLIF(b.prioridad,''),'NORMAL') AS nivel
                FROM {$tabla} b INNER JOIN clientes c ON c.id_cliente=b.id_cliente
                WHERE ({$filtro}){$estadoBuzon}
                ORDER BY fecha_alerta DESC, b.id_documento DESC LIMIT 100";
        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function atender(int $id, string $filtroClientes): void
    {
        $s = $this->conexion->prepare("UPDATE alertas_sunat a LEFT JOIN clientes c ON c.id_cliente=a.id_cliente SET a.estado='ATENDIDA',a.fecha_atencion=NOW() WHERE a.id_alerta=? AND ({$filtroClientes})");
        $s->bind_param('i', $id);
        $s->execute();
        $s->close();
    }
}
