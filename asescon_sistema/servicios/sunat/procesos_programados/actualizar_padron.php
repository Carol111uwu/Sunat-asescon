<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/ServicioPadronSunat.php';

try {
    $forzar = in_array('--force', $argv ?? [], true);
    (new ServicioPadronSunat())->actualizarAhora($forzar);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
