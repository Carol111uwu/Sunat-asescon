<?php

declare(strict_types=1);

final class NormalizadorCredencialesSunat
{
    private function __construct()
    {
    }

    public static function normalizar(array $credenciales): array
    {
        $ruc = preg_replace('/\D+/', '', (string) ($credenciales['ruc'] ?? '')) ?? '';
        $usuario = self::limpiarTexto((string) ($credenciales['usuario_sol'] ?? ''), true);
        $claveSol = self::limpiarTexto((string) ($credenciales['clave_sol'] ?? ''), false);
        $clientId = self::limpiarTexto((string) ($credenciales['client_id_sunat'] ?? ''), true);
        $clientSecret = self::limpiarTexto((string) ($credenciales['client_secret_sunat'] ?? ''), false);

        return [
            'ruc' => $ruc,
            'usuario_sol' => $usuario,
            'clave_sol' => $claveSol,
            'client_id_sunat' => $clientId,
            'client_secret_sunat' => $clientSecret,
        ];
    }

    public static function huellaSire(array $credenciales): string
    {
        $c = self::normalizar($credenciales);
        return hash('sha256', implode("\n", [
            $c['ruc'],
            $c['usuario_sol'],
            $c['clave_sol'],
            $c['client_id_sunat'],
            $c['client_secret_sunat'],
        ]));
    }

    private static function limpiarTexto(string $valor, bool $quitarEspaciosInternos): string
    {

        $valor = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $valor) ?? $valor;

        $valor = str_replace("\xC2\xA0", ' ', $valor);
        $valor = trim($valor);

        if ($quitarEspaciosInternos) {
            $valor = preg_replace('/\s+/u', '', $valor) ?? $valor;
        }

        return $valor;
    }
}
