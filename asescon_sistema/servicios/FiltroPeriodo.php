<?php

declare(strict_types=1);
require_once __DIR__.'/sunat/PeriodosSire.php';

final class FiltroPeriodo
{
    public static function normalizar(string $periodo, ?string $defecto = null): string
    {
        $periodo = preg_replace('/\D+/', '', $periodo) ?? '';
        if (PeriodosSire::permitido($periodo)) {
            return $periodo;
        }
        return $defecto !== null && PeriodosSire::permitido($defecto)
            ? $defecto : PeriodosSire::ahoraPeru()->format('Ym');
    }

    public static function esAnual(string $periodo): bool
    {
        return (bool)preg_match('/^20\d{2}00$/', $periodo);
    }

    public static function anio(string $periodo): string
    {
        return substr(self::normalizar($periodo), 0, 4);
    }

    public static function mes(string $periodo): string
    {
        return substr(self::normalizar($periodo), 4, 2);
    }

    public static function etiqueta(string $periodo): string
    {
        $periodo = self::normalizar($periodo);
        $anio = substr($periodo, 0, 4);
        $mes = (int)substr($periodo, 4, 2);
        if ($mes === 0) {
            return 'Todo ' . $anio . ' · Anual';
        }
        $meses = [1 => 'Enero',2 => 'Febrero',3 => 'Marzo',4 => 'Abril',5 => 'Mayo',6 => 'Junio',7 => 'Julio',8 => 'Agosto',9 => 'Septiembre',10 => 'Octubre',11 => 'Noviembre',12 => 'Diciembre'];
        return ($meses[$mes] ?? 'Mes') . ' ' . $anio;
    }

    public static function condicion(mysqli $conexion, string $columna, string $periodo): string
    {
        $periodo = self::normalizar($periodo);
        if (self::esAnual($periodo)) {
            $anio = $conexion->real_escape_string(substr($periodo, 0, 4));
            return "{$columna} LIKE '{$anio}%'";
        }
        $p = $conexion->real_escape_string($periodo);
        return "{$columna}='{$p}'";
    }
}
