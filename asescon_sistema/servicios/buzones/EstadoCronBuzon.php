<?php
declare(strict_types=1);

                                                                                   
require_once dirname(__DIR__, 2).'/configuracion/rutas_privadas.php';

final class EstadoCronBuzon
{
    public static function consultar(string $directorio): array
    {
        $archivo = rtrim($directorio, '/\\') . '/ultima_ejecucion_cron';
        $ultima = is_file($archivo) ? (int)trim((string)@file_get_contents($archivo)) : 0;
        $segundos = $ultima > 0 ? max(0, time() - $ultima) : null;
                                                                                    
        $reciente = $segundos !== null && $segundos <= 900;
        $rutaRondas = asesconRutaAlmacenamiento().'/sincronizacion/rondas_buzones.json';
        $rondas = is_file($rutaRondas) ? json_decode((string)@file_get_contents($rutaRondas), true) : [];
        return [
            'ultima_planificacion' => (int)($rondas['ultima_planificacion'] ?? 0),
            'reciente' => $reciente,
            'ultima' => $ultima,
            'mensaje' => $reciente
                ? 'Procesador activo recientemente. Las consultas pueden tardar unos minutos.'
                : 'No se detectó una ejecución reciente del procesador. Revisar la tarea Cron antes de volver a solicitar.',
        ];
    }

                                                                                      
    public static function pendientesOrdenados(string $directorio, bool $sol = false): array
    {
        $archivos = glob(rtrim($directorio, '/').'/solicitudes/pendiente_*.json') ?: [];
        $orden = [];
        foreach ($archivos as $archivo) {
            $marca = @filemtime($archivo) ?: 0;
            if (!$sol) {
                $orden[$archivo] = [$marca, $archivo];
                continue;
            }
            $solicitud = json_decode((string)@file_get_contents($archivo), true);
            $orden[$archivo] = [
                !empty($solicitud['solo_adjuntos']),
                (int)($solicitud['id_usuario'] ?? 0) === 0,
                strtotime((string)($solicitud['solicitado_en'] ?? '')) ?: 0,
                $archivo,
            ];
        }
        usort($archivos, static fn(string $a, string $b): int => $orden[$a] <=> $orden[$b]);
        return $archivos;
    }

    public static function detalleCola(string $directorio, array $estado, bool $sol = false): array
    {
        $archivos = self::pendientesOrdenados($directorio, $sol);
        $idTrabajo = (string)($estado['id_trabajo'] ?? '');
        $posicion = null;
        foreach ($archivos as $indice => $ruta) {
            if ($idTrabajo !== '' && basename($ruta) === 'pendiente_' . $idTrabajo . '.json') {
                $posicion = $indice + 1;
                break;
            }
        }
        $solicitado = strtotime((string)($estado['solicitado_en'] ?? ''));
        $iniciado = strtotime((string)($estado['iniciado_en'] ?? ''));
        $esperando = ($estado['estado'] ?? '') === 'PENDIENTE';
        return [
            'posicion' => $posicion,
            'pendientes' => count($archivos),
            'espera_segundos' => $solicitado === false || (!$esperando && $iniciado === false) ? null : max(0, ($iniciado ?: time()) - $solicitado),
        ];
    }
}
