<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/respuesta_api.php';
asesconPrepararRespuestaApi();
require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../servicios/sunat/ConfiguracionSunat.php';
require_once __DIR__ . '/../../servicios/sunat/DiagnosticoBuzonSol.php';
require_once __DIR__ . '/../../servicios/sunat/ServicioPadronSunat.php';

try {
    asesconExigirLogin();
    if (!asesconEsPrincipal($conexion)) {
        http_response_code(403);
        throw new RuntimeException('Solo la cuenta principal puede revisar la conectividad del servidor.');
    }

    $storageSunat = asesconRutaAlmacenamiento() . '/sunat';
    $resultado = [
        'version_php' => PHP_VERSION,
        'php_compatible' => PHP_VERSION_ID >= 80100,
        'curl_disponible' => extension_loaded('curl'),
        'openssl_disponible' => extension_loaded('openssl'),
        'zip_disponible' => class_exists('ZipArchive'),
        'mbstring_disponible' => extension_loaded('mbstring'),
        'exec_disponible' => function_exists('exec'),
        'proc_open_disponible' => function_exists('proc_open'),
        'mysql_conectado' => $conexion->query('SELECT 1') !== false,
        'storage_sunat_escribible' => is_dir($storageSunat) ? is_writable($storageSunat) : is_writable(dirname($storageSunat)),
        'padron_ruc' => (new ServicioPadronSunat())->obtenerEstado(),
        'buzon_sol' => (new DiagnosticoBuzonSol())->comprobar(),
        'destinos' => [],
    ];

    if ($resultado['curl_disponible']) {
        $destinos = [
            'consulta_ruc' => ConfiguracionSunat::RUC_FAST_PROVIDER_BASE_URL,
            'autenticacion_sunat' => 'https://api-seguridad.sunat.gob.pe/',
            'sire_sunat' => ConfiguracionSunat::SIRE_BASE_URL,
        ];

        foreach ($destinos as $nombre => $url) {
            $curl = curl_init($url);
            if ($curl === false) {
                $resultado['destinos'][$nombre] = ['conexion_https' => false, 'detalle' => 'No se pudo iniciar cURL.'];
                continue;
            }

            curl_setopt_array($curl, [
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'ASESCON-DIAGNOSTICO/1.0',
            ]);

            $respuesta = curl_exec($curl);
            $http = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $contentType = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
            $ssl = (int)curl_getinfo($curl, CURLINFO_SSL_VERIFYRESULT);
            $errorNumero = (int)curl_errno($curl);
            $errorTexto = trim((string)curl_error($curl));
            curl_close($curl);

            $resultado['destinos'][$nombre] = [
                'conexion_https' => $respuesta !== false && $http > 0,
                'http_destino' => $http,
                'content_type' => $contentType,
                'ssl_verificacion' => $ssl,
                'error_curl' => $errorNumero,
                'detalle_curl' => $errorTexto,
            ];
        }
    }

    echo json_encode(['ok' => true, 'data' => $resultado], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    error_log('ASESCON diagnóstico conectividad: ' . get_class($e));
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
