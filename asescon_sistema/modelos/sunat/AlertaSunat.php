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

    public function atender(int $id, string $filtroClientes): void
    {
        $s = $this->conexion->prepare("UPDATE alertas_sunat a LEFT JOIN clientes c ON c.id_cliente=a.id_cliente SET a.estado='ATENDIDA',a.fecha_atencion=NOW() WHERE a.id_alerta=? AND ({$filtroClientes})");
        $s->bind_param('i', $id);
        $s->execute();
        $s->close();
    }
}
