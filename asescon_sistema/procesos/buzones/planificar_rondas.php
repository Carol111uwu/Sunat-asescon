<?php
declare(strict_types=1);

/**
 * Rondas escalonadas, independientes del lote SIRE.
 * Este proceso es CLI y no lee $_SESSION ni datos de usuarios finales.
 * El historial oficial solo se modifica al completar correctamente cada conector.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/Lima');
require_once dirname(__DIR__,2).'/configuracion/conexion.php';
require_once dirname(__DIR__,2).'/configuracion/rutas_privadas.php';
require_once dirname(__DIR__,2).'/servicios/sunat/ServicioColaBuzonSol.php';
require_once dirname(__DIR__,2).'/servicios/sunafil/ServicioColaBuzonSunafil.php';
$raiz = asesconRutaAlmacenamiento().'/sincronizacion';
if (!is_dir($raiz) && !mkdir($raiz,0700,true) && !is_dir($raiz)) { exit(2); }
if (is_file($raiz.'/mantenimiento.flag') || is_file($raiz.'/pausa.flag')) { exit; }
$bloqueo = fopen($raiz.'/rondas_buzones.lock','c+');
if (!$bloqueo || !flock($bloqueo,LOCK_EX|LOCK_NB)) { exit; }
try {
    $rutaEstado=$raiz.'/rondas_buzones.json';
    $estado=is_file($rutaEstado)?json_decode((string)file_get_contents($rutaEstado),true):null;
    if (!is_array($estado)) $estado=[];
    $zona=new DateTimeZone('America/Lima');
    $ahora=new DateTimeImmutable('now',$zona);
    $fecha=$ahora->format('Y-m-d');
    $segundos=$ahora->getTimestamp();
    $intervalo=max(1800,(int)(getenv('ASESCON_INTERVALO_BUZONES')?:10800)); // por defecto 3 h
    $maxRonda=max(1,min(5,(int)(getenv('ASESCON_BUZONES_POR_RONDA')?:3)));
    $resultado=$conexion->query(
        "SELECT DISTINCT c.id_cliente FROM clientes c ".
        "JOIN credenciales_sunat cs ON cs.id_cliente=c.id_cliente ".
        "WHERE c.estado='ACTIVO' AND cs.estado=1 ".
        "AND TRIM(COALESCE(cs.usuario_sol,''))<>'' ".
        "AND TRIM(COALESCE(cs.clave_sol_cifrada,''))<>'' ORDER BY c.id_cliente"
    );
    if (!$resultado) throw new RuntimeException('No se pudo obtener el listado de clientes activos.');
    $clientes=[];
    while($fila=$resultado->fetch_assoc()) $clientes[]=(int)$fila['id_cliente'];
    foreach (['SOL','SUNAFIL'] as $tipo) {
        $cola=$tipo==='SOL'?new ServicioColaBuzonSol():new ServicioColaBuzonSunafil();
        $programados=0;
        // Primero los que no se consultaron hoy; en siguientes rondas, los más antiguos.
        usort($clientes, static function(int $a,int $b) use ($estado,$tipo):int {
            return (int)($estado[$tipo][$a]['solicitado_en']??0) <=> (int)($estado[$tipo][$b]['solicitado_en']??0);
        });
        foreach ($clientes as $id) {
            $anterior=$estado[$tipo][$id]??[];
            $fechaAnterior=(string)($anterior['dia']??'');
            $ultimo=(int)($anterior['solicitado_en']??0);
            // Ronda de las 00:00 (por etapas), más repasos espaciados durante el día.
            $necesario=$fechaAnterior!==$fecha || $segundos-$ultimo>=$intervalo;
            if (!$necesario && $segundos-$ultimo<900) continue;
            try {
                $actual=$cola->obtenerEstadoCliente($id);
            } catch (Throwable $error) {
                error_log('Estado de ronda '.$tipo.' id_cliente='.$id.': '.get_class($error));
                continue;
            }
            $errorTemporal = ($actual['estado'] ?? '') === 'ERROR' && empty($actual['id_documento'])
                && preg_match('/TIEMPO_AGOTADO|ERR_CONNECTION|ERR_TIMED_OUT|HTTP 5[0-9]{2}|tiempo permitido/i', (string)($actual['mensaje'] ?? ''));
            if (!$necesario && !$errorTemporal) continue;
            $enCurso=in_array(strtoupper((string)($actual['estado']??'')),['PENDIENTE','PROCESANDO','EN_COLA'],true);
            if ($enCurso) {
                // No crear solicitudes duplicadas ni repetir en bucle un cliente ocupado.
                $estado[$tipo][$id]=['dia'=>$fecha,'solicitado_en'=>$segundos,'resultado'=>'YA_EN_COLA'];
                continue;
            }
            try {
                $solicitud=$cola->solicitar($id,0);
                $estado[$tipo][$id]=[
                    'dia'=>$fecha,'solicitado_en'=>$segundos,
                    'resultado'=>!empty($solicitud['reutilizada'])?'REUTILIZADA':'PROGRAMADA',
                ];
                if (++$programados >= $maxRonda) break;
            } catch(Throwable $error) {
                $estado[$tipo][$id]=['dia'=>$fecha,'solicitado_en'=>$segundos,'resultado'=>'ERROR_EN_COLA'];
                error_log('Ronda '.$tipo.' id_cliente='.$id.': '.get_class($error));
            }
        }
        echo $ahora->format(DATE_ATOM).' '.$tipo.': '.$programados.' consultas programadas; '.count($clientes)." clientes activos.\n";
    }
    $estado['ultima_planificacion']=$segundos;
    $tmp=$rutaEstado.'.'.bin2hex(random_bytes(5)).'.tmp';
    $ok=file_put_contents($tmp,json_encode($estado,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX);
    if($ok===false || !rename($tmp,$rutaEstado)) throw new RuntimeException('No se pudo guardar el control de rondas.');
    chmod($rutaEstado,0600);
} catch(Throwable $error) {
    error_log('Rondas automáticas de buzones: '.get_class($error).': '.$error->getMessage());
    exit(1);
} finally {
    flock($bloqueo,LOCK_UN);
    fclose($bloqueo);
}
