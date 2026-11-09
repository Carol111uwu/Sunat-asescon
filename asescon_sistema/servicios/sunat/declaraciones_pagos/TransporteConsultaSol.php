<?php
declare(strict_types=1);

interface ContratoConsultaSol
{
    public function solicitar(string $url, ?array $formulario = null, array $cabeceras = []): array;
    public function cookies(): array;
}

final class TransporteConsultaSol implements ContratoConsultaSol
{
    private CurlHandle $curl;
    private const HOSTS = ['www.sunat.gob.pe','api-seguridad.sunat.gob.pe','e-menu.sunat.gob.pe','e-plataformaunica.sunat.gob.pe'];

    public function __construct(array $cookies = [])
    {
        if (!extension_loaded('curl')) throw new DomainException('Se necesita cURL de PHP para consultar SUNAT.');
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_COOKIEFILE, '');
        foreach ($cookies as $cookie) curl_setopt($this->curl, CURLOPT_COOKIELIST, (string)$cookie);
    }

    public static function validarUrl(string $url): void
    {
        $u = parse_url($url);
        if (!$u || ($u['scheme'] ?? '') !== 'https' || !in_array($u['host'] ?? '', self::HOSTS, true)
            || isset($u['user']) || isset($u['pass']) || isset($u['port']) || isset($u['fragment'])) {
            throw new DomainException('Destino de consulta SUNAT no autorizado.');
        }
    }

    public function solicitar(string $url, ?array $formulario = null, array $cabeceras = []): array
    {
        self::validarUrl($url);
        $headers = []; $bytes = ''; $exceso = false;
        curl_setopt_array($this->curl, [
            CURLOPT_URL=>$url, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>25, CURLOPT_USERAGENT=>'Mozilla/5.0 ASESCON/1.0',
            CURLOPT_HTTPGET=>$formulario === null, CURLOPT_POST=>$formulario !== null,
            CURLOPT_HTTPHEADER=>$cabeceras, CURLOPT_RETURNTRANSFER=>false,
            CURLOPT_HEADERFUNCTION=>static function ($ch, string $linea) use (&$headers): int {
                $p = strpos($linea, ':');
                if ($p !== false) $headers[strtolower(trim(substr($linea, 0, $p)))] = trim(substr($linea, $p+1));
                return strlen($linea);
            },
            CURLOPT_WRITEFUNCTION=>static function ($ch, string $parte) use (&$bytes, &$exceso): int {
                if (strlen($bytes)+strlen($parte)>25*1024*1024) { $exceso=true; return 0; }
                $bytes .= $parte; return strlen($parte);
            },
        ]);
        if ($formulario !== null) curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($formulario, '', '&', PHP_QUERY_RFC3986));
        if (curl_exec($this->curl) === false) {
            throw new DomainException($exceso ? 'La respuesta de SUNAT supera el límite de 25 MB.' : 'No se pudo completar la conexión segura con SUNAT. Se conserva el historial anterior.');
        }
        return ['status'=>(int)curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE), 'headers'=>$headers, 'body'=>$bytes];
    }

    public function cookies(): array { return curl_getinfo($this->curl, CURLINFO_COOKIELIST) ?: []; }
}
