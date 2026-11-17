<?php

declare(strict_types=1);

final class ArchivoTextoSire
{
    public static function abrir(string $ruta)
    {
        if (!is_file($ruta)) {
            return false;
        }
        $origen = str_ends_with(strtolower($ruta), '.gz') ? 'compress.zlib://' . $ruta : $ruta;
        return @fopen($origen, 'rb');
    }

    public static function hash(string $ruta): string|false
    {
        $flujo = self::abrir($ruta);
        if ($flujo === false) {
            return false;
        }
        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $flujo);
            if (!feof($flujo)) {
                return false;
            }
            return hash_final($hash);
        } finally {
            fclose($flujo);
        }
    }
}
