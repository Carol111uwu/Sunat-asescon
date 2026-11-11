<?php

declare(strict_types=1);
require_once __DIR__.'/../../servicios/sunat/PeriodosSire.php';
require_once __DIR__.'/../../servicios/sunat/PresentacionDatosSire.php';

final class RepositorioLecturaSire
{
    private ?string $cacheDesde = null;

    public function __construct(private mysqli $conexion)
    {
    }

                                                                                 
    public function usarSoloCacheDeHoy(): void
    {
        $this->cacheDesde = PeriodosSire::ahoraPeru()->format('Y-m-d').' 00:00:00';
    }

    private function restriccionDia(string $alias = ''): string
    {
        if ($this->cacheDesde === null) return '';
        return ' AND '.($alias === '' ? '' : $alias.'.')."fecha_actualizacion >= '".$this->cacheDesde."'";
    }

    public function listarClientesVisibles(string $filtroClientes, bool $soloActivos = true): array
    {
        $estado = $soloActivos ? " AND c.estado='ACTIVO'" : '';
        $res = $this->conexion->query(
            "SELECT c.* FROM clientes c WHERE {$filtroClientes}{$estado} ORDER BY c.razon_social,c.ruc"
        );
        return $this->filas($res);
    }

    public function obtenerCliente(int $idCliente): ?array
    {
        $stmt = $this->conexion->prepare('SELECT id_cliente,ruc,razon_social,nombre_comercial,nombre_contacto,estado FROM clientes WHERE id_cliente=? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('No se pudo consultar el cliente.');
        }
        $stmt->bind_param('i', $idCliente);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $fila;
    }

