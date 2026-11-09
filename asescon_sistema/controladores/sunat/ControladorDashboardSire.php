<?php
declare(strict_types=1);

require_once __DIR__.'/../../modelos/sunat/RepositorioLecturaSire.php';
require_once __DIR__.'/../../servicios/sunat/PeriodosSire.php';
require_once __DIR__.'/../../servicios/sunat/PresentacionDatosSire.php';

   
                                                                                 
                                                                   
   
final class ControladorDashboardSire
{
    public function __construct(private mysqli $conexion) {}

    public function preparar(array $entrada): array
    {
        asesconExigirPermiso($this->conexion, 'SIRE', 'ver');
        $repo = new RepositorioLecturaSire($this->conexion);
        $repo->usarSoloCacheDeHoy();
        $filtro = asesconFiltroClientesSql($this->conexion, 'c');
        $clientes = $repo->listarClientesVisibles($filtro, true);
        $idSolicitado = filter_var($entrada['cliente'] ?? 0, FILTER_VALIDATE_INT);
        $idCliente = $idSolicitado !== false && $idSolicitado > 0 ? (int)$idSolicitado : 0;
        $seleccionInvalida = false;
        $clienteSel = null;
        foreach ($clientes as $cliente) {
            if ((int)$cliente['id_cliente'] === $idCliente) {
                $clienteSel = $cliente;
                break;
            }
        }
        if ($idCliente > 0 && ($clienteSel === null || !asesconPuedeVerCliente($this->conexion,$idCliente))) {
            $idCliente = 0;
            $clienteSel = null;
            $seleccionInvalida = true;
        }

        $hoyLima = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
        $periodoDefecto = $hoyLima->format('Ym');
        $periodoBase = preg_replace('/\D+/', '', (string)($entrada['periodo'] ?? $periodoDefecto)) ?: $periodoDefecto;
        $periodo = (isset($entrada['anio']) || isset($entrada['mes']))
            ? PeriodosSire::construir((string)($entrada['anio'] ?? substr($periodoBase,0,4)),
                                     (string)($entrada['mes'] ?? substr($periodoBase,4,2)))
            : $periodoBase;
        if (!PeriodosSire::permitido($periodo)) $periodo=$periodoDefecto;
        $esAnual = PeriodosSire::esAnual($periodo);
        $anio = PeriodosSire::anio($periodo);
        $mes = substr($periodo,4,2);
        $esGlobal = $idCliente === 0;
        $periodoEtiqueta = PeriodosSire::etiqueta($periodo);

        $vacia = ['cantidad_registros'=>0,'base_imponible'=>0,'igv'=>0,'total'=>0,
                  'fecha_actualizacion'=>null,'estado'=>'SIN_DATOS'];
        $rv=$vacia;
        $rc=$vacia;
        $rvGlobal=[];
        $rcGlobal=[];
        $comparacionMensual=[];
        if ($esGlobal) {
                                                                                           
            $rvGlobal=$repo->obtenerResumenGlobal('RVIE',$filtro,$periodo);
            $rcGlobal=$repo->obtenerResumenGlobal('RCE',$filtro,$periodo);
        } else {
            if ($esAnual) {
                $rv=array_merge($vacia,$repo->obtenerCabeceraAnual('RVIE',$idCliente,$anio));
                $rc=array_merge($vacia,$repo->obtenerCabeceraAnual('RCE',$idCliente,$anio));
            } else {
                $rv=array_merge($vacia,$repo->obtenerCabeceraPeriodo('RVIE',$idCliente,$periodo));
                $rc=array_merge($vacia,$repo->obtenerCabeceraPeriodo('RCE',$idCliente,$periodo));
            }
            $comparacionMensual=$repo->obtenerSerieMensual($anio,$idCliente,$filtro);
        }

        $estadoLibro = static function(array $cabecera) use ($esAnual,$periodo,$hoyLima): array {
            $fecha=(string)($cabecera['fecha_actualizacion']??'');
            $importado=$esAnual ? ($cabecera['estado']??'')==='ANUAL' : ($cabecera['estado']??'')==='PROCESADO';
            if (!$importado) return ['codigo'=>'SIN_IMPORTAR','etiqueta'=>'Sin importación local','actualizacion'=>null];
            if ($esAnual) return ['codigo'=>'ANUAL','etiqueta'=>'Acumulado de meses importados','actualizacion'=>$fecha];
            $cantidad=(int)($cabecera['cantidad_registros']??0);
            if ($cantidad===0) return ['codigo'=>'CERO','etiqueta'=>'Importado: 0 comprobantes','actualizacion'=>$fecha];
            $antiguo=$periodo===$hoyLima->format('Ym') && $fecha!=='' && strtotime($fecha)!==false
                && strtotime($fecha)<$hoyLima->getTimestamp()-72*3600;
            return ['codigo'=>$antiguo?'ANTIGUO':'IMPORTADO',
                    'etiqueta'=>$antiguo?'Importado · revisar fecha':'Importado en ASESCON',
                    'actualizacion'=>$fecha];
        };
        $estadosSire=['RVIE'=>$estadoLibro($rv),'RCE'=>$estadoLibro($rc)];

                                                                                     
                                                                                        
        $historialSoloSeleccion=($entrada['historial_modo']??'seleccion')!=='completo';
        $porPaginaHistorial=50;
        $totalHistorial=$repo->contarHistorialDashboard($filtro,$idCliente>0?$idCliente:null,
                                                      $periodo,$historialSoloSeleccion);
        $clientesHistorial=$esGlobal ? $repo->clientesConHistorialDashboard($filtro,$periodo,$historialSoloSeleccion) : [];
        $totalPaginasHistorial=$esGlobal ? max(1,count($clientesHistorial)) : max(1,(int)ceil($totalHistorial/$porPaginaHistorial));
        $paginaHistorial=max(1,min($totalPaginasHistorial,(int)($entrada['historial_pagina']??1)));
        $clientePaginaHistorial=$esGlobal ? ($clientesHistorial[$paginaHistorial-1]??null) : null;
        $historial=$esGlobal
            ? ($clientePaginaHistorial!==null
                ? $repo->historialDashboardDeCliente($filtro,(int)$clientePaginaHistorial['id_cliente'],$periodo,$historialSoloSeleccion)
                : [])
            : $repo->historialDashboard($filtro,$idCliente,$periodo,$historialSoloSeleccion,$paginaHistorial,$porPaginaHistorial);

        $ultimaDescargaRvie = (!$esGlobal && !$esAnual)
            ? $repo->obtenerUltimaDescarga($idCliente,$periodo,'RVIE') : null;
        $ultimaDescargaRce = (!$esGlobal && !$esAnual)
            ? $repo->obtenerUltimaDescarga($idCliente,$periodo,'RCE') : null;

        return [
            'moduloActivo'=>'SIRE',
            'clientes'=>$clientes,
            'historial'=>$historial,
            'historialSoloSeleccion'=>$historialSoloSeleccion,
            'paginaHistorial'=>$paginaHistorial,
            'porPaginaHistorial'=>$porPaginaHistorial,
            'totalHistorial'=>$totalHistorial,
            'totalPaginasHistorial'=>$totalPaginasHistorial,
            'clientePaginaHistorial'=>$clientePaginaHistorial,
            'seleccionInvalida'=>$seleccionInvalida,
            'idCliente'=>$idCliente,
            'clienteSel'=>$clienteSel,
            'periodo'=>$periodo,
            'esAnual'=>$esAnual,
            'anio'=>$anio,
            'mes'=>$mes,
            'aniosUi'=>PeriodosSire::anios($this->conexion,$idCliente,$periodo,6),
            'mesesUi'=>PeriodosSire::meses(true),
            'periodoEtiqueta'=>$periodoEtiqueta,
            'rv'=>$rv,
            'rc'=>$rc,
            'estadosSire'=>$estadosSire,
            'comparacionMensual'=>$comparacionMensual,
            'rvGlobal'=>$rvGlobal,
            'rcGlobal'=>$rcGlobal,
            'esGlobal'=>$esGlobal,
            'ultimaDescargaRvie'=>$ultimaDescargaRvie,
            'ultimaDescargaRce'=>$ultimaDescargaRce,
            'csrfSire'=>asesconCsrf('csrf_sire'),
        ];
    }
}
