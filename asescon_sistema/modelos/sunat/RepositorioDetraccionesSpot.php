<?php
declare(strict_types=1);

                                                                                           
final class RepositorioDetraccionesSpot
{
    public function __construct(private mysqli $db) {}

    public function disponible(): bool
    {
        $r = $this->db->query("SELECT COUNT(*) total FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='detracciones_archivos_sunat'");
        return $r !== false && (int)($r->fetch_assoc()['total'] ?? 0) === 1;
    }

                                                                                        
    public function listar(array $ids, string $periodo): array
    {
        if (!$ids || !$this->disponible()) return [];
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $lista = implode(',', $ids);
        $condicion = str_ends_with($periodo, '00')
            ? "a.periodo LIKE '" . $this->db->real_escape_string(substr($periodo, 0, 4)) . "%'"
            : "a.periodo='" . $this->db->real_escape_string($periodo) . "'";
        $res = $this->db->query("SELECT a.*,c.razon_social,c.ruc FROM detracciones_archivos_sunat a INNER JOIN clientes c ON c.id_cliente=a.id_cliente WHERE a.id_cliente IN ({$lista}) AND {$condicion} ORDER BY c.razon_social,a.fecha_deposito DESC,a.id_archivo_sunat DESC");
        if (!$res) throw new RuntimeException('No se pudo leer las constancias importadas.');
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function guardar(int $idCliente, string $ruc, array $filas, string $nombreArchivo): array
    {
        if (!$this->disponible()) throw new RuntimeException('Falta aplicar la migración de Detracciones (ver guía de instalación).');
        $sql = 'INSERT INTO detracciones_archivos_sunat (id_cliente,ruc,clave_origen,periodo,numero_constancia,fecha_deposito,monto,tipo_cuenta,numero_cuenta,documento,contraparte,modalidad,origen_archivo,datos_origen) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE periodo=VALUES(periodo),numero_constancia=VALUES(numero_constancia),fecha_deposito=VALUES(fecha_deposito),monto=VALUES(monto),tipo_cuenta=VALUES(tipo_cuenta),numero_cuenta=VALUES(numero_cuenta),documento=VALUES(documento),contraparte=VALUES(contraparte),modalidad=VALUES(modalidad),origen_archivo=VALUES(origen_archivo),datos_origen=VALUES(datos_origen),importado_en=NOW()';
        $s = $this->db->prepare($sql);
        if (!$s) throw new RuntimeException('No fue posible preparar la importación.');
        $this->db->begin_transaction();
        $cantidad = 0;
        try {
            foreach ($filas as $fila) {
                $clave = hash('sha256', implode('|', [$ruc, $fila['numero_constancia'], $fila['fecha_deposito'], $fila['documento'], $fila['numero_cuenta']]));
                $json = json_encode($fila['datos_origen'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $periodo = $fila['periodo']; $constancia = $fila['numero_constancia'];
                $fecha = $fila['fecha_deposito']; $monto = $fila['monto'];
                $tipo = $fila['tipo_cuenta']; $cuenta = $fila['numero_cuenta'];
                $documento = $fila['documento']; $contraparte = $fila['contraparte'];
                $modalidad = $fila['modalidad'];
                $s->bind_param('isssssssssssss', $idCliente, $ruc, $clave, $periodo, $constancia, $fecha, $monto, $tipo, $cuenta, $documento, $contraparte, $modalidad, $nombreArchivo, $json);
                $s->execute(); $cantidad++;
            }
            $s->close(); $this->db->commit();
            return ['procesadas'=>$cantidad];
        } catch (Throwable $e) {
            $s->close(); $this->db->rollback(); throw $e;
        }
    }
}
