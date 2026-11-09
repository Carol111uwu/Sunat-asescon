<?php

declare(strict_types=1);

final class VencimientoSunat
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(string $filtro, string $estado = ''): array
    {
        $extra = $estado !== '' ? " AND v.estado='" . $this->conexion->real_escape_string($estado) . "'" : '';
        $r = $this->conexion->query("SELECT v.*,c.ruc,c.razon_social,c.ultimo_digito_ruc FROM vencimientos_sunat v INNER JOIN clientes c ON c.id_cliente=v.id_cliente WHERE {$filtro}{$extra} ORDER BY v.fecha_vencimiento,v.id_vencimiento");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }

                                                                                              
    public function cronograma(string $filtroClientes, int $anio, int $mes): array
    {
        if ($anio < 2021 || $anio > 2100 || $mes < 0 || $mes > 12) return [];
        $fechaSql = 'YEAR(cr.fecha_vencimiento)=' . $anio;
        if ($mes > 0) $fechaSql .= ' AND MONTH(cr.fecha_vencimiento)=' . $mes;
        $filas=[];
        try {
            $r = $this->conexion->query(
                "SELECT c.id_cliente,c.ruc,c.razon_social,cr.periodo,cr.formulario,cr.fecha_vencimiento
                 FROM cronograma_sunat cr INNER JOIN clientes c ON c.ultimo_digito_ruc=cr.ultimo_digito_ruc
                 WHERE {$fechaSql} AND c.estado='ACTIVO' AND ({$filtroClientes})
                 ORDER BY cr.fecha_vencimiento,c.razon_social LIMIT 500"
            );
            while ($r && ($fila=$r->fetch_assoc())) $filas[]=$fila;
        } catch (mysqli_sql_exception $e) {
                                                                                    
        }
        if ($filas) return $filas;
        require_once __DIR__.'/../../servicios/sunat/CronogramaOficial2026.php';
        $r = $this->conexion->query(
            "SELECT c.id_cliente,c.ruc,c.razon_social FROM clientes c
              WHERE c.estado='ACTIVO' AND ({$filtroClientes}) ORDER BY c.razon_social LIMIT 250"
        );
        while ($r && ($c=$r->fetch_assoc())) {
            foreach(CronogramaOficial2026::periodos() as $periodo) {
                $fecha=CronogramaOficial2026::fecha($periodo,(string)$c['ruc']);
                if ($fecha===null || (int)substr($fecha,0,4)!==$anio || ($mes>0 && (int)substr($fecha,5,2)!==$mes)) continue;
                $filas[]=$c+['periodo'=>$periodo,'formulario'=>'General 2026','fecha_vencimiento'=>$fecha];
                if (count($filas)>=500) break 2;
            }
        }
        usort($filas,static fn($a,$b)=>strcmp((string)$a['fecha_vencimiento'],(string)$b['fecha_vencimiento']));
        return $filas;
    }

    public function actualizarEstado(int $id, string $estado, string $filtroClientes): void
    {
        if ($id <= 0 || !in_array($estado, ['PENDIENTE','CUMPLIDO'], true)) {
            throw new InvalidArgumentException('El vencimiento o estado no es válido.');
        }
        $s = $this->conexion->prepare("UPDATE vencimientos_sunat v INNER JOIN clientes c ON c.id_cliente=v.id_cliente SET v.estado=? WHERE v.id_vencimiento=? AND ({$filtroClientes})");
        if (!$s) throw new RuntimeException('No se pudo actualizar el vencimiento.');
        $s->bind_param('si', $estado, $id);
        if (!$s->execute()) throw new RuntimeException('La actualización de vencimiento falló.');
        $s->close();
    }
}
