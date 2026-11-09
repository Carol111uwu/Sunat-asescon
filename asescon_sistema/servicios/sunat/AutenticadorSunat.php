<?php

declare(strict_types=1);

require_once __DIR__ . '/ClienteHttpSunat.php';
require_once __DIR__ . '/ConfiguracionSunat.php';
require_once __DIR__ . '/ExcepcionSunat.php';

final class AutenticadorSunat
{
    public function __construct(private ?ContratoHttpSunat $client = null)
    {
        $this->client ??= new ClienteHttpSunat();
    }

    public function generarTokenSire(array $credenciales): array
    {
        $ruc = trim((string)($credenciales['ruc'] ?? ''));
        $usuarioSol = strtoupper(trim((string)($credenciales['usuario_sol'] ?? '')));
        if(str_starts_with($usuarioSol,$ruc)&&strlen($usuarioSol)>11)$usuarioSol=substr($usuarioSol,11);
        $claveSol = (string)($credenciales['clave_sol'] ?? '');
        $clientId = trim((string)($credenciales['client_id_sunat'] ?? ''));
        $clientSecret = (string)($credenciales['client_secret_sunat'] ?? '');
        $this->validarCredenciales($ruc, $usuarioSol, $claveSol, $clientId, $clientSecret);

        $cacheKey = hash('sha256', $ruc . '|' . $usuarioSol . '|' . $clientId . '|' . hash('sha256', $claveSol) . '|' . hash('sha256', $clientSecret));
        if (session_status() === PHP_SESSION_ACTIVE) {
            $cache = $_SESSION['asescon_sire_tokens'][$cacheKey] ?? null;
            if (is_array($cache) && isset($cache['access_token'], $cache['expires_at']) && (int)$cache['expires_at'] > time() + 60) {
                return ['access_token' => (string)$cache['access_token'],'token_type' => (string)($cache['token_type'] ?? 'Bearer'),'expires_in' => max(1, (int)$cache['expires_at'] - time()),'scope' => (string)($cache['scope'] ?? ConfiguracionSunat::SIRE_SCOPE)];
            }
        }

        $url = rtrim(ConfiguracionSunat::AUTH_BASE_URL, '/') . '/' . rawurlencode($clientId) . '/oauth2/token/';
        $respuesta = $this->client->postForm($url, ['grant_type' => 'password','scope' => ConfiguracionSunat::SIRE_SCOPE,'client_id' => $clientId,'client_secret' => $clientSecret,'username' => $ruc . $usuarioSol,'password' => $claveSol]);
        $accessToken = trim((string)($respuesta['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new ExcepcionSunat('SUNAT respondió correctamente, pero no entregó un access_token.');
        }
        $expires = isset($respuesta['expires_in']) ? (int)$respuesta['expires_in'] : 3600;
        $resultado = ['access_token' => $accessToken,'token_type' => (string)($respuesta['token_type'] ?? 'Bearer'),'expires_in' => $expires,'scope' => (string)($respuesta['scope'] ?? ConfiguracionSunat::SIRE_SCOPE)];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['asescon_sire_tokens'] ??= [];
            $_SESSION['asescon_sire_tokens'][$cacheKey] = ['access_token' => $accessToken,'token_type' => $resultado['token_type'],'scope' => $resultado['scope'],'expires_at' => time() + max(1, $expires)];
            if (count($_SESSION['asescon_sire_tokens']) > 20) {
                $_SESSION['asescon_sire_tokens'] = array_slice($_SESSION['asescon_sire_tokens'], -20, null, true);
            }
        }
        return $resultado;
    }

    private function validarCredenciales(string $ruc, string $usuarioSol, string $claveSol, string $clientId, string $clientSecret): void
    {
        if (!preg_match('/^[0-9]{11}$/', $ruc)) {
            throw new ExcepcionSunat('El RUC SUNAT no tiene un formato válido.');
        }
        if ($usuarioSol === '') {
            throw new ExcepcionSunat('Falta el Usuario SOL.');
        }
        if (trim($claveSol) === '') {
            throw new ExcepcionSunat('Falta la Clave SOL.');
        }
        if ($clientId === '') {
            throw new ExcepcionSunat('Falta el ID de API SUNAT (Client ID).');
        }
        if (trim($clientSecret) === '') {
            throw new ExcepcionSunat('Falta la Clave de API SUNAT (Client Secret).');
        }
    }
}
