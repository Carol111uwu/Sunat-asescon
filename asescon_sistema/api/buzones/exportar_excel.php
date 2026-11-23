<?php
declare(strict_types=1);

require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/buzones/BuzonSol.php';
require_once __DIR__.'/../../modelos/buzones/BuzonSunafil.php';
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
require_once __DIR__.'/../../servicios/sunat/ExportadorExcelSire.php';

asesconExigirLogin();
$buzon = $_GET['buzon'] ?? '';
$cliente = (int)($_GET['id_cliente'] ?? 0);
if (!in_array($buzon, ['SOL', 'SUNAFIL'], true) || $cliente < 1) {
    http_response_code(400);
    exit('Selecciona un buzón y una empresa.');
}
asesconExigirPermiso($conexion, 'BUZON_'.$buzon, 'ver');
$filtro = asesconFiltroClientesSql($conexion, 'c');
$estado = (string)($_GET['estado'] ?? '');
$tipo = (string)($_GET['tipo'] ?? '');
$desde = (string)($_GET['desde'] ?? '');
$hasta = (string)($_GET['hasta'] ?? '');
if ($buzon === 'SOL') {
    $filtro = AccesoCasosSol::filtro($conexion, $filtro, 'b', false);
    $documentos = (new BuzonSol($conexion))->listar('('.$filtro.')', $estado, $tipo, $cliente, $desde, $hasta, 50, 0, true);
} else {
    $categoria = (string)($_GET['categoria'] ?? '');
    if (in_array($categoria, ['FISCALIZACION','COBRANZA','ACCIONES_PREVIAS','FORMALIZACION','SEGURIDAD_SALUD','ORIENTACION'], true)) {
        $filtro = "({$filtro}) AND b.categoria='".$conexion->real_escape_string($categoria)."'";
    }
    $documentos = (new BuzonSunafil($conexion))->listar($filtro, $estado, $cliente, $tipo, $desde, $hasta);
}
$filas = [];
foreach ($documentos as $doc) {
    $filas[] = [$doc['ruc'], $doc['razon_social'], $doc['fecha_recepcion'] ?: $doc['fecha_documento'],
        $doc['categoria'], $doc['tipo_documento'], $doc['asunto'], $doc['estado'], $doc['prioridad']];
}
$excel = (new ExportadorExcelSire())->crear('Buzón '.$buzon, 'Notificaciones guardadas',
    ['RUC', 'Empresa', 'Fecha', 'Categoría', 'Tipo', 'Asunto', 'Estado', 'Prioridad'], $filas, [], $buzon);
header('Cache-Control: private, no-store');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="buzon_'.$buzon.'_'.$cliente.'.xlsx"');
echo $excel;
