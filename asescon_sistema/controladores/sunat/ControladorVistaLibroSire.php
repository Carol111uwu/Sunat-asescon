<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelos/sunat/RepositorioLecturaSire.php';
require_once __DIR__ . '/../../servicios/sunat/PeriodosSire.php';
require_once __DIR__ . '/../../servicios/sunat/PresentacionDatosSire.php';

final class ControladorVistaLibroSire
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function preparar(string $libro, array $entrada): array
    {
        $libro = strtoupper(trim($libro));
        if (!in_array($libro, ['RVIE','RCE'], true)) {
            throw new InvalidArgumentException('Libro SIRE inválido.');
        }
        asesconExigirPermiso($this->conexion, $libro, 'ver');
        $repo = new RepositorioLecturaSire($this->conexion);
        $filtro = asesconFiltroClientesSql($this->conexion, 'c');
                                                                                               
        $filas = [];
        $clientes = $repo->listarClientesVisibles($filtro, true);
        $idCliente = (int)($entrada['cliente'] ?? 0);
        if ($idCliente > 0 && !asesconPuedeVerCliente($this->conexion, $idCliente)) {
            $idCliente = 0;
        }
        $periodoBase = preg_replace('/\D+/', '', (string)($entrada['periodo'] ?? date('Ym'))) ?: date('Ym');
        if (isset($entrada['anio']) || isset($entrada['mes'])) {
            $periodo = PeriodosSire::construir((string)($entrada['anio'] ?? substr($periodoBase,0,4)), (string)($entrada['mes'] ?? substr($periodoBase,4,2)));
        } else {
            $periodo = $periodoBase;
        }
        if (!PeriodosSire::permitido($periodo)) {
            $periodo = PeriodosSire::ahoraPeru()->format('Ym');
        }
        $esAnual = PeriodosSire::esAnual($periodo);
        $anio = PeriodosSire::anio($periodo);
        $mes = substr($periodo,4,2);
        $hoyPeru = PeriodosSire::ahoraPeru();
        $esPeriodoActual = $esAnual
            ? $anio === $hoyPeru->format('Y')
            : $periodo === $hoyPeru->format('Ym');
        $periodoBreve = $esAnual ? $anio : explode(' · ', PeriodosSire::etiqueta($periodo))[0];
        $periodoContexto = ($esAnual
            ? ($esPeriodoActual ? 'Año actual' : 'Año seleccionado')
            : ($esPeriodoActual ? 'Mes actual' : 'Mes seleccionado')) . ': ' . $periodoBreve;
        $mesesEsperados = $esAnual ? ($anio === $hoyPeru->format('Y') ? (int)$hoyPeru->format('n') : 12) : 1;
        $excluirAnteriores = (string)($entrada['excluir_anteriores'] ?? '0') !== '0';
        $actual = $libro === 'RCE'
            ? ['id_rce'=>0,'cantidad_registros'=>0,'base_imponible'=>0,'igv'=>0,'total'=>0,'fecha_actualizacion'=>null,'estado'=>'SIN_DATOS']
            : ['id_rvie'=>0,'cantidad_registros'=>0,'base_imponible'=>0,'igv'=>0,'total'=>0,'fecha_actualizacion'=>null,'estado'=>'SIN_DATOS'];
        $detalles = [];
        $resumenClientes = [];
        $pagina = max(1, min(100000, (int)($entrada['pagina'] ?? 1)));
        $porPagina = 50;
        $tipoFiltrado = substr(trim((string)($entrada['tipo'] ?? '')), 0, 40);
        $totalDetallesGlobales = 0;
        $tiposGlobales = [];
        $clientesPaginados = [];
        $clientePagina = null;
        if ($idCliente > 0) {
            $actual = $esAnual
                ? $repo->obtenerCabeceraAnual($libro, $idCliente, $anio)
                : $repo->obtenerCabeceraPeriodo($libro, $idCliente, $periodo);
            $detalles = $esAnual
                ? $repo->obtenerDetallesAnuales($libro, $idCliente, $anio)
                : $repo->obtenerDetalles($libro, $idCliente, $periodo);
        }
        else {
            $resumenClientes = $repo->obtenerResumenGlobal($libro, $filtro, $periodo);
            foreach ($resumenClientes as $fila) {
                foreach (['cantidad_registros','base_imponible','igv','total'] as $campo) {
                    $actual[$campo] += (float)$fila[$campo];
                }
                $marca = (string)($fila['fecha_actualizacion'] ?? '');
                if ($marca !== '' && ($actual['fecha_actualizacion'] === null || $marca > $actual['fecha_actualizacion'])) {
                    $actual['fecha_actualizacion'] = $marca;
                }
            }
            $actual['estado'] = $resumenClientes ? 'GUARDADO' : 'SIN_DATOS';
            $tiposGlobales = $repo->agruparDetallesGlobales($libro, $filtro, $periodo, $excluirAnteriores);
            $totalDetallesGlobales = $repo->contarDetallesGlobales($libro, $filtro, $periodo, $excluirAnteriores, $tipoFiltrado);
            $clientesPaginados = $repo->clientesConDetallesGlobales($libro, $filtro, $periodo, $excluirAnteriores, $tipoFiltrado);
            $pagina = min($pagina, max(1, count($clientesPaginados)));
            $clientePagina = $clientesPaginados[$pagina - 1] ?? null;
            if ($clientePagina !== null) {
                $detalles = $repo->detallesGlobalesDeCliente($libro, $filtro, $periodo, $excluirAnteriores,
                    (int)$clientePagina['id_cliente'], $tipoFiltrado);
            }
        }
        $campoRazon = $libro === 'RCE' ? 'razon_social_proveedor' : 'razon_social_cliente';
        $faltanDatosClave = false;
        foreach ($detalles as $detalle) {
            if (empty($detalle['fecha_emision']) || trim((string)($detalle[$campoRazon] ?? '')) === '') {
                $faltanDatosClave = true;
                break;
            }
        }
        $detallesVisibles = PresentacionDatosSire::filtrarMesesAnteriores($detalles, $periodo, $excluirAnteriores);
        $metricasGlobales = $actual;
        if ($idCliente === 0 && $excluirAnteriores) {
            $metricasGlobales = ['cantidad_registros'=>0,'base_imponible'=>0.0,'igv'=>0.0,'total'=>0.0];
            foreach ($tiposGlobales as $grupo) {
                $metricasGlobales['cantidad_registros'] += (int)$grupo['documentos'];
                $metricasGlobales['base_imponible'] += (float)$grupo['base'];
                $metricasGlobales['igv'] += (float)$grupo['igv'];
                $metricasGlobales['total'] += (float)$grupo['total'];
            }
        }
        $totalSinFiltro = $idCliente > 0 ? count($detalles) : (int)$actual['cantidad_registros'];
        $totalConFiltro = $idCliente > 0 ? count($detallesVisibles) : ($excluirAnteriores ? (int)$metricasGlobales['cantidad_registros'] : $totalSinFiltro);
        return [
            'moduloActivo' => $libro,
            'filas' => $filas,
            'clientes' => $clientes,
            'idCliente' => $idCliente,
            'periodo' => $periodo,
            'esAnual' => $esAnual,
            'anio' => $anio,
            'mes' => $mes,
            'excluirAnteriores' => $excluirAnteriores,
            'aniosUi' => PeriodosSire::anios($this->conexion, $idCliente, $periodo, 6),
            'mesesUi' => PeriodosSire::meses(true),
            'actual' => $actual,
            'resumenClientes' => $resumenClientes,
            'esGlobal' => $idCliente === 0,
            'detalles' => $detalles,
            'faltanDatosClave' => $faltanDatosClave,
            'detallesVisibles' => $detallesVisibles,
            'comprobantesExcluidos' => $excluirAnteriores ? max(0, $totalSinFiltro - $totalConFiltro) : 0,
            'comprobantesVisibles' => $totalConFiltro,
            'tipos' => $idCliente === 0 ? $tiposGlobales : PresentacionDatosSire::agruparPorTipo($detallesVisibles),
            'metricas' => $idCliente === 0 ? $metricasGlobales : PresentacionDatosSire::totales($detallesVisibles),
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'tipoFiltrado' => $tipoFiltrado,
            'totalDetallesGlobales' => $totalDetallesGlobales,
            'clientesPaginados' => $clientesPaginados,
            'clientePagina' => $clientePagina,
            'periodoEtiqueta' => PeriodosSire::etiqueta($periodo),
            'periodoContexto' => $periodoContexto,
            'mesesEsperados' => $mesesEsperados,
            'excelBase' => '../../api/sunat/exportar_sire_excel.php?libro=' . $libro . '&id_cliente=' . $idCliente . '&periodo=' . rawurlencode($periodo) . '&excluir_anteriores=' . ($excluirAnteriores ? '1' : '0'),
            'puedeEjecutarSire' => asesconTienePermiso($this->conexion, 'SIRE', 'ejecutar') && asesconTienePermiso($this->conexion, $libro, 'ejecutar'),
            'csrfSire' => asesconCsrf('csrf_sire'),
        ];
    }
}
