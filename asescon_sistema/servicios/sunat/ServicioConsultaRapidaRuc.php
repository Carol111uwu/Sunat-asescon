<?php

declare(strict_types=1);

require_once __DIR__ . '/ConfiguracionSunat.php';
require_once __DIR__ . '/ExcepcionSunat.php';

final class ServicioConsultaRapidaRuc
{
    public function consultarPorRuc(string $ruc): array
    {
        $ruc = trim($ruc);
        if (!preg_match('/^[0-9]{11}$/', $ruc)) {
            throw new ExcepcionSunat('El RUC no tiene un formato válido para consultar sus datos públicos.');
        }

        if (!ConfiguracionSunat::RUC_FAST_PROVIDER_ENABLED) {
            throw new ExcepcionSunat('El proveedor rápido de datos RUC está desactivado.');
        }

        if (!extension_loaded('curl')) {
            throw new ExcepcionSunat('La extensión cURL de PHP es necesaria para consultar los datos públicos del RUC.');
        }

        $url = rtrim(ConfiguracionSunat::RUC_FAST_PROVIDER_BASE_URL, '/') . '/api/ruc/' . rawurlencode($ruc);
        $ch = curl_init();
        if ($ch === false) {
            throw new ExcepcionSunat('No se pudo iniciar la consulta rápida del RUC.');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => ConfiguracionSunat::RUC_FAST_CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => ConfiguracionSunat::RUC_FAST_TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Cache-Control: no-cache',
            ],
            CURLOPT_USERAGENT => 'ASESCON/1.2 RUC-Lookup',
        ]);

        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new ExcepcionSunat(
                $curlError !== ''
                    ? 'La consulta rápida de RUC no respondió: ' . $curlError
                    : 'La consulta rápida de RUC no respondió.'
            );
        }

        if ($status === 404) {
            throw new ExcepcionSunat('No se encontró el RUC en la fuente rápida de datos públicos.', 404);
        }

        if ($status < 200 || $status >= 300) {
            throw new ExcepcionSunat('La consulta rápida de RUC respondió con HTTP ' . $status . '.', $status);
        }

        $json = json_decode((string) $body, true);
        if (!is_array($json)) {
            throw new ExcepcionSunat('La consulta rápida de RUC respondió con un formato inválido.');
        }

        $rucRespuesta = trim((string) ($json['ruc'] ?? ''));
        $razonSocial = trim((string) ($json['razon_social'] ?? $json['razonSocial'] ?? ''));

        if ($rucRespuesta !== '' && $rucRespuesta !== $ruc) {
            throw new ExcepcionSunat('La fuente rápida devolvió datos de un RUC diferente al solicitado.');
        }
        if ($razonSocial === '') {
            throw new ExcepcionSunat('La fuente rápida no devolvió la razón social del RUC.');
        }

        return [
            'ruc' => $ruc,
            'razon_social' => $razonSocial,
            'nombre_comercial' => trim((string) ($json['nombre_comercial'] ?? $json['nombreComercial'] ?? '')),
            'estado_ruc' => strtoupper(trim((string) ($json['estado'] ?? $json['estado_ruc'] ?? ''))),
            'condicion_ruc' => strtoupper(trim((string) ($json['condicion'] ?? $json['condicion_ruc'] ?? ''))),
            'ubigeo' => trim((string) ($json['ubigeo'] ?? '')),
            'domicilio_fiscal' => trim((string) ($json['direccion'] ?? $json['domicilio_fiscal'] ?? '')),
            'departamento' => trim((string) ($json['departamento'] ?? '')),
            'provincia' => trim((string) ($json['provincia'] ?? '')),
            'distrito' => trim((string) ($json['distrito'] ?? '')),
            'actividad_economica' => trim((string) ($json['actividad_economica'] ?? $json['actividadEconomica'] ?? '')),
            'fuente' => 'Consulta rápida de datos públicos RUC',
            'padron_actualizado_en' => trim((string) ($json['as_of'] ?? $json['capturadoEn'] ?? '')),
            'origen_tecnico' => 'fast_provider',
        ];
    }
}
