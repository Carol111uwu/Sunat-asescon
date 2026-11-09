<?php

declare(strict_types=1);

final class Informe
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(string $filtroClientes, array $filtros = []): array
    {
        $where = [$filtroClientes];
        $idCliente = (int)($filtros['id_cliente'] ?? 0);
        if ($idCliente > 0) {
            $where[] = 'i.id_cliente=' . $idCliente;
        }

        $responsable = (string)($filtros['responsable'] ?? '');
        if ($responsable !== '') {
            if ($responsable === 'AUXILIARES') {
                $where[] = "rresp.codigo='OPERADOR'";
            } elseif (preg_match('/^U:(\d+)$/', $responsable, $m)) {
                $where[] = 'i.id_responsable=' . (int)$m[1];
            }
        }

        $fechaInicio = trim((string)($filtros['fecha_inicio'] ?? ''));
        $fechaFin = trim((string)($filtros['fecha_fin'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) {
            $where[] = "i.fecha_generacion>='" . $this->conexion->real_escape_string($fechaInicio) . "'";
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
            $where[] = "i.fecha_generacion<='" . $this->conexion->real_escape_string($fechaFin) . "'";
        }

        $firma = trim((string)($filtros['firma'] ?? ''));
        if ($firma !== '') {
            $where[] = "i.firma_informe='" . $this->conexion->real_escape_string($firma) . "'";
        }

        $buscar = trim((string)($filtros['q'] ?? ''));
        if ($buscar !== '') {
            $b = $this->conexion->real_escape_string('%' . $buscar . '%');
            $where[] = "(c.ruc LIKE '{$b}' OR c.razon_social LIKE '{$b}' OR i.titulo LIKE '{$b}')";
        }

        $sql = "SELECT i.*,c.ruc,c.razon_social,
                       COALESCE(NULLIF(er.nombre,''),ur.usuario) AS responsable_nombre,
                       rresp.nombre AS responsable_rol
                FROM informes_entregables i
                INNER JOIN clientes c ON c.id_cliente=i.id_cliente
                LEFT JOIN usuarios ur ON ur.id_usuario=i.id_responsable
                LEFT JOIN equipo er ON er.id_usuario=ur.id_usuario
                LEFT JOIN roles rresp ON rresp.id_rol=ur.id_rol
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE(i.fecha_generacion,'1900-01-01') DESC,i.id_informe DESC";
        $r = $this->conexion->query($sql);
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }

    public function crear(int $c, string $titulo, ?string $tipo, ?string $periodo, ?string $fecha, ?int $responsable, ?string $firma): void
    {
        $s = $this->conexion->prepare("INSERT INTO informes_entregables(id_cliente,titulo,tipo_informe,periodo,fecha_generacion,estado,id_responsable,firma_informe) VALUES(?,?,?,?,?,'PENDIENTE',?,?)");
        if (!$s) {
            throw new RuntimeException('No se pudo preparar el informe.');
        }
        $s->bind_param('issssis', $c, $titulo, $tipo, $periodo, $fecha, $responsable, $firma);
        if (!$s->execute()) {
            $e = $s->error;
            $s->close();
            throw new RuntimeException($e);
        }
        $s->close();
    }
}
