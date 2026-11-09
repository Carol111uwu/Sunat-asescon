<?php

declare(strict_types=1);
require_once __DIR__.'/../../servicios/sunat/PeriodosSire.php';

final class DeclaracionSunat
{
    public function __construct(private mysqli $conexion)
    {
    }
    public function obtener(int $id,string $filtro):?array
    {
        $s=$this->conexion->prepare("SELECT d.*,c.ruc,c.razon_social FROM declaraciones_sunat d INNER JOIN clientes c ON c.id_cliente=d.id_cliente WHERE d.id_declaraciones_sunat=? AND ({$filtro}) LIMIT 1");
        $s->bind_param('i',$id);$s->execute();$d=$s->get_result()->fetch_assoc();$s->close();return $d;
    }
    public function guardarConstancia(int $id, string $ruta, string $orden, string $fecha, float $monto, string $estado): void
    {
        if ($id <= 0 || !in_array($estado, ['PENDIENTE','PRESENTADA'], true)) {
            throw new DomainException('Datos de constancia inválidos.');
        }
        $fechaSql = $fecha !== '' ? $fecha : null;
        $ordenSql = $orden !== '' ? $orden : null;
        $stmt = $this->conexion->prepare(
            "UPDATE declaraciones_sunat SET numero_orden=?,fecha_presentacion=?,monto_declarado=?,estado_presentacion=?,
             archivo_constancia=CASE WHEN ?<>'' THEN ? ELSE archivo_constancia END,fuente='REGISTRO_MANUAL',
             fecha_ultima_consulta=NULL WHERE id_declaraciones_sunat=?"
        );
        if ($stmt === false) {
            throw new RuntimeException('No se pudo preparar el guardado de la constancia.');
        }
        $stmt->bind_param('ssdsssi', $ordenSql, $fechaSql, $monto, $estado, $ruta, $ruta, $id);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('No se pudo guardar el registro de constancia.');
        }
        $stmt->close();
    }

    public function listar(string $filtroClientes, string $periodo = '', string $estado = '', string $buscar = ''): array
    {
        $where = ["{$filtroClientes}"];
        if ($periodo !== '') {
            if (preg_match('/^(20\d{2})00$/', $periodo, $m)) {
                $y = $this->conexion->real_escape_string($m[1]);
                $where[] = "d.periodo LIKE '{$y}%'";
            } else {
                $where[] = "d.periodo='" . $this->conexion->real_escape_string($periodo) . "'";
            }
        }
if ($estado !== '') {
            $where[] = "(d.estado_presentacion='" . $this->conexion->real_escape_string($estado) . "' OR d.estado_pago='" . $this->conexion->real_escape_string($estado) . "')";
        }
if ($buscar !== '') {
            $b = $this->conexion->real_escape_string('%' . $buscar . '%');
            $where[] = "(c.ruc LIKE '{$b}' OR c.razon_social LIKE '{$b}')";
        }
        $sql = "SELECT d.*,c.ruc,c.razon_social,c.tipo_contribuyente,c.regimen_tributario,c.celular,c.ultimo_digito_ruc FROM declaraciones_sunat d INNER JOIN clientes c ON c.id_cliente=d.id_cliente WHERE " . implode(' AND ', $where) . " ORDER BY d.periodo DESC,c.razon_social,d.formulario";
        $res = $this->conexion->query($sql);
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
return $rows;
    }
    public function resumen(string $filtroClientes, string $periodo): array
    {
        $p = $this->conexion->real_escape_string($periodo);
        $cond = preg_match('/^(20\d{2})00$/', $periodo, $m) ? "d.periodo LIKE '" . $this->conexion->real_escape_string($m[1]) . "%'" : "d.periodo='{$p}'";
        $sql = "SELECT COUNT(*) esperadas,SUM(d.estado_presentacion='PRESENTADA') presentadas,SUM(d.estado_presentacion<>'PRESENTADA') pendientes,SUM(d.fecha_vencimiento<CURDATE() AND d.estado_presentacion<>'PRESENTADA') vencidas FROM declaraciones_sunat d INNER JOIN clientes c ON c.id_cliente=d.id_cliente WHERE {$filtroClientes} AND {$cond}";
        $res = $this->conexion->query($sql);
        $r = $res ? $res->fetch_assoc() : [];
        $esp = (int)($r['esperadas'] ?? 0);
        $pres = (int)($r['presentadas'] ?? 0);
        return ['esperadas' => $esp,'presentadas' => $pres,'pendientes' => (int)($r['pendientes'] ?? 0),'vencidas' => (int)($r['vencidas'] ?? 0),'porcentaje' => $esp ? round($pres * 100 / $esp) : 0];
    }
                                                                                                     
    public function aplicarResultadosOficiales(int $cliente, string $periodo, array $filas): array
    {
        if ($cliente <= 0 || !preg_match('/^20\d{2}(0[1-9]|1[0-2])$/D', $periodo)) {
            throw new InvalidArgumentException('Cliente o período inválido.');
        }
        $this->conexion->begin_transaction();
        try {
            $marca = $this->conexion->prepare('UPDATE declaraciones_sunat SET fecha_ultima_consulta=NOW() WHERE id_cliente=? AND periodo=?');
            if (!$marca) throw new RuntimeException('No se pudo preparar la fecha de consulta.');
            $marca->bind_param('is', $cliente, $periodo);
            if (!$marca->execute()) throw new RuntimeException('No se pudo actualizar la fecha de consulta.');
            $marca->close();
            $consulta = $this->conexion->prepare('SELECT COUNT(*) total FROM declaraciones_sunat WHERE id_cliente=? AND periodo=? AND formulario=?');
            $actualiza = $this->conexion->prepare("UPDATE declaraciones_sunat SET numero_orden=?,fecha_presentacion=?,estado_presentacion='PRESENTADA',fecha_ultima_consulta=NOW() WHERE id_cliente=? AND periodo=? AND formulario=?");
            if (!$consulta || !$actualiza) throw new RuntimeException('No se pudo preparar la conciliación con SUNAT.');
            $actualizadas = 0;
            $sinVincular = 0;
            foreach ($filas as $fila) {
                $form = (string)($fila['formulario'] ?? '');
                $orden = (string)($fila['orden'] ?? '');
                $fecha = (string)($fila['fecha'] ?? '');
                if (!preg_match('/^\d{4}$/D', $form) || !preg_match('/^\d{1,30}$/D', $orden)
                    || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha)) continue;
                $consulta->bind_param('iss', $cliente, $periodo, $form);
                if (!$consulta->execute()) throw new RuntimeException('Falló la comprobación del formulario.');
                $existe = (int)($consulta->get_result()->fetch_assoc()['total'] ?? 0);
                if (!$existe) { $sinVincular++; continue; }
                $actualiza->bind_param('ssiss', $orden, $fecha, $cliente, $periodo, $form);
                if (!$actualiza->execute()) throw new RuntimeException('Falló la actualización del formulario.');
                                                                                               
                $actualizadas++;
            }
            $consulta->close();
            $actualiza->close();
            $this->conexion->commit();
            return ['coincidencias'=>$actualizadas, 'sin_vincular'=>$sinVincular];
        } catch (Throwable $e) {
            $this->conexion->rollback();
            throw $e;
        }
    }

                                                                                                
    public function cronogramaPeriodo(string $filtroClientes, string $periodo, int $cliente = 0): array
    {
        if (!preg_match('/^20\d{2}(0[1-9]|1[0-2])$/D', $periodo)) return [];
        $clienteSql = $cliente > 0 ? ' AND c.id_cliente='.(int)$cliente : '';
        $filas = [];
        try {
            $s = $this->conexion->prepare(
                "SELECT c.id_cliente,c.ruc,c.razon_social,cr.fecha_vencimiento,cr.formulario
                   FROM cronograma_sunat cr INNER JOIN clientes c ON c.ultimo_digito_ruc=cr.ultimo_digito_ruc
                  WHERE cr.periodo=? AND c.estado='ACTIVO' AND ({$filtroClientes}){$clienteSql}
                  ORDER BY cr.fecha_vencimiento,c.razon_social LIMIT 250"
            );
            if ($s) {
                $s->bind_param('s', $periodo);
                if ($s->execute()) $filas = $s->get_result()->fetch_all(MYSQLI_ASSOC);
                $s->close();
            }
        } catch (mysqli_sql_exception $e) {
                                                                                           
        }
        if ($filas || substr($periodo,0,4)!=='2026') return $filas;
        require_once __DIR__.'/../../servicios/sunat/CronogramaOficial2026.php';
        $r = $this->conexion->query(
            "SELECT c.id_cliente,c.ruc,c.razon_social FROM clientes c
              WHERE c.estado='ACTIVO' AND ({$filtroClientes}){$clienteSql}
              ORDER BY c.razon_social LIMIT 250"
        );
        while ($r && ($c=$r->fetch_assoc())) {
            $fecha = CronogramaOficial2026::fecha($periodo, (string)$c['ruc']);
            if ($fecha!==null) $filas[]=$c+['fecha_vencimiento'=>$fecha,'formulario'=>'General 2026'];
        }
        return $filas;
    }
    public function generarPeriodo(string $filtroClientes, string $periodo): int
    {
        if (!PeriodosSire::permitido($periodo,false)) {
            throw new InvalidArgumentException('El período debe estar entre enero de 2021 y el mes vigente.');
        }
        $p = $this->conexion->real_escape_string($periodo);
        $sql = "INSERT IGNORE INTO declaraciones_sunat (id_cliente,periodo,formulario,tipo_obligacion,estado_presentacion,estado_pago,fecha_vencimiento,fuente,fecha_ultima_consulta)
              SELECT o.id_cliente,'{$p}',o.formulario,o.tipo_obligacion,'PENDIENTE','PENDIENTE',
                     COALESCE((SELECT cr.fecha_vencimiento FROM cronograma_sunat cr WHERE cr.periodo='{$p}' AND cr.ultimo_digito_ruc=c.ultimo_digito_ruc AND cr.formulario IN (o.formulario,'*') ORDER BY cr.formulario='*' ASC LIMIT 1),NULL),
                     'CATALOGO_LOCAL',NULL
              FROM cliente_obligaciones_sunat o INNER JOIN clientes c ON c.id_cliente=o.id_cliente
              WHERE o.activo=1 AND c.estado='ACTIVO' AND {$filtroClientes}
                AND (o.periodo_desde IS NULL OR o.periodo_desde<='{$p}') AND (o.periodo_hasta IS NULL OR o.periodo_hasta>='{$p}')";
        if (!$this->conexion->query($sql)) {
            throw new RuntimeException($this->conexion->error);
        }
$insertados = $this->conexion->affected_rows;
        $this->conexion->query("INSERT IGNORE INTO vencimientos_sunat(id_cliente,id_declaracion_sunat,tipo_obligacion,periodo,fecha_vencimiento,estado)
                               SELECT d.id_cliente,d.id_declaraciones_sunat,d.tipo_obligacion,d.periodo,d.fecha_vencimiento,'PENDIENTE' FROM declaraciones_sunat d INNER JOIN clientes c ON c.id_cliente=d.id_cliente WHERE d.periodo='{$p}' AND d.fecha_vencimiento IS NOT NULL AND {$filtroClientes}");
        return max(0, $insertados);
    }
}
