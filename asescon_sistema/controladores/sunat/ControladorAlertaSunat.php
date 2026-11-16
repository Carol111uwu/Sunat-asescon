<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/AlertaSunat.php';
require_once __DIR__ . '/../../servicios/casos_sunat/AccesoCasosSol.php';
final class ControladorAlertaSunat
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(string $estado = ''): array
    {
        asesconExigirPermiso($this->conexion, 'ALERTAS_SUNAT', 'ver');
        $modelo = new AlertaSunat($this->conexion);
        $filtro = asesconFiltroClientesSql($this->conexion, 'c');
        $filas = $modelo->listar($filtro, $estado);
        $fuentes = [
            'SOL' => ['BUZON_SOL', 'buzon_sol_sunat.php', 'Buzón SOL'],
            'SUNAFIL' => ['BUZON_SUNAFIL', 'buzon_sunafil.php', 'Buzón SUNAFIL'],
        ];
        $fuentesConError = [];
        foreach ($fuentes as $buzon => [$permiso, $ruta, $etiqueta]) {
            if (!asesconTienePermiso($this->conexion, $permiso, 'ver')) {
                continue;
            }
            try {
                $alcance = $buzon === 'SOL' ? AccesoCasosSol::filtro($this->conexion, $filtro, instalar: false) : $filtro;
                foreach ($modelo->listarBuzon($buzon, $alcance, $estado) as $fila) {
                    $fila['tipo_alerta'] = $etiqueta;
                    $fila['mensaje'] = '';
                    $fila['url_buzon'] = asesconUrl('vistas/buzones/' . $ruta) . '?id_cliente=' . (int) $fila['id_cliente'];
                    $filas[] = $fila;
                }
            } catch (Throwable $error) {
                error_log('Alertas ASESCON ' . $buzon . ': ' . $error->getMessage());
                $fuentesConError[] = $etiqueta;
            }
        }
        usort($filas, static fn(array $a, array $b): int => [
            $a['estado'] === 'ATENDIDA', $b['fecha_alerta'],
        ] <=> [
            $b['estado'] === 'ATENDIDA', $a['fecha_alerta'],
        ]);
        return ['filas' => $filas, 'fuentesConError' => $fuentesConError];
    }

    public function procesar(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_alertas')) {
            asesconRedirigir('vistas/alertas/alertas_sunat.php', ['error' => 'csrf']);
        }
asesconExigirPermiso($this->conexion, 'ALERTAS_SUNAT', 'editar');
        (new AlertaSunat($this->conexion))->atender((int)($p['id_alerta'] ?? 0), asesconFiltroClientesSql($this->conexion, 'c'));
        asesconRedirigir('vistas/alertas/alertas_sunat.php', ['guardado' => 1]);
    }
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    (new ControladorAlertaSunat($conexion))->procesar($_POST);
}
