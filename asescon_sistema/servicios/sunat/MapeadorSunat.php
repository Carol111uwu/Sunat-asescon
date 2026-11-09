<?php

declare(strict_types=1);

require_once __DIR__ . '/ExcepcionSunat.php';
require_once __DIR__ . '/ServicioSireSunat.php';

final class MapeadorSunat
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function importarArchivos(int $idCliente, string $periodo, string $codLibro, array $archivos, array $evidencia = []): array
    {
        if ($idCliente <= 0) {
            throw new ExcepcionSunat('Cliente inválido para importar SIRE.');
        }
        if (!preg_match('/^20\d{4}$/', $periodo)) {
            throw new ExcepcionSunat('Periodo SIRE inválido para importar.');
        }
        if (!in_array($codLibro, [ServicioSireSunat::LIBRO_RVIE,ServicioSireSunat::LIBRO_RCE], true)) {
            throw new ExcepcionSunat('Libro SIRE inválido para importar.');
        }

        $detalles = [];
        $omitidas = 0;
        $estructuraReconocida = false;
        $rucsEmisores = [];
        foreach ($archivos as $archivo) {
            if (!is_string($archivo) || !is_file($archivo)) {
                throw new ExcepcionSunat('Falta un archivo SIRE de la descarga. No se importó un conjunto incompleto.');
            }
            [$filas,$omit,$compatible,$propietarios] = $this->leerArchivo($archivo, $periodo, $codLibro);
            $rucsEmisores = array_merge($rucsEmisores, $propietarios);
            $detalles = array_merge($detalles, $filas);
            $omitidas += $omit;
            $estructuraReconocida = $estructuraReconocida || $compatible;
        }
                                                                               
                                                                                              
        if ($detalles === [] && $omitidas > 0) {
            throw new ExcepcionSunat('Se rechazaron todas las filas del archivo SIRE (periodo, RUC o formato distinto). No se reemplazó ningún dato anterior.');
        }
        $propuestaSinComprobantes = $detalles === [] && $estructuraReconocida && $omitidas === 0;
        if ($detalles !== [] && $omitidas > 0) {
            throw new ExcepcionSunat('El archivo SIRE contiene filas omitidas. Se detuvo la importación para evitar guardar datos parciales; revisa el archivo oficial.');
        }
        if ($detalles === [] && !$estructuraReconocida) {
            throw new ExcepcionSunat('El archivo SIRE se descargó, pero ASESCON no reconoció la estructura de la propuesta ' . ($codLibro === ServicioSireSunat::LIBRO_RVIE ? 'RVIE' : 'RCE') . '. Se conservó el archivo para diagnóstico.');
        }

                                                                                  
        $stmtCliente = $this->conexion->prepare('SELECT ruc FROM clientes WHERE id_cliente=? LIMIT 1');
        if (!$stmtCliente) { throw new ExcepcionSunat('No se pudo verificar la identidad del cliente SIRE.'); }
        $stmtCliente->bind_param('i', $idCliente);
        $stmtCliente->execute();
        $empresa = $stmtCliente->get_result()->fetch_assoc();
        $stmtCliente->close();
        $rucEsperado = (string)($empresa['ruc'] ?? '');
        if (!preg_match('/^\d{11}$/', $rucEsperado) || (!$propuestaSinComprobantes && $rucsEmisores === [])) {
            throw new ExcepcionSunat('No se pudo verificar el RUC titular de la propuesta SIRE. No se cambiaron los datos anteriores.');
        }
        if ($propuestaSinComprobantes) {
                                                                              
                                                                                
                                                                                        
            $this->validarEvidenciaCero($evidencia, $rucEsperado, $periodo, $codLibro, $archivos);
        }
        foreach (array_unique($rucsEmisores) as $rucArchivo) {
            if ($rucArchivo !== $rucEsperado) {
                throw new ExcepcionSunat('El archivo SIRE pertenece a otro RUC. No se importó ningún documento.');
            }
        }

        $base = 0.0;
        $igv = 0.0;
        $total = 0.0;
        foreach ($detalles as $d) {
            $base += (float)$d['base_imponible'];
            $igv += (float)$d['igv'];
            $total += (float)$d['importe_total'];
        }
        $this->conexion->begin_transaction();
        try {
            if ($propuestaSinComprobantes) {
                                                                               
                                                                                  
                $tabla = $codLibro === ServicioSireSunat::LIBRO_RVIE ? 'rvie' : 'rce';
                $anterior = $this->conexion->prepare("SELECT cantidad_registros FROM {$tabla} WHERE id_cliente=? AND periodo=? LIMIT 1 FOR UPDATE");
                if (!$anterior) throw new RuntimeException('No se pudo proteger el historial SIRE anterior.');
                $anterior->bind_param('is', $idCliente, $periodo);
                $anterior->execute();
                $filaAnterior = $anterior->get_result()->fetch_assoc();
                $anterior->close();
                if ((int)($filaAnterior['cantidad_registros'] ?? 0) > 0) {
                    throw new ExcepcionSunat('La propuesta actual contiene cero comprobantes, pero ASESCON ya tiene comprobantes guardados para este período. Se conservaron; revisar la diferencia antes de reemplazarlos.');
                }
            }
            if ($codLibro === ServicioSireSunat::LIBRO_RVIE) {
                $idRegistro = $this->upsertCabecera('rvie', 'id_rvie', $idCliente, $periodo, count($detalles), $base, $igv, $total);
                $this->reemplazarRvieDetalle($idRegistro, $detalles);
                $libro = 'RVIE';
            } else {
                $idRegistro = $this->upsertCabecera('rce', 'id_rce', $idCliente, $periodo, count($detalles), $base, $igv, $total);
                $this->reemplazarRceDetalle($idRegistro, $detalles);
                $libro = 'RCE';
            }
            $obs = $propuestaSinComprobantes
                ? 'Propuesta SIRE consultada y archivo del ticket validado: 0 comprobantes para el período. No acredita aceptación ni presentación oficial.'
                : 'Datos reales importados desde la propuesta SIRE descargada por API.';
            $stmt = $this->conexion->prepare("INSERT INTO sire(id_cliente,periodo,tipo_libro,cantidad_registros,monto_total,estado,fecha_actualizacion,observaciones) VALUES(?,?,?,?,?,'PROCESADO',NOW(),?) ON DUPLICATE KEY UPDATE cantidad_registros=VALUES(cantidad_registros),monto_total=VALUES(monto_total),estado='PROCESADO',fecha_actualizacion=NOW(),observaciones=VALUES(observaciones)");
            if (!$stmt) {
                throw new RuntimeException('No se pudo actualizar el resumen SIRE.');
            }
$cantidad = count($detalles);
            $stmt->bind_param('issids', $idCliente, $periodo, $libro, $cantidad, $total, $obs);
            if (!$stmt->execute()) {
                $e = $stmt->error;
                $stmt->close();
                throw new RuntimeException($e);
            }
$stmt->close();
            $this->conexion->commit();
        } catch (Throwable $e) {
            $this->conexion->rollback();
            throw $e;
        }
        return ['libro' => $codLibro === ServicioSireSunat::LIBRO_RVIE ? 'RVIE' : 'RCE','periodo' => $periodo,'cantidad_registros' => count($detalles),'sin_comprobantes' => $propuestaSinComprobantes,'base_imponible' => $base,'igv' => $igv,'total' => $total,'omitidas' => $omitidas];
    }

                                                                                                  
    public function recuperarCamposFaltantes(int $idCliente, string $periodo, string $codLibro, array $archivos, string $rucEsperado): array
    {
        if ($idCliente <= 0 || !preg_match('/^20\d{2}(0[1-9]|1[0-2])$/D', $periodo)
            || !preg_match('/^\d{11}$/D', $rucEsperado)
            || !in_array($codLibro, [ServicioSireSunat::LIBRO_RVIE, ServicioSireSunat::LIBRO_RCE], true)
            || !$archivos || count($archivos) > 50) {
            throw new ExcepcionSunat('No se pudo validar el período, la empresa o los archivos SIRE locales.');
        }
        $filasFuente = [];
        $rucsFuente = [];
        $omitidas = 0;
        foreach ($archivos as $archivo) {
            if (!is_string($archivo) || !is_file($archivo)) {
                throw new ExcepcionSunat('Falta una parte del archivo local. No se cambiaron los comprobantes.');
            }
            [$filas, $omit, , $propietarios] = $this->leerArchivo($archivo, $periodo, $codLibro);
            $filasFuente = array_merge($filasFuente, $filas);
            $rucsFuente = array_merge($rucsFuente, $propietarios);
            $omitidas += $omit;
            if (count($filasFuente) > 50000) {
                throw new ExcepcionSunat('El archivo supera el límite seguro de recuperación web (50 000 filas). Los datos siguen intactos.');
            }
        }
        foreach (array_unique($rucsFuente) as $ruc) {
            if ($ruc !== $rucEsperado) throw new ExcepcionSunat('El archivo local no pertenece al RUC de la empresa. No se cambiaron datos.');
        }
        if (!$filasFuente || !$rucsFuente) {
            throw new ExcepcionSunat('No se identificaron comprobantes y RUC verificables en el archivo guardado. Los datos se conservaron.');
        }

                                                                              
        $porCar = [];
        $porDocumento = [];
        foreach ($filasFuente as $fila) {
            $car = trim((string)($fila['car_sunat'] ?? ''));
            if ($car !== '') $porCar[$car] = array_key_exists($car, $porCar) ? null : $fila;
            $clave = self::claveDocumento($fila);
            if ($clave !== null) $porDocumento[$clave] = array_key_exists($clave, $porDocumento) ? null : $fila;
        }

        $esRce = $codLibro === ServicioSireSunat::LIBRO_RCE;
        $tabla = $esRce ? 'rce' : 'rvie';
        $detalle = $esRce ? 'rce_detalle' : 'rvie_detalle';
        $pk = $esRce ? 'id_rce' : 'id_rvie';
        $pkDetalle = $esRce ? 'id_rce_detalle' : 'id_rvie_detalle';
        $campoDocumento = $esRce ? 'ruc_proveedor' : 'documento_cliente';
        $campoRazon = $esRce ? 'razon_social_proveedor' : 'razon_social_cliente';
        $resumen = ['archivos_leidos'=>count($archivos), 'filas_fuente'=>count($filasFuente), 'filas_omitidas_fuente'=>$omitidas,
            'registros_locales'=>0,'filas_corregidas'=>0,'fechas_recuperadas'=>0,'nombres_recuperados'=>0,'documentos_recuperados'=>0];
        $this->conexion->begin_transaction();
        try {
            $cabecera = $this->conexion->prepare("SELECT {$pk} id FROM {$tabla} WHERE id_cliente=? AND periodo=? AND estado='PROCESADO' LIMIT 1 FOR UPDATE");
            if (!$cabecera) throw new RuntimeException('No se pudo consultar el resumen local.');
            $cabecera->bind_param('is', $idCliente, $periodo);
            $cabecera->execute();
            $idRegistro = (int)($cabecera->get_result()->fetch_assoc()['id'] ?? 0);
            $cabecera->close();
            if ($idRegistro <= 0) throw new ExcepcionSunat('No existe un período procesado localmente para reparar. No se cambiaron datos.');
            $consulta = $this->conexion->prepare("SELECT {$pkDetalle} id_detalle, car_sunat, tipo_comprobante, serie, numero, fecha_emision, {$campoDocumento} documento, {$campoRazon} razon FROM {$detalle} WHERE {$pk}=?");
            if (!$consulta) throw new RuntimeException('No se pudo consultar el detalle local.');
            $consulta->bind_param('i', $idRegistro);
            $consulta->execute();
            $resultado = $consulta->get_result();
            $filasGuardadas = [];
            while ($fila = $resultado->fetch_assoc()) $filasGuardadas[] = $fila;
            $consulta->close();
            $resumen['registros_locales'] = count($filasGuardadas);
                                                                                                              
            $actualizar = $this->conexion->prepare("UPDATE {$detalle} SET fecha_emision=COALESCE(fecha_emision,?), {$campoRazon}=IF(TRIM(COALESCE({$campoRazon},''))='',?,{$campoRazon}), {$campoDocumento}=IF(TRIM(COALESCE({$campoDocumento},''))='',?,{$campoDocumento}) WHERE {$pkDetalle}=? AND {$pk}=?");
            if (!$actualizar) throw new RuntimeException('No se pudo preparar la recuperación local.');
            foreach ($filasGuardadas as $guardada) {
                $car = trim((string)($guardada['car_sunat'] ?? ''));
                $fuente = $car !== '' ? ($porCar[$car] ?? null) : null;
                if ($fuente === null) {
                    $clave = self::claveDocumento($guardada);
                    $candidata = $clave === null ? null : ($porDocumento[$clave] ?? null);
                    $carCandidata = trim((string)($candidata['car_sunat'] ?? ''));
                    if (is_array($candidata) && ($car === '' || $carCandidata === '' || $car === $carCandidata)) $fuente = $candidata;
                }
                if (!is_array($fuente)) continue;
                $fecha = empty($guardada['fecha_emision']) && !empty($fuente['fecha_emision']) ? (string)$fuente['fecha_emision'] : null;
                $razon = trim((string)($guardada['razon'] ?? '')) === '' && trim((string)($fuente['razon_social'] ?? '')) !== '' ? (string)$fuente['razon_social'] : null;
                $documento = trim((string)($guardada['documento'] ?? '')) === '' && trim((string)($fuente['documento'] ?? '')) !== '' ? (string)$fuente['documento'] : null;
                if ($fecha === null && $razon === null && $documento === null) continue;
                $idDetalle = (int)$guardada['id_detalle'];
                $actualizar->bind_param('sssii', $fecha, $razon, $documento, $idDetalle, $idRegistro);
                if (!$actualizar->execute()) throw new RuntimeException('Falló la actualización de un campo de detalle SIRE.');
                if ($actualizar->affected_rows > 0) {
                    $resumen['filas_corregidas']++;
                    if ($fecha !== null) $resumen['fechas_recuperadas']++;
                    if ($razon !== null) $resumen['nombres_recuperados']++;
                    if ($documento !== null) $resumen['documentos_recuperados']++;
                }
            }
            $actualizar->close();
            $this->conexion->commit();
        } catch (Throwable $e) {
            $this->conexion->rollback();
            throw $e;
        }
        return $resumen;
    }

    private static function claveDocumento(array $fila): ?string
    {
        $tipo = strtoupper(trim((string)($fila['tipo_comprobante'] ?? '')));
        $numero = trim((string)($fila['numero'] ?? ''));
        if ($tipo === '' || $numero === '') return null;
        return $tipo."\x1F".strtoupper(trim((string)($fila['serie'] ?? '')))."\x1F".$numero;
    }

                                                                                           
    private function validarEvidenciaCero(array $evidencia, string $ruc, string $periodo, string $libro, array $archivos): void
    {
        if (($evidencia['tipo'] ?? '') !== 'TICKET_SIRE_TERMINADO'
            || ($evidencia['ruc'] ?? '') !== $ruc
            || ($evidencia['periodo'] ?? '') !== $periodo
            || ($evidencia['libro'] ?? '') !== $libro
            || trim((string)($evidencia['ticket'] ?? '')) === ''
            || !preg_match('/^[a-f0-9]{64}$/D', (string)($evidencia['hash_zip'] ?? ''))) {
            throw new ExcepcionSunat('El archivo no contiene comprobantes, pero faltan evidencias del ticket y RUC oficiales. Se mantuvo el período pendiente.');
        }
        $hashes = $evidencia['hash_archivos'] ?? [];
        if (!is_array($hashes) || count($archivos) === 0 || count($archivos) !== count($hashes)) {
            throw new ExcepcionSunat('No se pudieron verificar todas las partes del archivo SIRE vacío.');
        }
        foreach ($archivos as $i => $archivo) {
            $hash = is_file($archivo) ? hash_file('sha256', $archivo) : false;
            if (!is_string($hash) || !hash_equals($hash, (string)($hashes[$i] ?? ''))) {
                throw new ExcepcionSunat('La integridad del archivo SIRE vacío no pudo comprobarse.');
            }
        }
    }

    private function leerArchivo(string $archivo, string $periodo, string $codLibro): array
    {
        $h = @fopen($archivo, 'rb');
        if ($h === false) {
            throw new ExcepcionSunat('No se pudo abrir el archivo SIRE descargado.');
        }
        $filas = [];
        $omitidas = 0;
        $cabecera = null;
        $compatible = false;
        $propietarios = [];
        try {
            while (($linea = fgets($h)) !== false) {
                $linea = $this->aUtf8(rtrim($linea, "\r\n"));
                if (trim($linea) === '') {
                    continue;
                }
$delim = $this->detectarDelimitador($linea);
                $c = str_getcsv($linea, $delim);
                $c = array_map(fn($v)=>trim($this->limpiarBom((string)$v)), $c);
                if ($cabecera === null && $this->pareceCabecera($c)) {
                    $cabecera = $this->crearMapaCabecera($c);
                    $compatible = $this->cabeceraCompatible($cabecera, $codLibro);
                    continue;
                }
                if ($cabecera !== null) {
                    $ruc = $this->valor($c, $cabecera, ['ruc']);
                    $per = $this->valor($c, $cabecera, ['periodo']);
                    $periodoFila = preg_replace('/\D+/', '', $per) ?? '';
                    if ($ruc !== '' && !preg_match('/^\d{11}$/', $ruc)) {
                        $omitidas++;
                        continue;
                    }
                    if ($ruc !== '') { $propietarios[] = $ruc; }
if ($periodoFila !== '' && substr($periodoFila, 0, 6) !== $periodo) {
                        $omitidas++;
                        continue;
                    }
$fila = $this->mapearConCabecera($c, $cabecera, $codLibro);
                    if ($fila === null) {
                        $omitidas++;
                        continue;
                    }
$filas[] = $fila;
                    $compatible = true;
                    continue;
                }

                $periodoFila = preg_replace('/\D+/', '', (string)($c[2] ?? '')) ?? '';
                if (count($c) < 26 || !preg_match('/^\d{11}$/', $c[0] ?? '') || substr($periodoFila, 0, 6) !== $periodo) {
                    $omitidas++;
                    continue;
                }
$fila = $codLibro === ServicioSireSunat::LIBRO_RVIE ? $this->mapearRviePosicional($c) : $this->mapearRcePosicional($c);
                if ($fila === null) {
                    $omitidas++;
                    continue;
                }
$propietarios[] = (string)$c[0];
$filas[] = $fila;
                $compatible = true;
            }
        } finally {
            fclose($h);
        }
return [$filas,$omitidas,$compatible,array_values(array_unique($propietarios))];
    }

    private function detectarDelimitador(string $linea): string
    {
        return str_contains($linea, '|') ? '|' : (str_contains($linea, "\t") ? "\t" : (str_contains($linea, ';') ? ';' : ','));
    }

    private function pareceCabecera(array $c): bool
    {
        $n = array_map(fn($x)=>$this->clave($x), $c);
        return in_array('car sunat', $n, true) && in_array('periodo', $n, true);
    }

    private function crearMapaCabecera(array $c): array
    {
        $m = [];
        foreach ($c as $i => $v) {
            $k = $this->clave($v);
            if ($k !== '' && !isset($m[$k])) {
                $m[$k] = $i;
            }
        }
return $m;
    }

    private function cabeceraCompatible(array $m, string $libro): bool
    {
        if (!isset($m['car sunat'])) {
            return false;
        }
if ($libro === ServicioSireSunat::LIBRO_RCE) {
            return $this->indice($m, ['bi gravado dg','total cp']) !== null;
        }
return $this->indice($m, ['bi gravada','total cp']) !== null;
    }

    private function mapearConCabecera(array $c, array $m, string $libro): ?array
    {
        $car = $this->valor($c, $m, ['car sunat']);
        $tipo = $this->valor($c, $m, ['tipo cp/doc','tipo cp doc']);
        $serie = $this->valor($c, $m, ['serie del cdp','serie cp']);
        $numero = $this->valor($c, $m, ['nro cp o doc nro inicial rango','nro cp o doc nro inicial','nro cp o doc','numero']);
        if ($car === '' && $tipo === '' && $serie === '' && $numero === '') {
            return null;
        }
        $doc = $this->valor($c, $m, ['nro doc identidad','numero doc identidad','nro documento identidad','ruc proveedor','documento proveedor']);
        $razon = $this->valor($c, $m, ['apellidos nombres/ razon social','apellidos nombres razon social','apellidos y nombres o razon social','razon social proveedor','nombre proveedor','razon social']);
        $fechaRaw = $this->valor($c, $m, ['fecha de emision','fecha emision','fec emision','fecha comprobante']);

        if ($fechaRaw === '' && isset($c[4])) {
            $fechaRaw = (string)$c[4];
        }
        if ($libro === ServicioSireSunat::LIBRO_RCE) {
            if ($doc === '' && isset($c[12])) {
                $doc = trim((string)$c[12]);
            }
            if ($razon === '' && isset($c[13])) {
                $razon = trim((string)$c[13]);
            }
        } else {
            if ($doc === '' && isset($c[11])) {
                $doc = trim((string)$c[11]);
            }
            if ($razon === '' && isset($c[12])) {
                $razon = trim((string)$c[12]);
            }
        }
        $fecha = $this->fechaSql($fechaRaw);
        $estado = $this->valor($c, $m, ['est comp','estado comprobante','estado registro']);
        if ($libro === ServicioSireSunat::LIBRO_RCE) {
            $base = $this->numero($this->valor($c, $m, ['bi gravado dg'])) + $this->numero($this->valor($c, $m, ['bi gravado dgng'])) + $this->numero($this->valor($c, $m, ['bi gravado dng']));
            $igv = $this->numero($this->valor($c, $m, ['igv / ipm dg','igv ipm dg'])) + $this->numero($this->valor($c, $m, ['igv / ipm dgng','igv ipm dgng'])) + $this->numero($this->valor($c, $m, ['igv / ipm dng','igv ipm dng']));
        } else {
            $base = $this->numero($this->valor($c, $m, ['bi gravada'])) - $this->numero($this->valor($c, $m, ['dscto bi']));
            $igv = $this->numero($this->valor($c, $m, ['igv / ipm','igv ipm'])) - $this->numero($this->valor($c, $m, ['dscto igv / ipm','dscto igv ipm']));
        }
        $total = $this->numero($this->valor($c, $m, ['total cp','importe total']));
        return ['car_sunat' => $car ?: null,'fecha_emision' => $fecha,'tipo_comprobante' => $tipo ?: null,'serie' => $serie ?: null,'numero' => $numero ?: null,'documento' => $doc ?: null,'razon_social' => $razon ?: null,'base_imponible' => $base,'igv' => $igv,'importe_total' => $total,'estado_registro' => $estado ?: null];
    }

    private function indice(array $m, array $aliases): ?int
    {
        foreach ($aliases as $a) {
            $k = $this->clave($a);
            if (isset($m[$k])) {
                return $m[$k];
            }
        }
return null;
    }

    private function valor(array $c, array $m, array $aliases): string
    {
        $i = $this->indice($m, $aliases);
        return $i === null ? '' : trim((string)($c[$i] ?? ''));
    }
    private function clave(string $v): string
    {
        $v = $this->limpiarBom(trim($v));
        if (function_exists('iconv')) {
            $a = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
            if (is_string($a) && $a !== '') {
                $v = $a;
            }
        }
$v = strtolower($v);
        $v = preg_replace('/[^a-z0-9]+/', ' ', $v) ?? $v;
        return trim(preg_replace('/\s+/', ' ', $v) ?? $v);
    }
    private function limpiarBom(string $v): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $v) ?? $v;
    }

    private function mapearRviePosicional(array $c): ?array
    {
        $tipo = $c[6] ?? '';
        $serie = $c[7] ?? '';
        $numero = $c[8] ?? '';
        $car = $c[3] ?? '';
        if ($tipo === '' && $serie === '' && $numero === '' && $car === '') {
            return null;
        }
return ['car_sunat' => $car ?: null,'fecha_emision' => $this->fechaSql($c[4] ?? ''),'tipo_comprobante' => $tipo ?: null,'serie' => $serie ?: null,'numero' => $numero ?: null,'documento' => ($c[11] ?? '') ?: null,'razon_social' => ($c[12] ?? '') ?: null,'base_imponible' => $this->numero($c[14] ?? '') - $this->numero($c[15] ?? ''),'igv' => $this->numero($c[16] ?? '') - $this->numero($c[17] ?? ''),'importe_total' => $this->numero($c[25] ?? ''),'estado_registro' => ($c[34] ?? '') ?: null];
    }

    private function mapearRcePosicional(array $c): ?array
    {
        $tipo = $c[6] ?? '';
        $serie = $c[7] ?? '';
        $numero = $c[9] ?? '';
        $car = $c[3] ?? '';
        if ($tipo === '' && $serie === '' && $numero === '' && $car === '') {
            return null;
        }
return ['car_sunat' => $car ?: null,'fecha_emision' => $this->fechaSql($c[4] ?? ''),'tipo_comprobante' => $tipo ?: null,'serie' => $serie ?: null,'numero' => $numero ?: null,'documento' => ($c[12] ?? '') ?: null,'razon_social' => ($c[13] ?? '') ?: null,'base_imponible' => $this->numero($c[14] ?? '') + $this->numero($c[16] ?? '') + $this->numero($c[18] ?? ''),'igv' => $this->numero($c[15] ?? '') + $this->numero($c[17] ?? '') + $this->numero($c[19] ?? ''),'importe_total' => $this->numero($c[24] ?? ''),'estado_registro' => ($c[39] ?? '') ?: null];
    }

    private function upsertCabecera(string $tabla, string $pk, int $idCliente, string $periodo, int $cantidad, float $base, float $igv, float $total): int
    {
        if (!in_array($tabla, ['rvie','rce'], true) || !in_array($pk, ['id_rvie','id_rce'], true)) {
            throw new RuntimeException('Tabla SIRE inválida.');
        }
$sql = "INSERT INTO {$tabla}(id_cliente,periodo,cantidad_registros,base_imponible,igv,total,estado,fecha_actualizacion) VALUES(?,?,?,?,?,?,'PROCESADO',NOW()) ON DUPLICATE KEY UPDATE cantidad_registros=VALUES(cantidad_registros),base_imponible=VALUES(base_imponible),igv=VALUES(igv),total=VALUES(total),estado='PROCESADO',fecha_actualizacion=NOW()";
        $stmt = $this->conexion->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar el resumen SIRE.');
        }
