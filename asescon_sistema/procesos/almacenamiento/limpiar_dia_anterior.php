<?php
declare(strict_types=1);

   
                                                          
  
                                                  
                                                                 
                                                                        
                                                                          
  
                                                                         
                                                                          
   
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/Lima');
umask(0077);

require_once dirname(__DIR__, 2).'/configuracion/rutas_privadas.php';
require_once dirname(__DIR__, 2).'/configuracion/conexiones_bases_datos.php';

$argumentos = array_slice($argv, 1);
if (array_diff($argumentos, ['--execute', '--cron', '--dry-run'])) {
    fwrite(STDERR, "Uso: php limpiar_dia_anterior.php [--dry-run|--execute|--cron]\n");
    exit(2);
}
$ejecutar = in_array('--execute', $argumentos, true) || in_array('--cron', $argumentos, true);
$programado = in_array('--cron', $argumentos, true);
$ahora = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
$hoy = $ahora->format('Y-m-d');
$limite = $hoy.' 00:00:00';
$limiteEpoch = $ahora->setTime(0, 0)->getTimestamp();

                                                                                
if ($programado) {
    $minuto = (int)$ahora->format('H') * 60 + (int)$ahora->format('i');
    if ($minuto < 40 || $minuto > 110) { exit(0); }
}

$raiz = realpath(asesconRutaAlmacenamiento());
if ($raiz === false || basename($raiz) !== 'almacenamiento'
    || basename(dirname($raiz)) !== 'asescon_datos_privados') {
    throw new RuntimeException('La ubicación del almacenamiento no corresponde a ASESCON.');
}
$sync = $raiz.'/sincronizacion';
if (!is_dir($sync) && !mkdir($sync, 0700, true) && !is_dir($sync)) {
    throw new RuntimeException('No se pudo preparar el directorio de sincronización.');
}
$marca = $sync.'/ultima_limpieza_diaria';
if ($programado && is_file($marca) && trim((string)file_get_contents($marca)) === $hoy) { exit(0); }

