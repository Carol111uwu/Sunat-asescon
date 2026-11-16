<?php
declare(strict_types=1);

require_once __DIR__.'/../../servicios/sunat/PresentacionDatosSire.php';

final class RepositorioLoteSire
{
    public function __construct(private mysqli $conexion) {}

    public function obtener(string $libro, string $periodo, int $cliente, bool $excluir, string $tipo,
        string $filtroClientes, int $offset, int $limite): array
    {
        [$tabla, $detalle, $idCabecera, $idComprobante] = $libro === 'RCE'
            ? ['rce', 'rce_detalle', 'id_rce', 'id_rce_detalle']
            : ['rvie', 'rvie_detalle', 'id_rvie', 'id_rvie_detalle'];
        $where = ["c.estado='ACTIVO'", '(' . $filtroClientes . ')'];
        $tipos = '';
        $valores = [];
        if ($cliente > 0) {
            $where[] = 'c.id_cliente = ?';
            $tipos .= 'i';
            $valores[] = $cliente;
        }
        if (substr($periodo, 4) === '00') {
            $where[] = "h.periodo LIKE CONCAT(?, '%')";
            $tipos .= 's';
            $valores[] = substr($periodo, 0, 4);
        } else {
            $where[] = 'h.periodo = ?';
            $tipos .= 's';
            $valores[] = $periodo;
        }
        if ($excluir) {
            [$desde, $hasta] = PresentacionDatosSire::limitesEmision($periodo);
            $where[] = 'd.fecha_emision >= ? AND d.fecha_emision < ?';
            $tipos .= 'ss';
            array_push($valores, $desde, $hasta);
        }
        if ($tipo !== '') {
            $where[] = "COALESCE(NULLIF(TRIM(d.tipo_comprobante), ''), '—') = ?";
            $tipos .= 's';
            $valores[] = $tipo;
        }
        $from = " FROM {$detalle} d JOIN {$tabla} h ON h.{$idCabecera} = d.{$idCabecera}
                  JOIN clientes c ON c.id_cliente = h.id_cliente
                  WHERE " . implode(' AND ', $where);

        $total = (int) ($this->consultarLote('SELECT COUNT(*) AS total' . $from, $tipos, $valores)->fetch_assoc()['total'] ?? 0);
        $sql = "SELECT d.*, h.periodo, h.id_cliente, c.ruc AS ruc_empresa,
                       c.razon_social AS empresa_asescon, d.{$idComprobante} AS id_detalle"
            . $from . " ORDER BY c.id_cliente, h.periodo, d.{$idComprobante} LIMIT ? OFFSET ?";
        return ['total' => $total, 'filas' => $this->consultarLote($sql, $tipos . 'ii', [...$valores, $limite, $offset])];
    }

    private function consultarLote(string $sql, string $tipos, array $valores): mysqli_result
    {
        $stmt = $this->conexion->prepare($sql);
        try {
            $stmt->bind_param($tipos, ...$valores);
            $stmt->execute();
            return $stmt->get_result();
        } finally {
            $stmt->close();
        }
    }
}