    public function listarCabeceras(string $libro, string $filtroClientes): array
    {
        [$tabla] = $this->configuracionLibro($libro);
        $res = $this->conexion->query(
            "SELECT v.*,c.ruc,c.razon_social FROM {$tabla} v INNER JOIN clientes c ON c.id_cliente=v.id_cliente WHERE {$filtroClientes} ORDER BY v.periodo DESC,c.razon_social"
        );
        return $this->filas($res);
    }

                                                                                                  
    public function obtenerResumenGlobal(string $libro, string $filtroClientes, string $periodo): array
    {
        [$tabla] = $this->configuracionLibro($libro);
        $restriccionDia = $this->restriccionDia('v');
        if (!preg_match('/^20\d{2}(?:00|0[1-9]|1[0-2])$/D', $periodo)) {
            throw new InvalidArgumentException('Período SIRE inválido.');
        }
        $periodoSeguro = $this->conexion->real_escape_string($periodo);
        $periodoSql = substr($periodo, 4, 2) === '00'
            ? "v.periodo LIKE '" . $this->conexion->real_escape_string(substr($periodo, 0, 4)) . "%'"
            : "v.periodo='" . $periodoSeguro . "'";
        $consulta = "SELECT c.id_cliente,c.ruc,c.razon_social,
            COALESCE(SUM(v.cantidad_registros),0) cantidad_registros,
            COALESCE(SUM(v.base_imponible),0) base_imponible,
            COALESCE(SUM(v.igv),0) igv,COALESCE(SUM(v.total),0) total,
            COUNT(DISTINCT v.periodo) periodos_importados,
            MAX(v.fecha_actualizacion) fecha_actualizacion
            FROM {$tabla} v INNER JOIN clientes c ON c.id_cliente=v.id_cliente
            WHERE c.estado='ACTIVO' AND ({$filtroClientes}) AND {$periodoSql}
              AND v.estado='PROCESADO' {$restriccionDia}
            GROUP BY c.id_cliente,c.ruc,c.razon_social
            ORDER BY c.razon_social,c.ruc";
        return $this->filas($this->conexion->query($consulta));
    }

                                                                                    
    public function listarPeriodosGuardados(string $libro, string $filtroClientes): array
    {
        [$tabla] = $this->configuracionLibro($libro);
        $maximo=PeriodosSire::ahoraPeru()->format('Ym');
        $sql = "SELECT c.id_cliente,c.ruc,c.razon_social,v.periodo,v.estado,
                       v.cantidad_registros,v.fecha_actualizacion
                FROM {$tabla} v INNER JOIN clientes c ON c.id_cliente=v.id_cliente
                WHERE c.estado='ACTIVO' AND ({$filtroClientes})
                  AND v.estado='PROCESADO' AND v.periodo BETWEEN '202101' AND '{$maximo}'
                ORDER BY c.razon_social ASC,c.ruc ASC,v.periodo DESC LIMIT 12000";
        return $this->filas($this->conexion->query($sql));
    }

                                                                                
    public function obtenerDetallesGlobales(string $libro, string $filtroClientes, string $periodo,
                                            bool $excluirAnteriores, int $pagina, int $porPagina,
                                            string $tipo = ''): array
    {
        [$tabla,$pk,$detalle,$pkDetalle,$documento,$razon] = $this->configuracionLibro($libro);
        $where = $this->condicionGlobalDetalle($filtroClientes, $periodo, $excluirAnteriores, $tipo);
        $limite = max(1, min(100, $porPagina));
        $offset = max(0, $pagina - 1) * $limite;
        $sql = "SELECT d.fecha_emision,d.tipo_comprobante,d.serie,d.numero,
                       d.{$documento},d.{$razon},d.base_imponible,d.igv,
                       d.importe_total,d.estado_registro,h.id_cliente,c.ruc AS ruc_empresa,
                       c.razon_social AS empresa_asescon,h.periodo
                FROM {$detalle} d INNER JOIN {$tabla} h ON h.{$pk}=d.{$pk}
                INNER JOIN clientes c ON c.id_cliente=h.id_cliente
                WHERE {$where}
                ORDER BY c.razon_social,c.ruc,d.fecha_emision DESC,d.{$pkDetalle} DESC
                LIMIT {$limite} OFFSET {$offset}";
        return $this->filas($this->conexion->query($sql));
    }

                                                                                                     
    public function clientesConDetallesGlobales(string $libro, string $filtroClientes, string $periodo,
                                               bool $excluirAnteriores, string $tipo = ''): array
    {
        [$tabla, $pk, $detalle] = $this->configuracionLibro($libro);
        $where = $this->condicionGlobalDetalle($filtroClientes, $periodo, $excluirAnteriores, $tipo);
        $sql = "SELECT DISTINCT c.id_cliente,c.razon_social,c.ruc FROM {$detalle} d
                INNER JOIN {$tabla} h ON h.{$pk}=d.{$pk}
                INNER JOIN clientes c ON c.id_cliente=h.id_cliente WHERE {$where}
                ORDER BY c.razon_social,c.ruc,c.id_cliente";
        return $this->filas($this->conexion->query($sql));
    }

                                                                                               
    public function detallesGlobalesDeCliente(string $libro, string $filtroClientes, string $periodo,
                                              bool $excluirAnteriores, int $idCliente, string $tipo = ''): array
    {
        if ($idCliente <= 0) return [];
        [$tabla, $pk, $detalle, $pkDetalle, $documento, $razon] = $this->configuracionLibro($libro);
        $where = $this->condicionGlobalDetalle($filtroClientes, $periodo, $excluirAnteriores, $tipo);
        $sql = "SELECT d.{$pkDetalle} AS id_detalle,d.fecha_emision,d.tipo_comprobante,d.serie,d.numero,
                       d.{$documento},d.{$razon},d.base_imponible,d.igv,
                       d.importe_total,d.estado_registro,h.id_cliente,c.ruc AS ruc_empresa,
                       c.razon_social AS empresa_asescon,h.periodo
                FROM {$detalle} d INNER JOIN {$tabla} h ON h.{$pk}=d.{$pk}
                INNER JOIN clientes c ON c.id_cliente=h.id_cliente
                WHERE {$where} AND c.id_cliente=" . (int)$idCliente . "
                ORDER BY d.fecha_emision DESC,d.{$pkDetalle} DESC";
        return $this->filas($this->conexion->query($sql));
    }

