<?php
declare(strict_types=1);
                                                                          
$estadoProceso = strtoupper((string)($sincronizacion['estado'] ?? 'SIN_SOLICITUD'));
$etiquetasProceso = [
    'PENDIENTE'=>'En cola', 'PROCESANDO'=>'Procesando', 'COMPLETADO'=>'Completado',
    'ADJUNTOS_PENDIENTES'=>'Bandeja guardada; PDF pendientes', 'ERROR'=>'Error',
    'SIN_SOLICITUD'=>'Sin consulta anterior',
];
$etiquetaProceso=$etiquetasProceso[$estadoProceso]??'Estado de consulta';
$eProceso=static fn($valor):string=>htmlspecialchars((string)$valor,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$fechaProceso=static function($valor)use($eProceso):string{
    $hora=strtotime((string)$valor);
    return $hora!==false?$eProceso(date('d/m/Y H:i:s',$hora)):'—';
};
$cronProceso=$estado_cron??[];
$colaProceso=$detalle_cola??[];
$documentoProceso=(int)($sincronizacion['id_documento']??0);
?>
<details class="buzon-proceso-detalle" data-buzon-estado="<?=$eProceso($estadoProceso)?>" data-buzon-documento="<?=$documentoProceso?>">
  <summary>
    <span class="buzon-expandir-mas" aria-hidden="true"></span>
    <span class="buzon-proceso-titulo"><strong data-resumen-estado><?=$eProceso($etiquetaProceso)?></strong><small data-resumen-proceso><?=$eProceso($estadoProceso==='ERROR'?'La última consulta necesita atención':($estadoProceso==='COMPLETADO'?'Datos recibidos; puedes actualizar la lista':($estadoProceso==='PENDIENTE'?'Esperando turno del procesador':($estadoProceso==='PROCESANDO'?'Conectando con el portal oficial':'Consulta del cliente seleccionado'))))?></small></span>
    <span class="buzon-proceso-ver" data-detalles-etiqueta>Ver detalles</span>
  </summary>
  <div class="buzon-proceso-contenido">
    <p data-detalle-mensaje><?=$eProceso($sincronizacion['mensaje']??'No se ha solicitado ninguna consulta para esta empresa.')?></p>
    <dl>
      <div><dt>Empresa / buzón</dt><dd><?=$eProceso($clientes[$idCliente]['razon_social']??'Cliente seleccionado')?> · <?=$eProceso($buzonCodigo??'')?></dd></div>
      <div><dt>Solicitado</dt><dd data-detalle-solicitado><?=$fechaProceso($sincronizacion['solicitado_en']??null)?></dd></div>
      <div><dt>Última actualización</dt><dd data-detalle-actualizado><?=$fechaProceso($sincronizacion['actualizado_en']??null)?></dd></div>
      <div><dt>Cola de espera</dt><dd data-detalle-cola><?=isset($colaProceso['posicion'])?'Turno '.(int)$colaProceso['posicion'].' de '.(int)$colaProceso['pendientes']:(int)($colaProceso['pendientes']??0).' solicitud(es) pendientes'?></dd></div>
      <div><dt>Tiempo desde la solicitud</dt><dd data-detalle-espera><?=isset($colaProceso['espera_segundos'])?(int)floor((int)$colaProceso['espera_segundos']/60).' min':'—'?></dd></div>
      <div><dt>Procesador Cron</dt><dd data-detalle-cron><?=$eProceso($cronProceso['mensaje']??'Sin datos del procesador.')?></dd></div>
      <div><dt>Última ejecución de Cron</dt><dd data-detalle-cron-ultima><?=!empty($cronProceso['ultima'])?$eProceso(date('d/m/Y H:i:s',(int)$cronProceso['ultima'])):'No registrada'?></dd></div>
      <div><dt>Registros consultados / nuevos</dt><dd data-detalle-resultados><?=(int)($sincronizacion['total']??0)?> / <?=(int)($sincronizacion['nuevos']??0)?></dd></div>
    </dl>
    <?php if($documentoProceso===0):?><button type="button" class="reference-btn" data-actualizar-consulta>Comprobar estado ahora</button><?php endif;?>
    <small class="buzon-proceso-ayuda">Puedes cerrar esta pantalla. La consulta sigue en el servidor si el procesador Cron está activo. Si aparece «Error», corrige Cron o los accesos y vuelve a consultar.</small>
  </div>
</details>
