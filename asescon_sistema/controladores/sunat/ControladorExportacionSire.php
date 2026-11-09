<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelos/sunat/RepositorioLecturaSire.php';
require_once __DIR__ . '/../../servicios/sunat/ExportadorExcelSire.php';
require_once __DIR__ . '/../../servicios/sunat/PeriodosSire.php';
require_once __DIR__ . '/../../servicios/sunat/PresentacionDatosSire.php';

final class ControladorExportacionSire
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function generar(array $entrada): array
    {
        $libro = strtoupper(trim((string)($entrada['libro'] ?? '')));
        if (!in_array($libro, ['RVIE','RCE'], true)) throw new InvalidArgumentException('Libro SIRE inválido.');
        asesconExigirPermiso($this->conexion, $libro, 'ver');
        $modo = strtolower(trim((string)($entrada['modo'] ?? 'resumen')));
        if (!in_array($modo, ['resumen','detalle','original'], true)) throw new InvalidArgumentException('Tipo de exportación inválido.');
        $idCliente = (int)($entrada['id_cliente'] ?? 0);
        $periodo = preg_replace('/\D+/', '', (string)($entrada['periodo'] ?? '')) ?? '';
        $excluir = (string)($entrada['excluir_anteriores'] ?? '0') !== '0';
        if ($idCliente < 0 || ($idCliente > 0 && !asesconPuedeVerCliente($this->conexion, $idCliente))) throw new RuntimeException('Cliente no autorizado.');
        if (!PeriodosSire::permitido($periodo)) throw new InvalidArgumentException('Período fuera del intervalo 2021–mes actual.');

        $repo = new RepositorioLecturaSire($this->conexion);
        $global = $idCliente === 0;
        $cliente = $global ? null : $repo->obtenerCliente($idCliente);
        if (!$global && !$cliente) throw new RuntimeException('Cliente no encontrado.');
        $esAnual = PeriodosSire::esAnual($periodo);
        $anio = PeriodosSire::anio($periodo);
        $filtroClientes = $global ? asesconFiltroClientesSql($this->conexion, 'c') : '';
        $detalles = [];
        if (!$global) {
            $detalles = $esAnual
                ? $repo->obtenerDetallesAnuales($libro, $idCliente, $anio, true)
                : $repo->obtenerDetallesNormalizados($libro, $idCliente, $periodo);
            $detalles = PresentacionDatosSire::filtrarMesesAnteriores($detalles, $periodo, $excluir);
        } elseif ($modo === 'detalle') {
            $cantidad = $repo->contarDetallesGlobales($libro, $filtroClientes, $periodo, $excluir);
            if ($cantidad > 10000) throw new DomainException('El detalle consolidado supera 10 000 registros. Exporta por empresa o por período para evitar saturar el servidor.');
            for ($pagina = 1; count($detalles) < $cantidad; $pagina++) {
                $lote = $repo->obtenerDetallesGlobales($libro, $filtroClientes, $periodo, $excluir, $pagina, 100);
                if ($lote === []) break;
                foreach ($lote as $fila) {
                    $fila['documento'] = $fila[$libro === 'RCE' ? 'ruc_proveedor' : 'documento_cliente'] ?? '';
                    $fila['razon_social'] = $fila[$libro === 'RCE' ? 'razon_social_proveedor' : 'razon_social_cliente'] ?? '';
                    $detalles[] = $fila;
                }
            }
        }
        $periodoEtiqueta = PeriodosSire::etiqueta($periodo);
        $filtroTxt = $excluir ? ' · solo fechas de emisión dentro del período (sin fecha se oculta)' : ' · incluye todas las emisiones de la propuesta';
        $subtitulo = ($global ? 'Todas las empresas autorizadas' : ((string)$cliente['ruc'] . ' — ' . (string)$cliente['razon_social'])) . ' · Período SIRE: ' . $periodoEtiqueta . ' · Exportado: ' . date('d/m/Y H:i') . $filtroTxt;

        if($modo==='original'){
            if ($global) throw new DomainException('El archivo original solo se descarga por empresa y mes. Usa Excel detalle para ver un consolidado.');
            if($esAnual)throw new DomainException('Selecciona un mes para exportar el archivo original completo.');
            require_once __DIR__.'/../../servicios/sunat/InspectorArchivosSire.php';
            require_once __DIR__.'/../../servicios/sunat/LectorDetalleSireCompleto.php';
            $inspector=new InspectorArchivosSire(null,$this->conexion);
            $codigo=$libro==='RCE'?ServicioSireSunat::LIBRO_RCE:ServicioSireSunat::LIBRO_RVIE;
            $cache=$inspector->obtenerCache((string)$cliente['ruc'],$periodo,$codigo);$rutas=$cache['archivos_paths']??[];
            if(!$rutas){$ruta=$inspector->obtenerArchivoDatosMasReciente((string)$cliente['ruc'],$periodo,$codigo);if($ruta)$rutas=[$ruta];}
            if(!$rutas)throw new DomainException('El TXT/CSV original de este mes no está guardado. Vuelve a descargar la propuesta SIRE; el Excel de detalle resumido sigue disponible.');
            $filas=[];$cabeceras=null;
            foreach($rutas as $ruta){$tabla=(new LectorDetalleSireCompleto())->leer($ruta);if($cabeceras!==null&&$cabeceras!==$tabla['cabeceras'])throw new DomainException('Los originales contienen formatos distintos. Revisa los TXT por separado.');$cabeceras=$tabla['cabeceras'];$filas=array_merge($filas,$tabla['filas']);if(count($filas)>20000)throw new DomainException('El original supera 20000 registros.');}
            $bytes=(new ExportadorExcelSire())->crear($libro.' — Propuesta original completa',(string)$cliente['ruc'].' · '.$periodo.' · Sin filtro de emisiones; se conservan todas las columnas y valores',$cabeceras,$filas,array_fill(0,count($cabeceras),'text'),$libro.' Original');
            return ['nombre'=>'ASESCON_'.$libro.'_original_'.$periodo.'.xlsx','contenido'=>$bytes];
        }

        if ($modo === 'resumen') {
            $grupos = $global ? $repo->agruparDetallesGlobales($libro, $filtroClientes, $periodo, $excluir) : PresentacionDatosSire::agruparPorTipo($detalles);
            $cabeceras = ['Tipo de documento','Total documentos','Base imponible','IGV','Total'];
            $tipos = ['text','int','money','money','money'];
            $filas = array_map(static fn(array $g): array => [$g['tipo'],$g['documentos'],$g['base'],$g['igv'],$g['total']], $grupos);
            $titulo = $libro . ' ' . ($libro === 'RCE' ? 'Compras' : 'Ventas') . ' — Resumen por tipo de documento';
            $hoja = $libro . ' Resumen';
        } else {
            $cabeceras = array_merge($global ? ['Empresa ASESCON','RUC empresa'] : [], ['Fecha de emisión','Tipo','Serie','Número',$libro === 'RCE' ? 'RUC proveedor' : 'Documento cliente',$libro === 'RCE' ? 'Proveedor' : 'Cliente','Base imponible','IGV','Total','Estado']);
            $tipos = array_merge($global ? ['text','text'] : [], ['date','text','text','text','text','text','money','money','money','text']);
            $filas = array_map(static fn(array $d): array => array_merge($global ? [(string)($d['empresa_asescon'] ?? ''),(string)($d['ruc_empresa'] ?? '')] : [], [
                (string)($d['fecha_emision'] ?? ''),(string)($d['tipo_comprobante'] ?? ''),(string)($d['serie'] ?? ''),(string)($d['numero'] ?? ''),(string)($d['documento'] ?? ''),(string)($d['razon_social'] ?? ''),(float)($d['base_imponible'] ?? 0),(float)($d['igv'] ?? 0),(float)($d['importe_total'] ?? 0),(string)($d['estado_registro'] ?? '')
            ]), $detalles);
            $titulo = $libro . ' ' . ($libro === 'RCE' ? 'Compras' : 'Ventas') . ' — Detalle de comprobantes';
            $hoja = $libro . ' Detalle';
        }
        $bytes = (new ExportadorExcelSire())->crear($titulo, $subtitulo, $cabeceras, $filas, $tipos, $hoja);
        if (strlen($bytes) < 4 || substr($bytes,0,2) !== 'PK') throw new RuntimeException('El generador produjo un archivo XLSX inválido.');
        $periodoArchivo = $esAnual ? $anio . '_ANUAL' : $periodo;
        return ['nombre'=>'ASESCON_'.$libro.'_'.$modo.'_'.($global ? 'TODAS_' : '').$periodoArchivo.'.xlsx','contenido'=>$bytes];
    }
}