    public function contarDetallesGlobales(string $libro, string $filtroClientes, string $periodo,
                                          bool $excluirAnteriores, string $tipo = ''): int
    {
        [$tabla,$pk,$detalle] = $this->configuracionLibro($libro);
        $where = $this->condicionGlobalDetalle($filtroClientes, $periodo, $excluirAnteriores, $tipo);
        $res = $this->conexion->query("SELECT COUNT(*) total FROM {$detalle} d
            INNER JOIN {$tabla} h ON h.{$pk}=d.{$pk}
            INNER JOIN clientes c ON c.id_cliente=h.id_cliente WHERE {$where}");
        return (int)($res ? ($res->fetch_assoc()['total'] ?? 0) : 0);
    }

    public function agruparDetallesGlobales(string $libro, string $filtroClientes, string $periodo,
                                           bool $excluirAnteriores): array
    {
        [$tabla,$pk,$detalle] = $this->configuracionLibro($libro);
        $where = $this->condicionGlobalDetalle($filtroClientes, $periodo, $excluirAnteriores, '');
        $sql = "SELECT COALESCE(NULLIF(TRIM(d.tipo_comprobante),''),'—') AS tipo,
                       COUNT(*) documentos,COALESCE(SUM(d.base_imponible),0) base,
                       COALESCE(SUM(d.igv),0) igv,COALESCE(SUM(d.importe_total),0) total
                FROM {$detalle} d INNER JOIN {$tabla} h ON h.{$pk}=d.{$pk}
                INNER JOIN clientes c ON c.id_cliente=h.id_cliente WHERE {$where}
                GROUP BY tipo ORDER BY documentos DESC,tipo ASC LIMIT 120";
        return $this->filas($this->conexion->query($sql));
    }

                                                                                                                                    
    public function obtenerSerieMensual(string $anio, int $idCliente, string $filtroClientes): array
    {
        $restriccionDia = $this->restriccionDia('v');
        if (!preg_match('/^20\d{2}$/D', $anio)) {
            throw new InvalidArgumentException('Año SIRE inválido.');
        }
        $serie = [];
        foreach (['RVIE', 'RCE'] as $libro) {
            [$tabla] = $this->configuracionLibro($libro);
            $porCliente = $idCliente > 0
                ? 'c.id_cliente=' . $idCliente
                : 'c.estado=\'ACTIVO\' AND (' . $filtroClientes . ')';
            $sql = "SELECT v.periodo,COUNT(DISTINCT v.id_cliente) empresas,
                        COALESCE(SUM(v.cantidad_registros),0) registros,
                        COALESCE(SUM(v.base_imponible),0) base,
                        COALESCE(SUM(v.igv),0) igv,
                        COALESCE(SUM(v.total),0) total,
                        MAX(v.fecha_actualizacion) fecha_actualizacion
                    FROM {$tabla} v INNER JOIN clientes c ON c.id_cliente=v.id_cliente
                    WHERE {$porCliente} AND v.periodo LIKE '" . $anio . "%'
                      AND v.estado='PROCESADO' {$restriccionDia}
                    GROUP BY v.periodo ORDER BY v.periodo";
            foreach ($this->filas($this->conexion->query($sql)) as $fila) {
                $periodo = (string)$fila['periodo'];
                if (preg_match('/^20\d{2}(0[1-9]|1[0-2])$/D', $periodo)) {
                    $serie[$periodo][$libro] = $fila;
                }
            }
        }
        ksort($serie);
        return $serie;
    }

    private function condicionGlobalDetalle(string $filtroClientes, string $periodo,
                                            bool $excluirAnteriores, string $tipo): string
    {
        if (!preg_match('/^20\d{2}(00|0[1-9]|1[0-2])$/D', $periodo)) {
            throw new InvalidArgumentException('Período SIRE inválido.');
        }
        $periodoSql = substr($periodo, 4) === '00'
            ? "h.periodo LIKE '".$this->conexion->real_escape_string(substr($periodo,0,4))."%'"
            : "h.periodo='".$this->conexion->real_escape_string($periodo)."'";
        $where = "c.estado='ACTIVO' AND ({$filtroClientes}) AND {$periodoSql}";
        if ($excluirAnteriores) {
            [$inicio, $fin] = PresentacionDatosSire::limitesEmision($periodo);
            $where .= " AND d.fecha_emision >= '".$inicio."' AND d.fecha_emision < '".$fin."'";
        }
        if ($tipo !== '') {
            $where .= " AND COALESCE(NULLIF(TRIM(d.tipo_comprobante),''),'—')='"
                .$this->conexion->real_escape_string($tipo)."'";
        }
        return $where;
    }

    public function obtenerCabeceraPeriodo(string $libro, int $idCliente, string $periodo): array
    {
        [$tabla,$pk] = $this->configuracionLibro($libro);
        $restriccionDia = $this->restriccionDia();
        $base = $this->cabeceraVacia($libro);
        $stmt = $this->conexion->prepare("SELECT * FROM {$tabla} WHERE id_cliente=? AND periodo=? AND estado='PROCESADO' {$restriccionDia} LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('No se pudo consultar el período SIRE.');
        }
        $stmt->bind_param('is', $idCliente, $periodo);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return array_merge($base, $fila, [$pk => (int)($fila[$pk] ?? 0)]);
    }

    public function obtenerCabeceraAnual(string $libro, int $idCliente, string $anio): array
    {
        [$tabla] = $this->configuracionLibro($libro);
        $restriccionDia = $this->restriccionDia();
        $stmt = $this->conexion->prepare(
            "SELECT COALESCE(SUM(cantidad_registros),0) cantidad_registros,COALESCE(SUM(base_imponible),0) base_imponible,COALESCE(SUM(igv),0) igv,COALESCE(SUM(total),0) total,COUNT(DISTINCT periodo) meses_importados,MAX(fecha_actualizacion) fecha_actualizacion,CASE WHEN COUNT(*)>0 THEN 'ANUAL' ELSE 'SIN_DATOS' END estado FROM {$tabla} WHERE id_cliente=? AND periodo LIKE CONCAT(?,'%') AND estado='PROCESADO' {$restriccionDia}"
        );
        if (!$stmt) {
            throw new RuntimeException('No se pudo consultar el resumen anual SIRE.');
        }
        $stmt->bind_param('is', $idCliente, $anio);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return array_merge($this->cabeceraVacia($libro), $fila);
    }

    public function obtenerDetalles(string $libro, int $idCliente, string $periodo): array
    {
        [$tabla,$pk,$detalle,$pkDetalle,$documento,$razon] = $this->configuracionLibro($libro);
        $stmt = $this->conexion->prepare("SELECT {$pk} id FROM {$tabla} WHERE id_cliente=? AND periodo=? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('No se pudo consultar la cabecera SIRE.');
        }
        $stmt->bind_param('is', $idCliente, $periodo);
        $stmt->execute();
        $id = (int)($stmt->get_result()->fetch_assoc()['id'] ?? 0);
        $stmt->close();
        if ($id <= 0) {
            return [];
        }
        $sql = "SELECT {$pkDetalle} AS id_detalle,fecha_emision,tipo_comprobante,serie,numero,{$documento},{$razon},base_imponible,igv,importe_total,estado_registro FROM {$detalle} WHERE {$pk}=? ORDER BY fecha_emision DESC,{$pkDetalle} DESC";
        $stmt = $this->conexion->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('No se pudo consultar el detalle SIRE.');
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $filas = $this->filas($stmt->get_result());
        $stmt->close();
        return $filas;
    }

    public function obtenerDetallesAnuales(string $libro, int $idCliente, string $anio, bool $normalizados = false): array
    {
        [$tabla,$pk,$detalle,$pkDetalle,$documento,$razon] = $this->configuracionLibro($libro);
        $docSelect = $normalizados ? "d.{$documento} documento" : "d.{$documento}";
        $razonSelect = $normalizados ? "d.{$razon} razon_social" : "d.{$razon}";
        $sql = "SELECT d.{$pkDetalle} AS id_detalle,d.fecha_emision,d.tipo_comprobante,d.serie,d.numero,{$docSelect},{$razonSelect},d.base_imponible,d.igv,d.importe_total,d.estado_registro,h.periodo FROM {$detalle} d INNER JOIN {$tabla} h ON h.{$pk}=d.{$pk} WHERE h.id_cliente=? AND h.periodo LIKE CONCAT(?,'%') ORDER BY d.fecha_emision DESC,d.{$pkDetalle} DESC";
        $stmt = $this->conexion->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('No se pudo consultar el detalle anual SIRE.');
        }
        $stmt->bind_param('is', $idCliente, $anio);
        $stmt->execute();
        $filas = $this->filas($stmt->get_result());
        $stmt->close();
        return $filas;
    }