$stmt->bind_param('isiddd', $idCliente, $periodo, $cantidad, $base, $igv, $total);
        if (!$stmt->execute()) {
            $e = $stmt->error;
            $stmt->close();
            throw new RuntimeException($e);
        }
$stmt->close();
        $stmt = $this->conexion->prepare("SELECT {$pk} id FROM {$tabla} WHERE id_cliente=? AND periodo=? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('No se pudo recuperar el resumen SIRE.');
        }
$stmt->bind_param('is', $idCliente, $periodo);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$r) {
            throw new RuntimeException('No se pudo recuperar el registro SIRE procesado.');
        }
return (int)$r['id'];
    }
    private function reemplazarRvieDetalle(int $id, array $detalles): void
    {
        $s = $this->conexion->prepare('DELETE FROM rvie_detalle WHERE id_rvie=?');
        $s->bind_param('i', $id);
        $s->execute();
        $s->close();
        if ($detalles === []) {
            return;
        }
$s = $this->conexion->prepare('INSERT INTO rvie_detalle(id_rvie,car_sunat,fecha_emision,tipo_comprobante,serie,numero,documento_cliente,razon_social_cliente,base_imponible,igv,importe_total,estado_registro) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        if (!$s) {
            throw new RuntimeException('No se pudo preparar el detalle RVIE.');
        }foreach ($detalles as $d) {
            $s->bind_param('isssssssddds', $id, $d['car_sunat'], $d['fecha_emision'], $d['tipo_comprobante'], $d['serie'], $d['numero'], $d['documento'], $d['razon_social'], $d['base_imponible'], $d['igv'], $d['importe_total'], $d['estado_registro']);
            if (!$s->execute()) {
                $e = $s->error;
                $s->close();
                throw new RuntimeException($e);
            }
        }
$s->close();
    }
    private function reemplazarRceDetalle(int $id, array $detalles): void
    {
        $s = $this->conexion->prepare('DELETE FROM rce_detalle WHERE id_rce=?');
        $s->bind_param('i', $id);
        $s->execute();
        $s->close();
        if ($detalles === []) {
            return;
        }
$s = $this->conexion->prepare('INSERT INTO rce_detalle(id_rce,car_sunat,fecha_emision,tipo_comprobante,serie,numero,ruc_proveedor,razon_social_proveedor,base_imponible,igv,importe_total,estado_registro) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        if (!$s) {
            throw new RuntimeException('No se pudo preparar el detalle RCE.');
        }foreach ($detalles as $d) {
            $s->bind_param('isssssssddds', $id, $d['car_sunat'], $d['fecha_emision'], $d['tipo_comprobante'], $d['serie'], $d['numero'], $d['documento'], $d['razon_social'], $d['base_imponible'], $d['igv'], $d['importe_total'], $d['estado_registro']);
            if (!$s->execute()) {
                $e = $s->error;
                $s->close();
                throw new RuntimeException($e);
            }
        }
$s->close();
    }
    private function numero(string $v): float
    {
        $v = trim($v);
        if ($v === '') {
            return 0.0;
        }
$v = str_replace(["\xC2\xA0",' '], '', $v);
        if (str_contains($v, ',') && !str_contains($v, '.')) {
            $v = str_replace(',', '.', $v);
        } else {
            $v = str_replace(',', '', $v);
        }
        return is_numeric($v) ? (float)$v : 0.0;
    }
    private function fechaSql(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }foreach (['d/m/Y','Y-m-d','d-m-Y'] as $f) {
            $d = DateTimeImmutable::createFromFormat($f, substr($v, 0, 10));
            if ($d instanceof DateTimeImmutable) {
                return $d->format('Y-m-d');
            }
        }
return null;
    }
    private function aUtf8(string $v): string
    {
        if (preg_match('//u', $v)) {
            return $v;
        }
if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($v, 'UTF-8', 'Windows-1252,ISO-8859-1');
        }
return $v;
    }
}
