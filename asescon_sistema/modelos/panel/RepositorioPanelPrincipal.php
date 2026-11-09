<?php

declare(strict_types=1);

final class RepositorioPanelPrincipal
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function resumen(string $filtroClientes, int $idUsuario): array
    {
        $totalClientes = $this->escalar("SELECT COUNT(*) n FROM clientes c WHERE c.estado='ACTIVO' AND {$filtroClientes}");
        $riesgoAlto = $this->escalar("SELECT COUNT(DISTINCT d.id_cliente) n FROM declaraciones_sunat d INNER JOIN clientes c ON c.id_cliente=d.id_cliente WHERE {$filtroClientes} AND c.estado='ACTIVO' AND d.fecha_vencimiento<CURDATE() AND (d.estado_presentacion<>'PRESENTADA' OR d.estado_pago NOT IN ('PAGADO','NO_APLICA'))");
        $venceHoySunat = $this->escalar("SELECT COUNT(*) n FROM vencimientos_sunat v INNER JOIN clientes c ON c.id_cliente=v.id_cliente WHERE {$filtroClientes} AND v.fecha_vencimiento=CURDATE() AND v.estado NOT IN ('ATENDIDO','PAGADO','COMPLETADO','CUMPLIDO')");
        $venceHoyTareas = $this->escalar("SELECT COUNT(*) n FROM tareas t LEFT JOIN clientes c ON c.id_cliente=t.id_cliente WHERE t.fecha_vencimiento=CURDATE() AND t.estado<>'COMPLETADA' AND ((t.id_cliente IS NOT NULL AND {$filtroClientes}) OR t.id_usuario=".(int)$idUsuario.")");
        $proximos7 = $this->escalar("SELECT COUNT(*) n FROM vencimientos_sunat v INNER JOIN clientes c ON c.id_cliente=v.id_cliente WHERE {$filtroClientes} AND v.fecha_vencimiento>CURDATE() AND v.fecha_vencimiento<=DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND v.estado NOT IN ('ATENDIDO','PAGADO','COMPLETADO','CUMPLIDO')");
        $sinLeer = $this->escalar("SELECT COUNT(*) n FROM buzon_sol_sunat b LEFT JOIN clientes c ON c.id_cliente=b.id_cliente WHERE b.estado='NO_REVISADO' AND ({$filtroClientes})");
        $clientesAlDia = max(0, $totalClientes - $riesgoAlto);
        return compact('totalClientes','riesgoAlto','venceHoySunat','venceHoyTareas','proximos7','sinLeer','clientesAlDia') + [
            'porcentajeAlDia' => $totalClientes > 0 ? (int)round(($clientesAlDia / $totalClientes) * 100) : 0,
        ];
    }

    public function proximosVencimientos(string $filtroClientes): array
    {
        return $this->filas("SELECT c.razon_social,v.tipo_obligacion,v.periodo,v.fecha_vencimiento,v.estado,DATEDIFF(v.fecha_vencimiento,CURDATE()) AS dias FROM vencimientos_sunat v INNER JOIN clientes c ON c.id_cliente=v.id_cliente WHERE {$filtroClientes} AND v.estado NOT IN ('ATENDIDO','PAGADO','COMPLETADO','CUMPLIDO') ORDER BY v.fecha_vencimiento ASC LIMIT 5");
    }

    public function alertas(string $filtroClientes): array
    {
        return $this->filas("SELECT a.titulo,a.nivel,a.fecha_alerta FROM alertas_sunat a LEFT JOIN clientes c ON c.id_cliente=a.id_cliente WHERE a.estado<>'ATENDIDA' AND ({$filtroClientes}) ORDER BY a.fecha_alerta DESC LIMIT 5");
    }

    public function prioridadesHoy(string $filtroClientes, int $idUsuario): array
    {
        return $this->filas("SELECT t.id_tarea,t.titulo,t.prioridad,t.fecha_vencimiento,c.razon_social FROM tareas t LEFT JOIN clientes c ON c.id_cliente=t.id_cliente WHERE t.estado<>'COMPLETADA' AND (t.fecha_vencimiento=CURDATE() OR t.prioridad='ALTA') AND ((t.id_cliente IS NOT NULL AND {$filtroClientes}) OR t.id_usuario=".(int)$idUsuario.") ORDER BY (t.prioridad='ALTA') DESC,t.fecha_vencimiento ASC LIMIT 5");
    }

    public function idsClientesActivos(string $filtroClientes): array
    {
        $ids = [];
        $res = $this->conexion->query("SELECT c.id_cliente FROM clientes c WHERE c.estado='ACTIVO' AND {$filtroClientes}");
        if ($res) while ($fila = $res->fetch_assoc()) $ids[(int)$fila['id_cliente']] = true;
        return $ids;
    }

    public function sinLeerBuzonSol(string $filtroClientes): int
    {
        return $this->escalar("SELECT COUNT(*) AS n FROM buzon_sol_sunat b LEFT JOIN clientes c ON c.id_cliente=b.id_cliente WHERE b.estado='NO_REVISADO' AND ({$filtroClientes})");
    }

    private function escalar(string $sql): int
    {
        $res = $this->conexion->query($sql);
        return $res ? (int)($res->fetch_assoc()['n'] ?? 0) : 0;
    }

    private function filas(string $sql): array
    {
        $salida = [];
        $res = $this->conexion->query($sql);
        if ($res) while ($fila = $res->fetch_assoc()) $salida[] = $fila;
        return $salida;
    }
}
