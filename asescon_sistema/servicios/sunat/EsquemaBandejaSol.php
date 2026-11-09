<?php
declare(strict_types=1);
final class EsquemaBandejaSol
{
    public static function prepararContenido(mysqli $db):void
    {
        $col=$db->query("SHOW COLUMNS FROM buzon_sol_sunat LIKE 'observaciones'")->fetch_assoc();
        if($col&&!in_array(strtolower((string)$col['Type']),['text','mediumtext','longtext'],true))$db->query('ALTER TABLE buzon_sol_sunat MODIFY COLUMN observaciones LONGTEXT NULL');
    }
}