    public function obtenerComprobanteRce(int $idCliente, int $idDetalle): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT d.*, h.periodo, c.ruc AS ruc_empresa, c.razon_social AS empresa_asescon
             FROM rce_detalle d
             JOIN rce h ON h.id_rce = d.id_rce
             JOIN clientes c ON c.id_cliente = h.id_cliente
             WHERE d.id_rce_detalle = ? AND h.id_cliente = ?'
        );
        $stmt->bind_param('ii', $idDetalle, $idCliente);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $fila ?: null;
    }

    public function obtenerDetallesNormalizados(string $libro, int $idCliente, string $periodo): array
    {
        [$tabla,$pk,$detalle,$pkDetalle,$documento,$razon] = $this->configuracionLibro($libro);
        $stmt = $this->conexion->prepare("SELECT {$pk} id FROM {$tabla} WHERE id_cliente=? AND periodo=? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('No se pudo consultar el período SIRE.');
        }
        $stmt->bind_param('is', $idCliente, $periodo);
        $stmt->execute();
        $id = (int)($stmt->get_result()->fetch_assoc()['id'] ?? 0);
        $stmt->close();
        if ($id <= 0) {
            return [];
        }
        $sql = "SELECT fecha_emision,tipo_comprobante,serie,numero,{$documento} documento,{$razon} razon_social,base_imponible,igv,importe_total,estado_registro FROM {$detalle} WHERE {$pk}=? ORDER BY fecha_emision DESC,{$pkDetalle} DESC";
        $stmt = $this->conexion->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('No se pudo consultar el detalle SIRE.');
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $filas = $this->filas($stmt->get_result());
        $stmt->close();
        return $filas;
    }

    public function listarHistorial(string $filtroClientes, string $libro = '', ?int $idCliente = null): array
    {
        $extra = '';
        if ($libro !== '') {
            $libro = strtoupper(trim($libro));
            if (!in_array($libro, ['RVIE','RCE'], true)) {
                throw new InvalidArgumentException('Libro SIRE inválido.');
            }
            $extra .= " AND s.tipo_libro='" . $this->conexion->real_escape_string($libro) . "'";
        }
        if ($idCliente !== null && $idCliente > 0) {
            $extra .= ' AND s.id_cliente=' . (int)$idCliente;
        }
        $res = $this->conexion->query(
            "SELECT s.*,c.ruc,c.razon_social FROM sire s INNER JOIN clientes c ON c.id_cliente=s.id_cliente WHERE {$filtroClientes}{$extra} ORDER BY s.periodo DESC,c.razon_social LIMIT 200"
        );
        return $this->filas($res);
    }

                                                                                   
    public function historialDashboard(string $filtroClientes, ?int $idCliente, string $periodo,
                                      bool $soloSeleccion, int $pagina, int $porPagina = 50): array
    {
        $where = $this->condicionHistorialDashboard($filtroClientes, $idCliente, $periodo, $soloSeleccion);
        $limite = max(1, min(100, $porPagina));
        $offset = max(0, $pagina - 1) * $limite;
        $res = $this->conexion->query("SELECT s.*,c.ruc,c.razon_social FROM sire s
            INNER JOIN clientes c ON c.id_cliente=s.id_cliente
            WHERE {$where}
            ORDER BY s.periodo DESC,c.razon_social,c.ruc,s.tipo_libro ASC
            LIMIT {$limite} OFFSET {$offset}");
        return $this->filas($res);
    }

                                                                                              
    public function clientesConHistorialDashboard(string $filtroClientes, string $periodo, bool $soloSeleccion): array
    {
        $where = $this->condicionHistorialDashboard($filtroClientes, null, $periodo, $soloSeleccion);
        $res = $this->conexion->query("SELECT DISTINCT c.id_cliente,c.razon_social,c.ruc FROM sire s
            INNER JOIN clientes c ON c.id_cliente=s.id_cliente WHERE {$where}
            ORDER BY c.razon_social,c.ruc,c.id_cliente");
        return $this->filas($res);
    }

                                                                                   
    public function historialDashboardDeCliente(string $filtroClientes, int $idCliente,
                                               string $periodo, bool $soloSeleccion): array
    {
        if ($idCliente <= 0) return [];
        $where = $this->condicionHistorialDashboard($filtroClientes, $idCliente, $periodo, $soloSeleccion);
        $res = $this->conexion->query("SELECT s.*,c.ruc,c.razon_social FROM sire s
            INNER JOIN clientes c ON c.id_cliente=s.id_cliente WHERE {$where}
            ORDER BY s.periodo DESC,c.razon_social,c.ruc,s.tipo_libro ASC");
        return $this->filas($res);
    }

    public function contarHistorialDashboard(string $filtroClientes, ?int $idCliente,
                                             string $periodo, bool $soloSeleccion): int
    {
        $where = $this->condicionHistorialDashboard($filtroClientes, $idCliente, $periodo, $soloSeleccion);
        $res = $this->conexion->query("SELECT COUNT(*) total FROM sire s
            INNER JOIN clientes c ON c.id_cliente=s.id_cliente WHERE {$where}");
        return (int)($res ? ($res->fetch_assoc()['total'] ?? 0) : 0);
    }

    private function condicionHistorialDashboard(string $filtroClientes, ?int $idCliente,
                                                string $periodo, bool $soloSeleccion): string
    {
        if (!preg_match('/^20\d{2}(00|0[1-9]|1[0-2])$/D', $periodo)) {
            throw new InvalidArgumentException('Período de historial inválido.');
        }
        $maximo = PeriodosSire::ahoraPeru()->format('Ym');
                                                                          
                                                                             
        $inicioHoy = PeriodosSire::ahoraPeru()->format('Y-m-d').' 00:00:00';
        $where = "c.estado='ACTIVO' AND ({$filtroClientes}) AND s.periodo BETWEEN '202101' AND '{$maximo}'"
            . " AND s.fecha_actualizacion >= '".$inicioHoy."'";
        if ($idCliente !== null && $idCliente > 0) $where .= ' AND s.id_cliente='.(int)$idCliente;
        if ($soloSeleccion) {
            $seleccion = $this->conexion->real_escape_string($periodo);
            $where .= substr($periodo, 4)==='00'
                ? " AND s.periodo LIKE '".$this->conexion->real_escape_string(substr($periodo,0,4))."%'"
                : " AND s.periodo='".$seleccion."'";
        }
        return $where;
    }

    public function obtenerUltimaDescarga(int $idCliente, string $periodo, string $libro): ?array
    {
        $libro = strtoupper(trim($libro));
        if ($idCliente <= 0 || !preg_match('/^20\d{4}$/', $periodo) || !in_array($libro, ['RVIE','RCE'], true)) {
            return null;
        }
        $restriccionDescarga = $this->cacheDesde === null ? ''
            : " AND COALESCE(fecha_procesamiento,fecha_descarga,fecha_solicitud) >= '".$this->cacheDesde."'";
        $stmt = $this->conexion->prepare(
            'SELECT id_descarga,numero_ticket,estado_ticket,cantidad_registros,fecha_descarga,fecha_procesamiento,estado_procesamiento,mensaje,hash_zip,hash_datos
             FROM sire_descargas
             WHERE id_cliente=? AND periodo=? AND tipo_libro=?'. $restriccionDescarga .'
             ORDER BY id_descarga DESC LIMIT 1'
        );
        $stmt->bind_param('iss', $idCliente, $periodo, $libro);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $fila;
    }

    public function obtenerEstadoCredencial(int $idCliente): array
    {
        $stmt = $this->conexion->prepare('SELECT estado_validacion,fecha_ultima_validacion,mensaje_ultima_validacion FROM credenciales_sunat WHERE id_cliente=? LIMIT 1');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $idCliente);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return $fila;
    }

    private function configuracionLibro(string $libro): array
    {
        $libro = strtoupper(trim($libro));
        return match ($libro) {
            'RVIE' => ['rvie','id_rvie','rvie_detalle','id_rvie_detalle','documento_cliente','razon_social_cliente'],
            'RCE' => ['rce','id_rce','rce_detalle','id_rce_detalle','ruc_proveedor','razon_social_proveedor'],
            default => throw new InvalidArgumentException('Libro SIRE inválido.'),
        };
    }

    private function cabeceraVacia(string $libro): array
    {
        $pk = strtoupper($libro) === 'RCE' ? 'id_rce' : 'id_rvie';
        return [$pk => 0,'cantidad_registros' => 0,'base_imponible' => 0,'igv' => 0,'total' => 0,'fecha_actualizacion' => null,'estado' => 'SIN_DATOS'];
    }

    private function filas(mysqli_result|false $res): array
    {
        $filas = [];
        if ($res) {
            while ($fila = $res->fetch_assoc()) {
                $filas[] = $fila;
            }
        }
        return $filas;
    }
}
