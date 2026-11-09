<?php

declare(strict_types=1);

final class Liquidacion
{
    public function __construct(private mysqli $conexion)
    {
    }
    public function listar(string $filtro, string $periodo = ''): array
    {
        $extra = '';
        if ($periodo !== '') {
            if (preg_match('/^(20\d{2})00$/', $periodo, $m)) {
                $y = $this->conexion->real_escape_string($m[1]);
                $extra = " AND l.periodo LIKE '" . $y . "%'";
            } else {
                $extra = " AND l.periodo='" . $this->conexion->real_escape_string($periodo) . "'";
            }
        }
        $sql = "SELECT l.*,c.ruc,c.razon_social,c.nombre_comercial,
                     COALESCE(l.impuesto_resultado,0) AS impuesto_resultado_mostrar,
                     COALESCE(l.saldo_anterior,0) AS saldo_anterior_mostrar,
                     COALESCE(l.credito_fiscal_anterior,0) AS credito_fiscal_anterior_mostrar,
                     CASE WHEN COALESCE(l.igv_pagar_sf,0)=0 AND COALESCE(l.igv,0)<>0 THEN l.igv ELSE COALESCE(l.igv_pagar_sf,0) END AS igv_pagar_sf_mostrar
              FROM liquidaciones l INNER JOIN clientes c ON c.id_cliente=l.id_cliente WHERE {$filtro}{$extra} ORDER BY l.periodo DESC,c.razon_social";
        $r = $this->conexion->query($sql);
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }
    public function obtener(int $cliente,string $periodo):?array
    {
        $s=$this->conexion->prepare('SELECT * FROM liquidaciones WHERE id_cliente=? AND periodo=? LIMIT 1');$s->bind_param('is',$cliente,$periodo);$s->execute();$d=$s->get_result()->fetch_assoc();$s->close();return $d;
    }
    public function guardar(array $d): void
    {
        $total = $d['total'] ?? ($d['igv'] + $d['renta'] + $d['essalud'] + $d['onp']);
        $imp = (float)($d['impuesto_resultado'] ?? $d['renta']);
        $saldo = (float)($d['saldo_anterior'] ?? 0);
        $cred = (float)($d['credito_fiscal_anterior'] ?? 0);
        $igvSf = (float)($d['igv_pagar_sf'] ?? $d['igv']);
        $s = $this->conexion->prepare("INSERT INTO liquidaciones(id_cliente,periodo,igv,renta,essalud,onp,total,impuesto_resultado,saldo_anterior,credito_fiscal_anterior,igv_pagar_sf,estado,fecha_liquidacion,observaciones) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE igv=VALUES(igv),renta=VALUES(renta),essalud=VALUES(essalud),onp=VALUES(onp),total=VALUES(total),impuesto_resultado=VALUES(impuesto_resultado),saldo_anterior=VALUES(saldo_anterior),credito_fiscal_anterior=VALUES(credito_fiscal_anterior),igv_pagar_sf=VALUES(igv_pagar_sf),estado=VALUES(estado),fecha_liquidacion=VALUES(fecha_liquidacion),observaciones=VALUES(observaciones)");
        if (!$s) {
            throw new RuntimeException('No se pudo guardar la liquidación.');
        }
        $s->bind_param('isdddddddddsss', $d['id_cliente'], $d['periodo'], $d['igv'], $d['renta'], $d['essalud'], $d['onp'], $total, $imp, $saldo, $cred, $igvSf, $d['estado'], $d['fecha_liquidacion'], $d['observaciones']);
        $s->execute();
        $s->close();
    }
}
