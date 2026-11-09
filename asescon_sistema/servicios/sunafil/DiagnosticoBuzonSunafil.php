<?php

declare(strict_types=1);
require_once dirname(__DIR__,2).'/configuracion/rutas_privadas.php';

final class DiagnosticoBuzonSunafil
{
    public function comprobar(): array
    {
        $raizProyecto = dirname(__DIR__, 2);
        $recursosPrivados = (string)(getenv('ASESCON_RECURSOS_PRIVADOS')?:dirname($raizProyecto).'/asescon_recursos_privados');
        $python = $recursosPrivados . DIRECTORY_SEPARATOR . 'entorno_buzon_sol' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python';
        $cache = $recursosPrivados . DIRECTORY_SEPARATOR . 'navegadores_playwright';

        $scriptCron = $raizProyecto . DIRECTORY_SEPARATOR . 'procesos' . DIRECTORY_SEPARATOR . 'buzon_sunafil' . DIRECTORY_SEPARATOR . 'procesar_cola.sh';
        $scriptPython = $raizProyecto . DIRECTORY_SEPARATOR . 'python' . DIRECTORY_SEPARATOR . 'sunafil' . DIRECTORY_SEPARATOR . 'consultar_casilla_sunafil.py';
        $almacenamiento = asesconRutaAlmacenamiento() . DIRECTORY_SEPARATOR . 'sunafil' . DIRECTORY_SEPARATOR . 'buzon_sunafil';

        $chromium = false;
        if (is_dir($cache)) {
            $coincidencias = array_merge(
                glob($cache . DIRECTORY_SEPARATOR . 'chromium-*') ?: [],
                glob($cache . DIRECTORY_SEPARATOR . 'chromium_headless_shell-*') ?: []
            );
            $chromium = $coincidencias !== [];
        }

        $marcaCron = $almacenamiento . DIRECTORY_SEPARATOR . 'ultima_ejecucion_cron';
        $ultimoCron = is_file($marcaCron) ? (int)trim((string)@file_get_contents($marcaCron)) : 0;
        $estado = [
            'modo' => 'CRON',
            'python_preparado' => is_file($python) && is_executable($python),
            'chromium_preparado' => $chromium,
            'worker_preparado' => is_file($scriptCron) && is_file($scriptPython),
            'cron_reciente' => $ultimoCron > 0 && $ultimoCron >= time() - 180,
            'almacenamiento_escribible' => is_dir($almacenamiento)
                ? is_writable($almacenamiento)
                : is_writable(dirname($almacenamiento)),
        ];
        $informe=$recursosPrivados.'/diagnostico_runtime.json';$prueba=is_file($informe)?json_decode((string)file_get_contents($informe),true):[];
        $estado['navegador_ejecutado']=is_array($prueba)&&!empty($prueba['instalacion_ok'])&&!empty($prueba['estricto'])&&is_file($recursosPrivados.'/RUNTIME_OK');
        $estado['ok'] = !in_array(false, $estado, true);
        $estado['codigo'] = $estado['ok'] ? 'BUZON_SUNAFIL_CRON_LISTO' : 'BUZON_SUNAFIL_CRON_INCOMPLETO';
        $estado['mensaje'] = $estado['ok']
            ? 'El entorno de Buzón SUNAFIL y la actividad reciente de Cron se detectaron. La conexión con SUNAFIL solo se valida con una prueba autorizada.'
            : 'Falta completar el entorno de ejecución por Cron del Buzón SUNAFIL.';

        return $estado;
    }
}
