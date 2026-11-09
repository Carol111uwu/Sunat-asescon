<?php
declare(strict_types=1);

                                                                       
final class ResumenSincronizacion
{
    public const MODULOS=['RVIE'=>'Ventas RVIE','RCE'=>'Compras RCE','DECLARACIONES'=>'Declaraciones','PADRON_RUC'=>'Padrón RUC','SSCO'=>'SSCO','SOL'=>'Buzón SOL','SUNAFIL'=>'Buzón SUNAFIL'];
    private const ESTADOS=['PENDIENTE'=>'pendientes','PROCESANDO'=>'procesando','COMPLETADO'=>'completados','ERROR'=>'errores'];
    private const ERRORES=[
        'CONSULTA_FALLIDA'=>'La consulta no terminó. Abre el módulo para revisar el diagnóstico; se conservan los datos anteriores.',
        'FALTAN_TABLAS_DECLARACIONES'=>'Faltan tablas del módulo de declaraciones. Revisa su diagnóstico antes de reintentar.',
        'CREDENCIALES_NO_DESCIFRABLES'=>'No se pudieron descifrar las credenciales. Comprueba la clave privada existente; no la sustituyas.',
        'SUNAT_SOL_NO_DISPONIBLE'=>'La entrada a SUNAT SOL no respondió correctamente. Se conservan los datos anteriores.',
        'ERROR_MYSQL'=>'No se completó una operación de base de datos. Revisa el registro privado del servidor.',
        'CONSULTA_SUNAT_FALLIDA'=>'SUNAT rechazó o no completó la consulta. Se conservan los datos anteriores.',
        'SIRE_CERO_CON_HISTORIAL'=>'La propuesta descargada contiene cero comprobantes y ya existían movimientos para ese período. No se eliminaron: revisa la discrepancia en SUNAT.',
        'SIRE_CERO_NO_CONFIRMADO'=>'No fue posible comprobar que la propuesta oficial esté vacía. Se conservaron los datos y el archivo para revisión manual.',
        'TICKET_SUNAT_SIN_RESPUESTA'=>'SUNAT no finalizó la consulta en dos horas. Mes pendiente de verificar; nunca se presume que tenga cero comprobantes.',
        'BUZON_SIN_RESPUESTA'=>'El trabajador del buzón no terminó en dos horas. Revisa su cron y los permisos de almacenamiento privado.',
        'BUZON_SOL_FALLIDO'=>'El conector SOL no terminó. Abre Buzón SOL para ver el diagnóstico del cliente.',
        'BUZON_SUNAFIL_FALLIDO'=>'El conector SUNAFIL no terminó. Abre SUNAFIL para ver el diagnóstico del cliente.',
        'CLIENTE_INACTIVO'=>'La empresa ya no está activa. No se realizó la consulta.',
    ];
    public static function fechaConsulta(string $valor,?DateTimeImmutable $hoy=null):string
    {
        $hoy=($hoy??new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->setTimezone(new DateTimeZone('America/Lima'));
        if($valor==='')return $hoy->format('Y-m-d');
        $fecha=DateTimeImmutable::createFromFormat('!Y-m-d',$valor,new DateTimeZone('America/Lima'));
        if(!$fecha||$fecha->format('Y-m-d')!==$valor||$valor>$hoy->format('Y-m-d')||$valor<$hoy->modify('-6 days')->format('Y-m-d'))throw new InvalidArgumentException('Selecciona uno de los últimos siete días.');
        return $valor;
    }
    public static function construir(array $trabajos,array $clientes,string $fecha):array
    {
        $catalogo=[];foreach($clientes as $c)$catalogo[(int)$c['id_cliente']]=$c;
        $grupos=[];$modulos=[];$totales=self::contadores();$ultima=0;
                                                                              
                                                                          
        $recientes=[];
        foreach($trabajos as $t){
            if((string)($t['fecha']??'')>$fecha)continue;
            $clave=(int)($t['id_cliente']??0).'|'.($t['modulo']??'').'|'.($t['periodo']??'');
            $anterior=$recientes[$clave]??null;
            if(!$anterior||strcmp((string)$t['fecha'],(string)$anterior['fecha'])>0
                ||((string)$t['fecha']===(string)$anterior['fecha']&&strcmp((string)($t['actualizado_en']??''),(string)($anterior['actualizado_en']??''))>0)){
                $recientes[$clave]=$t;
            }
        }
        foreach($recientes as $t){
            $estado=(string)($t['estado']??'');$modulo=(string)($t['modulo']??'');
            if(!isset(self::ESTADOS[$estado],self::MODULOS[$modulo]))continue;
            $id=max(0,(int)($t['id_cliente']??0));$cliente=$catalogo[$id]??[];
            if(!isset($grupos[$id]))$grupos[$id]=[
                'id_cliente'=>$id,'nombre'=>$id===0?'Padrones generales':(trim((string)($cliente['razon_social']??''))?:'Empresa no disponible (ID '.$id.')'),
                'ruc'=>$id===0?'':(string)($cliente['ruc']??''),'general'=>$id===0,'modulos'=>[],
            ]+self::contadores();
            if(!isset($grupos[$id]['modulos'][$modulo]))$grupos[$id]['modulos'][$modulo]=['modulo'=>$modulo,'nombre'=>self::MODULOS[$modulo],'trabajos'=>[]]+self::contadores();
            if(!isset($modulos[$modulo]))$modulos[$modulo]=['modulo'=>$modulo,'nombre'=>self::MODULOS[$modulo]]+self::contadores();
            $advertencia=$estado==='COMPLETADO'&&((int)($t['pdf_pendientes']??0)>0||($t['codigo']??'')==='PDF_PENDIENTES');
            $registros=$estado==='COMPLETADO'?max(0,(int)($t['registros']??0)):0;
            foreach([&$totales,&$grupos[$id],&$grupos[$id]['modulos'][$modulo],&$modulos[$modulo]] as &$contador){
                $contador['total']++;$contador[self::ESTADOS[$estado]]++;$contador['registros']+=$registros;$contador['advertencias']+=(int)$advertencia;
            }unset($contador);
            $actualizado=strtotime((string)($t['actualizado_en']??''))?:0;$ultima=max($ultima,$actualizado);$codigo=(string)($t['codigo']??'');
                                                                                      
            if($estado==='COMPLETADO'){$codigo=$advertencia?'PDF_PENDIENTES':'';$detalle=$advertencia?'Bandeja guardada; quedan PDF pendientes de obtener.':(!empty($t['omitido'])?'Período ya guardado; se omitió una consulta innecesaria.':'Consulta completada. Datos guardados para consultar en el módulo.');}
            elseif(isset(self::ERRORES[$codigo]))$detalle=self::ERRORES[$codigo];
            elseif($estado==='ERROR'){$codigo='CONSULTA_FALLIDA';$detalle=self::ERRORES[$codigo];}
            elseif($codigo==='TICKET_EN_PROCESO')$detalle='SUNAT está preparando el archivo. El trabajador consultará el ticket nuevamente.';
            else{$codigo='';$detalle=$estado==='PROCESANDO'?'Consulta en proceso.':'Pendiente de ejecución por Cron.';}
            $periodo=(string)($t['periodo']??'');
            $grupos[$id]['modulos'][$modulo]['trabajos'][]=[
                'periodo'=>$periodo,'periodo_texto'=>self::periodo($periodo),'estado'=>$estado,'intentos'=>max(0,(int)($t['intentos']??0)),
                'registros'=>$registros,'codigo'=>$codigo,'detalle'=>$detalle,'advertencia'=>$advertencia,
                'actualizado_en'=>$actualizado?(new DateTimeImmutable('@'.$actualizado))->setTimezone(new DateTimeZone('America/Lima'))->format('d/m/Y H:i:s'):'—',
            ];
        }
        uksort($grupos,static fn($a,$b)=>($a===0?PHP_INT_MAX:$a)<=>($b===0?PHP_INT_MAX:$b));
        foreach($grupos as &$grupo){
            $grupo['modulos']=self::ordenarModulos($grupo['modulos']);
            foreach($grupo['modulos'] as &$m){usort($m['trabajos'],static fn($a,$b)=>strcmp($b['periodo'],$a['periodo']));$m=self::finalizar($m);}unset($m);
            $grupo=self::finalizar($grupo);
        }unset($grupo);
        return self::finalizar($totales)+['fecha'=>$fecha,'empresas'=>array_values($grupos),
            'por_modulo'=>array_map([self::class,'finalizar'],self::ordenarModulos($modulos)),
            'ultima_actividad'=>$ultima?(new DateTimeImmutable('@'.$ultima))->setTimezone(new DateTimeZone('America/Lima'))->format('d/m/Y H:i:s'):null,
            'sin_avance_reciente'=>($totales['procesando']+$totales['pendientes'])>0&&$ultima>0&&time()-$ultima>1800];
    }
    private static function contadores():array{return ['total'=>0,'pendientes'=>0,'procesando'=>0,'completados'=>0,'errores'=>0,'registros'=>0,'advertencias'=>0];}
    private static function finalizar(array $c):array{$c['finalizados']=$c['completados']+$c['errores'];$c['porcentaje']=$c['total']>0?(int)floor($c['finalizados']/$c['total']*100):0;return $c;}
    private static function ordenarModulos(array $modulos):array{$orden=array_flip(array_keys(self::MODULOS));uksort($modulos,static fn($a,$b)=>$orden[$a]<=>$orden[$b]);return array_values($modulos);}
    private static function periodo(string $p):string
    {
        if(!preg_match('/^(20\d{2})(0[1-9]|1[0-2])$/D',$p,$m))return 'Consulta general';
        $meses=['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];return $meses[(int)$m[2]-1].' '.$m[1];
    }
}
