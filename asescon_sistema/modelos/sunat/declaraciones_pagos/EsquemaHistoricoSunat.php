<?php
declare(strict_types=1);
final class EsquemaHistoricoSunat
{
    public static function comprobar(mysqli $db): bool
    {
        $r=$db->query("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN('sunat_configuracion_modulo','sunat_declaraciones','sunat_pagos','sunat_detracciones','sunat_historial_consultas','sunat_historial_cambios')");
        return (int)$r->fetch_assoc()['n']===6;
    }
    public static function preparar(mysqli $db): void
    {
        if(!self::comprobar($db))throw new DomainException('Importa la base histórica SUNAT y concede acceso MySQL a esa base.');
        $sql=(string)file_get_contents(__DIR__.'/../../../base_datos/migraciones/2026_10_22_cache_historico_sunat.sql');
        foreach(explode(';',$sql) as $q)if(trim($q)!=='')$db->query($q);
    }
}
