<?php

declare(strict_types=1);

require_once __DIR__ . '/ExcepcionSunat.php';
require_once __DIR__.'/ContratoHttpSunat.php';
require_once __DIR__ . '/ConfiguracionSunat.php';

final class ClienteHttpSunat implements ContratoHttpSunat
{
    private const MAX_RETRIES_429 = 3;

    public function postForm(string $url, array $form, array $headers = []): array
    {
        return $this->requestJson('POST', $url, array_merge($headers, ['Content-Type: application/x-www-form-urlencoded']), http_build_query($form, '', '&', PHP_QUERY_RFC3986));
    }
    public function getJson(string $url, array $headers = []): array
    {
        return $this->requestJson('GET', $url, $headers);
    }

    public function postJson(string $url, ?array $payload = null, array $headers = []): array
    {
        $headers[] = 'Content-Type: application/json';
        $body = $payload === null
            ? null
            : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return $this->requestJson('POST', $url, $headers, $body);
    }

    public function getBinary(string $url, array $headers = []): array
    {
        if (!extension_loaded('curl')) {
            throw new ExcepcionSunat('La extensión cURL de PHP es necesaria para comunicarse con SUNAT.');
        }
        for ($intento = 0; $intento <= self::MAX_RETRIES_429; $intento++) {
            $ch = curl_init();
            if ($ch === false) {
                throw new ExcepcionSunat('No se pudo inicializar el cliente HTTP para SUNAT.');
            }
$httpHeaders = array_values($headers);
            $httpHeaders[] = 'Accept: application/zip,application/octet-stream,text/plain,*/*';
            $respHeaders = [];
            curl_setopt_array($ch, [CURLOPT_URL => $url,CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,CURLOPT_CUSTOMREQUEST => 'GET',CURLOPT_RETURNTRANSFER => true,CURLOPT_FOLLOWLOCATION => false,CURLOPT_CONNECTTIMEOUT => ConfiguracionSunat::CONNECT_TIMEOUT_SECONDS,CURLOPT_TIMEOUT => max(60, ConfiguracionSunat::REQUEST_TIMEOUT_SECONDS),CURLOPT_HTTPHEADER => $httpHeaders,CURLOPT_SSL_VERIFYPEER => true,CURLOPT_SSL_VERIFYHOST => 2,CURLOPT_USERAGENT => 'ASESCON-SUNAT/1.1',CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$respHeaders): int {
                $len = strlen($line);
                $pos = strpos($line, ':');
                if ($pos !== false) {
                    $n = strtolower(trim(substr($line, 0, $pos)));
                    $v = trim(substr($line, $pos + 1));
                    if ($n !== '') {
                        $respHeaders[$n] = $v;
                    }
                }
return $len;
            }]);
            $body = curl_exec($ch);
            $err = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            if ($body === false) {
                throw new ExcepcionSunat($err !== '' ? 'No se pudo descargar el archivo de SUNAT: ' . $err : 'No se pudo descargar el archivo de SUNAT.');
            }
            if ($status === 429 && $intento < self::MAX_RETRIES_429) {
                $this->esperarRateLimit($intento, $respHeaders['retry-after'] ?? '');
                continue;
            }
            if ($status < 200 || $status >= 300) {
                $d = json_decode((string)$body, true);
                $json = is_array($d) ? $d : [];
                throw new ExcepcionSunat($this->extraerMensajeError($json, $status), $status, $this->extraerCodigoError($json));
            }
            if (str_contains(strtolower($ct), 'json')) {
                $d = json_decode((string)$body, true);
                if (is_array($d) && ($d['cod'] ?? $d['error'] ?? null) !== null) {
                    throw new ExcepcionSunat($this->extraerMensajeError($d, $status), $status, $this->extraerCodigoError($d));
                }
            }
            $filename = '';
            $disp = (string)($respHeaders['content-disposition'] ?? '');
            if ($disp !== '' && preg_match('/filename\*?=(?:UTF-8\'\')?["\']?([^"\';]+)["\']?/i', $disp, $m)) {
                $filename = rawurldecode(trim($m[1]));
            }
return ['body' => (string)$body,'http_status' => $status,'content_type' => $ct,'filename' => $filename];
        }
        throw new ExcepcionSunat('SUNAT limitó temporalmente las consultas (HTTP 429). Espera unos segundos y vuelve a intentar.', 429);
    }

    private function requestJson(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        if (!extension_loaded('curl')) {
            throw new ExcepcionSunat('La extensión cURL de PHP es necesaria para comunicarse con SUNAT.');
        }
        for ($intento = 0; $intento <= self::MAX_RETRIES_429; $intento++) {
            $ch = curl_init();
            if ($ch === false) {
                throw new ExcepcionSunat('No se pudo inicializar el cliente HTTP para SUNAT.');
            }
$httpHeaders = array_values($headers);
            $httpHeaders[] = 'Accept: application/json';
            $respHeaders = [];
            curl_setopt_array($ch, [CURLOPT_URL => $url,CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,CURLOPT_CUSTOMREQUEST => strtoupper($method),CURLOPT_RETURNTRANSFER => true,CURLOPT_FOLLOWLOCATION => false,CURLOPT_CONNECTTIMEOUT => ConfiguracionSunat::CONNECT_TIMEOUT_SECONDS,CURLOPT_TIMEOUT => ConfiguracionSunat::REQUEST_TIMEOUT_SECONDS,CURLOPT_HTTPHEADER => $httpHeaders,CURLOPT_SSL_VERIFYPEER => true,CURLOPT_SSL_VERIFYHOST => 2,CURLOPT_USERAGENT => 'ASESCON-SUNAT/1.1',CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$respHeaders): int {
                $len = strlen($line);
                $pos = strpos($line, ':');
                if ($pos !== false) {
                    $n = strtolower(trim(substr($line, 0, $pos)));
                    $v = trim(substr($line, $pos + 1));
                    if ($n !== '') {
                        $respHeaders[$n] = $v;
                    }
                }
return $len;
            }]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $response = curl_exec($ch);
            $err = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($response === false) {
                throw new ExcepcionSunat($err !== '' ? 'No se pudo conectar con SUNAT: ' . $err : 'No se pudo conectar con SUNAT.');
            }
            if ($status === 429 && $intento < self::MAX_RETRIES_429) {
                $this->esperarRateLimit($intento, $respHeaders['retry-after'] ?? '');
                continue;
            }
            $texto = trim((string)$response);
            $decoded = json_decode((string)$response, true);
            if (is_array($decoded)) {
                $json = $decoded;
            } elseif (json_last_error() === JSON_ERROR_NONE && is_scalar($decoded)) {
                $json = ['_value' => $decoded];
            } elseif ($status >= 200 && $status < 300 && preg_match('/^[A-Za-z0-9._-]+$/', $texto)) {
                $json = ['_value' => $texto];
            } else {
                $json = [];
            }
            if ($status < 200 || $status >= 300) {
                throw new ExcepcionSunat($this->extraerMensajeError($json, $status), $status, $this->extraerCodigoError($json));
            }
if ($json === [] && $texto !== '' && $texto !== '{}') {
                throw new ExcepcionSunat('SUNAT respondió con un formato inesperado.', $status);
            }
return $json;
        }
        throw new ExcepcionSunat('SUNAT limitó temporalmente las consultas (HTTP 429). Espera unos segundos y vuelve a intentar.', 429);
    }

    private function esperarRateLimit(int $intento, string $retryAfter): void
    {
        $seg = is_numeric($retryAfter) ? max(1, min(10, (int)$retryAfter)) : min(8, 2 ** ($intento + 1));
        usleep($seg * 1000000);
    }
    private function extraerMensajeError(array $json, int $httpStatus): string
    {
        if (isset($json['errors']) && is_array($json['errors'])) {
            $mensajes = [];
            foreach ($json['errors'] as $error) {
                if (!is_array($error)) {
                    continue;
                }
                $mensaje = trim((string)($error['msg'] ?? $error['message'] ?? $error['mensaje'] ?? ''));
                if ($mensaje !== '') {
                    $mensajes[] = $mensaje;
                }
            }
            if ($mensajes !== []) {
                return 'SUNAT rechazó la solicitud: ' . implode(' · ', array_slice(array_unique($mensajes), 0, 3));
            }
        }

        foreach (['error_description', 'message', 'mensaje', 'errorMessage', 'msg'] as $clave) {
            if (isset($json[$clave]) && is_string($json[$clave]) && trim($json[$clave]) !== '') {
                return 'SUNAT rechazó la solicitud: ' . trim($json[$clave]);
            }
        }

        if (isset($json['error']) && is_string($json['error']) && trim($json['error']) !== '') {
            return 'SUNAT rechazó la solicitud: ' . trim($json['error']);
        }
        if ($httpStatus === 429) {
            return 'SUNAT limitó temporalmente la cantidad de consultas (HTTP 429). ASESCON reintentó automáticamente; espera unos segundos antes de volver a sincronizar.';
        }
        return 'SUNAT respondió con HTTP ' . $httpStatus . '.';
    }

    private function extraerCodigoError(array $json): ?string
    {
        if (isset($json['errors']) && is_array($json['errors'])) {
            foreach ($json['errors'] as $error) {
                if (is_array($error) && isset($error['cod']) && (is_string($error['cod']) || is_int($error['cod']))) {
                    return (string)$error['cod'];
                }
            }
        }

        foreach (['error', 'cod', 'codigo', 'code'] as $clave) {
            if (isset($json[$clave]) && (is_string($json[$clave]) || is_int($json[$clave]))) {
                return (string)$json[$clave];
            }
        }
        return null;
    }

}