$candado = fopen($sync.'/limpieza.lock', 'c+');
if ($candado === false || !flock($candado, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Ya existe una limpieza en ejecución.\n");
    exit(0);
}

                                                                          
$protegidos = [
    'sincronizacion', 'textos', 'casos', 'auditoria',
    'configuracion', 'credenciales', 'respaldos', 'backups', 'sesiones',
];
$resumen = ['fecha'=>$hoy, 'modo'=>$ejecutar ? 'ejecucion' : 'simulacion',
    'archivos'=>0, 'bytes'=>0, 'tablas'=>[]];

function limpiarArchivoTemporal(string $ruta, int $limiteEpoch, bool $ejecutar, array &$resumen): void
{
                                                  
    if (is_link($ruta) || !is_file($ruta)) { return; }
    $nombre = basename($ruta);
                                                                          
                                                                        
    if ($nombre === '' || $nombre[0] === '.' || preg_match('/\.(?:lock|flag|php|sh)$/i', $nombre)) { return; }
    $mtime = @filemtime($ruta);
    if ($mtime === false || $mtime >= $limiteEpoch) { return; }
    $bytes = max(0, (int)@filesize($ruta));
    if ($ejecutar && !@unlink($ruta)) {
        throw new RuntimeException('No se pudo eliminar un archivo temporal de ASESCON.');
    }
    $resumen['archivos']++;
    $resumen['bytes'] += $bytes;
}

                                                                                  
$operaciones = [
    'principal' => [
        ['rvie', 'fecha_actualizacion IS NULL OR fecha_actualizacion < ?'],
        ['rce', 'fecha_actualizacion IS NULL OR fecha_actualizacion < ?'],
        ['sire', 'fecha_actualizacion IS NULL OR fecha_actualizacion < ?'],
        ['sire_descargas', 'COALESCE(fecha_procesamiento,fecha_descarga,fecha_solicitud) IS NULL OR COALESCE(fecha_procesamiento,fecha_descarga,fecha_solicitud) < ?'],
        ['consultas_declaraciones_sunat', 'consultado_en < ?'],
        ['declaraciones_oficiales_sunat','consultado_en < ?'],
        ['declaraciones_sunat','fecha_ultima_consulta < ?'],
                                                                         
                                                                                
        ['buzon_sol_sunat', '(fecha_sincronizacion IS NULL OR fecha_sincronizacion < ?)'
            .' AND NOT EXISTS (SELECT 1 FROM buzon_sol_casos c WHERE c.id_documento=buzon_sol_sunat.id_documento)'
            .' AND NOT EXISTS (SELECT 1 FROM buzon_sol_avances a WHERE a.id_documento=buzon_sol_sunat.id_documento)'
            .' AND NOT EXISTS (SELECT 1 FROM cola_avisos_buzones n WHERE n.entidad=\'SUNAT\' AND n.estado=\'PENDIENTE\' AND n.id_documento=buzon_sol_sunat.id_documento)'],
        ['buzon_sunafil', '(fecha_sincronizacion IS NULL OR fecha_sincronizacion < ?)'
            .' AND NOT EXISTS (SELECT 1 FROM cola_avisos_buzones n WHERE n.entidad=\'SUNAFIL\' AND n.estado=\'PENDIENTE\' AND n.id_documento=buzon_sunafil.id_documento)'],
        ['cola_avisos_buzones', 'estado=\'ENVIADO\' AND enviado_en < ?'],
        ['detracciones_archivos_sunat','importado_en < ?'],
        ['archivos_comprobantes','actualizado_en < ?'],
        ['cpe','fecha_consulta < ?'],
        ['tipos_cambio','fecha < ?'],
        ['alertas_sunat','fecha_alerta < ?'],
        ['padron_ssco','1=1'],
        ['actualizacion_padron_ssco','1=1'],
        ['comprobantes_sunat','1=1'],
        ['detracciones','1=1'],
    ],
    'historico_sunat' => [
        ['sunat_consultas_respuestas', 'id_consulta IN (SELECT id_consulta FROM sunat_historial_consultas WHERE fecha_inicio < ?)'],
        ['sunat_historial_cambios', 'fecha_cambio < ?'],
        ['sunat_historial_consultas', 'fecha_inicio < ?'],
        ['sunat_detalles_declaracion', 'consultado_en < ?'],
        ['sunat_pagos', 'ultima_consulta < ?'],
        ['sunat_detracciones', 'ultima_consulta < ?'],
        ['sunat_declaraciones', 'ultima_consulta < ?'],
    ],
];

                                                                 
$bases = [
    'principal'=>asesconConexionPrincipal(),
    'historico_sunat'=>asesconConexionHistoricoSunat(),
];

$candadosTrabajadores = [];
$mantenimiento = $sync.'/mantenimiento.flag';
$codigoSalida = 0;
try {
    if ($ejecutar) {
                                                                           
                                                                         
        $locks = [$sync.'/almacenamiento.lock', $sync.'/coordinador.lock', $sync.'/consultas_sire.lock'];
        $rutaSol = $raiz.'/sunat/buzon_sol';
        $rutaSunafil = $raiz.'/sunafil/buzon_sunafil';
        $locks[] = $raiz.'/sunat/padron_actualizacion.lock';
        if (!is_dir($rutaSol)) { mkdir($rutaSol, 0700, true); }
        if (!is_dir($rutaSunafil)) { mkdir($rutaSunafil, 0700, true); }
        foreach ([0,1,2,3] as $slot) { $locks[]=$rutaSol.'/cron_'.$slot.'.lock'; }
        $locks[]=$rutaSunafil.'/cron.lock';
        foreach ($locks as $rutaLock) {
            $f = fopen($rutaLock, 'c+');
            if (!$f || !flock($f, LOCK_EX | LOCK_NB)) {
                if (is_resource($f)) { fclose($f); }
                fwrite(STDERR, "Hay un proceso de sincronización activo; se reintentará la limpieza en el siguiente cron.\n");
                exit(0);
            }
            $candadosTrabajadores[]=$f;
        }
        if (file_put_contents($mantenimiento, $hoy, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo iniciar el mantenimiento seguro.');
        }
        chmod($mantenimiento, 0600);
    }

    foreach ($operaciones as $perfil=>$tablas) {
        $db=$bases[$perfil];
        foreach ($tablas as [$tabla,$condicion]) {
                                                                                
            $verificar=$db->prepare('SELECT COUNT(*) total FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
            $verificar->bind_param('s',$tabla);
            $verificar->execute();
            $existe=(int)($verificar->get_result()->fetch_assoc()['total']??0)>0;
            $verificar->close();
            if(!$existe){$resumen['tablas'][$perfil.'.'.$tabla]='NO_EXISTE';continue;}
            $consulta=$ejecutar
                ? "DELETE FROM `{$tabla}` WHERE {$condicion} LIMIT 500"
                : "SELECT COUNT(*) AS total FROM `{$tabla}` WHERE {$condicion}";
            $numero=0;
            do {
                $stmt=$db->prepare($consulta);
                if(str_contains($condicion,'?'))$stmt->bind_param('s',$limite);
                $stmt->execute();
                $n=$ejecutar?$stmt->affected_rows:(int)$stmt->get_result()->fetch_assoc()['total'];
                $stmt->close();
                $numero+=$n;
                                                                           
                                                                                    
            }while($ejecutar&&$n===500);
            $resumen['tablas'][$perfil.'.'.$tabla]=$numero;
        }
    }

                                                                        
                                                                          
    $iterator = new DirectoryIterator($raiz);
    foreach ($iterator as $entrada) {
        if ($entrada->isDot() || $entrada->isLink()) { continue; }
        if ($entrada->isFile()) {
            limpiarArchivoTemporal($entrada->getPathname(),$limiteEpoch,$ejecutar,$resumen);
            continue;
        }
        if (!$entrada->isDir()) { continue; }
        if (in_array($entrada->getFilename(), $protegidos, true)) { continue; }
        $arbol = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($entrada->getPathname(), FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($arbol as $archivo) {
            limpiarArchivoTemporal($archivo->getPathname(), $limiteEpoch, $ejecutar, $resumen);
        }
    }

                                                                           
    foreach (glob($sync.'/trabajo_*.json') ?: [] as $ruta) {
        if (is_link($ruta)) { continue; }
        $datos = json_decode((string)@file_get_contents($ruta), true);
        if (!is_array($datos) || ($datos['fecha']??$hoy)>=$hoy) { continue; }
        if ($ejecutar && !@unlink($ruta)) { throw new RuntimeException('No se pudo depurar una tarea completada.'); }
        $resumen['trabajos_antiguos'] = ($resumen['trabajos_antiguos']??0) + 1;
    }
    if ($ejecutar && file_put_contents($marca, $hoy, LOCK_EX) === false) {
        throw new RuntimeException('La limpieza terminó pero no pudo guardar su marca de fecha.');
    }
    echo json_encode($resumen, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
                                                                                
                                                             
    error_log('ASESCON limpieza diaria: '.get_class($e));
    fwrite(STDERR, "La limpieza no terminó. Revisa el log protegido y no borres las bases manualmente.\n");
    $codigoSalida = 1;
} finally {
    if ($ejecutar && is_file($mantenimiento)) { @unlink($mantenimiento); }
    foreach (array_reverse($candadosTrabajadores) as $f) { flock($f, LOCK_UN); fclose($f); }
    flock($candado, LOCK_UN);
    fclose($candado);
}
exit($codigoSalida);
