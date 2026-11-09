<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/sincronizacion/ColaNocturna.php';
require_once __DIR__.'/../../servicios/sincronizacion/ResumenSincronizacion.php';
require_once __DIR__.'/../../servicios/sincronizacion/EstadoSireGuardado.php';
asesconExigirLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
if(!asesconEsPrincipal($conexion)){http_response_code(403);echo json_encode(['error'=>'Sin permiso']);exit;}
                                                                                           
session_write_close();
try{
    $compacto=(string)($_GET['compacto']??'')==='1';
    $fecha=ResumenSincronizacion::fechaConsulta((string)($_GET['fecha']??''));
    $anioActual=(int)(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y');
    $anioEntrada=(string)($_GET['anio']??$anioActual);
    if(!preg_match('/^20\d{2}$/D',$anioEntrada))throw new InvalidArgumentException('Año inválido.');
    $anioConsulta=(int)$anioEntrada;
    if($anioConsulta<2021||$anioConsulta>$anioActual)throw new InvalidArgumentException('Selecciona un año entre 2021 y el actual.');
    $clientes=[];
    if(!$compacto){
        $r=$conexion->query("SELECT id_cliente,ruc,razon_social,nombre_comercial,celular FROM clientes WHERE estado='ACTIVO' ORDER BY id_cliente");
        while($c=$r->fetch_assoc())$clientes[]=$c;
    }
    $cola=new ColaNocturna();
    $datos=ResumenSincronizacion::construir($cola->resumen(),$clientes,$fecha);
    if(!$compacto){
        $inventario=new EstadoSireGuardado($conexion);
        $datos['catalogo_clientes']=$clientes;
        $datos['cobertura_sire']=$inventario->inventario($anioConsulta);
        $datos['periodos_sire']=EstadoSireGuardado::periodosAnio($anioConsulta);
        $datos['anio_consulta']=$anioConsulta;
                                                                            
                                                                       
    $datos['cobertura_buzones']=[];
    $anio=(string)$anioConsulta;
    foreach(['SOL'=>'buzon_sol_sunat','SUNAFIL'=>'buzon_sunafil'] as $codigo=>$tabla){
        $sql="SELECT id_cliente,DATE_FORMAT(COALESCE(fecha_recepcion,fecha_documento),'%Y%m') periodo,COUNT(*) registros FROM {$tabla} WHERE COALESCE(fecha_recepcion,fecha_documento)>=? AND COALESCE(fecha_recepcion,fecha_documento)<? GROUP BY id_cliente,periodo";
        $s=$conexion->prepare($sql);
        if(!$s)continue;
        $inicio=$anio.'-01-01';$fin=((int)$anio+1).'-01-01';
        $s->bind_param('ss',$inicio,$fin);$s->execute();$rs=$s->get_result();
        while($fila=$rs->fetch_assoc()){
            $datos['cobertura_buzones'][(int)$fila['id_cliente']][$codigo][(string)$fila['periodo']]=max(0,(int)$fila['registros']);
        }
        $s->close();
    }
    }
    else{$datos['empresas']=[];}
    $marca=asesconRutaAlmacenamiento().'/sincronizacion/ultima_ejecucion_cron';
    $ultima=is_file($marca)?(int)file_get_contents($marca):0;
    $datos['cron_activo']=$ultima>0&&time()-$ultima<600;
                                                                              
    $datos['runtime_preparado']=is_file(dirname(__DIR__,3).'/asescon_recursos_privados/RUNTIME_OK');
    $datos['ultima_senal']=$ultima?(new DateTimeImmutable('@'.$ultima))->setTimezone(new DateTimeZone('America/Lima'))->format('d/m/Y H:i:s'):null;
    $datos['pausado']=$cola->estaPausada();
    echo json_encode($datos,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
}catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(503);error_log('Estado sincronizacion: '.get_class($e));echo json_encode(['error'=>'No se pudo leer el progreso guardado. Revisa el registro privado del servidor.']);}
