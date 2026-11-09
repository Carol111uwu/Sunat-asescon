<?php
declare(strict_types=1);

                                                                                   
final class EstadoCronBuzon
{
    public static function consultar(string $directorio): array
    {
        $archivo = rtrim($directorio, '/\\') . '/ultima_ejecucion_cron';
        $ultima = is_file($archivo) ? (int)trim((string)@file_get_contents($archivo)) : 0;
        $segundos = $ultima > 0 ? max(0, time() - $ultima) : null;
                                                                                    
        $reciente = $segundos !== null && $segundos <= 900;
        return [
            'reciente' => $reciente,
            'ultima' => $ultima,
            'mensaje' => $reciente
                ? 'Procesador activo recientemente. Las consultas pueden tardar unos minutos.'
                : 'No se detectó una ejecución reciente del procesador. Revisar la tarea Cron antes de volver a solicitar.',
        ];
    }

                                                                                      
    public static function detalleCola(string $directorio, array $estado): array
    {
        $archivos = glob(rtrim($directorio, '/\\') . '/solicitudes/pendiente_*.json') ?: [];
        usort($archivos, static fn(string $a, string $b): int => (@filemtime($a) ?: 0) <=> (@filemtime($b) ?: 0));
        $idTrabajo = (string)($estado['id_trabajo'] ?? '');
        $posicion = null;
        foreach ($archivos as $indice => $ruta) {
            if ($idTrabajo !== '' && basename($ruta) === 'pendiente_' . $idTrabajo . '.json') {
                $posicion = $indice + 1;
                break;
            }
        }
        $solicitado = strtotime((string)($estado['solicitado_en'] ?? ''));
        return [
            'posicion' => $posicion,
            'pendientes' => count($archivos),
            'espera_segundos' => $solicitado === false ? null : max(0, time() - $solicitado),
        ];
    }
}
