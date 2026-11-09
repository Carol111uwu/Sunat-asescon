<?php

declare(strict_types=1);

final class PeriodosSire
{
    public const ANIO_INICIAL = 2021;

    public static function ahoraPeru(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
    }

                                                                               
    public static function permitido(string $periodo, bool $anual = true): bool
    {
        if (!preg_match('/^(20\d{2})(00|0[1-9]|1[0-2])$/D', $periodo, $coincide)) return false;
        $anio = (int)$coincide[1];
        $mes = (int)$coincide[2];
        $ahora = self::ahoraPeru();
        $actual = (int)$ahora->format('Y');
        if ($anio < self::ANIO_INICIAL || $anio > $actual || ($mes === 0 && !$anual)) return false;
        return $anio !== $actual || $mes === 0 || $mes <= (int)$ahora->format('n');
    }

    public static function listar(mysqli $conexion, int $idCliente, ?string $periodoActual = null, int $meses = 48): array
    {
        $periodos = [];
        if ($idCliente > 0) {
            foreach (['sire','rvie','rce'] as $tabla) {
                $stmt = $conexion->prepare("SELECT DISTINCT periodo FROM {$tabla} WHERE id_cliente=? AND periodo REGEXP '^20[0-9]{4}$' ORDER BY periodo DESC");
                if (!$stmt) {
                    continue;
                }
$stmt->bind_param('i', $idCliente);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $p = (string)($row['periodo'] ?? '');
                    if (self::permitido($p, false)) {
                        $periodos[$p] = $p;
                    }
                }
                $stmt->close();
            }
        }

        $base = self::ahoraPeru()->modify('first day of this month');
        $totalMeses = max($meses, ((int)$base->format('Y')-self::ANIO_INICIAL)*12+(int)$base->format('n'));
        for ($i = 0; $i < $totalMeses; $i++) {
            $p = $base->modify("-{$i} months")->format('Ym');
            if (!self::permitido($p, false)) continue;
            $periodos[$p] = $p;
        }

        $periodoActual = preg_replace('/\D+/', '', (string)$periodoActual) ?: '';
        if (self::permitido($periodoActual)) {
            if (substr($periodoActual, 4, 2) !== '00') {
                $periodos[$periodoActual] = $periodoActual;
            }
        }

        $porAnio = [];
        foreach (array_values($periodos) as $p) {
            $p = (string)$p;
            $porAnio[substr($p, 0, 4)][] = $p;
        }
        krsort($porAnio, SORT_STRING);
        $salida = [];
        foreach ($porAnio as $anio => $mesesAnio) {
            $salida[] = $anio . '00';
            rsort($mesesAnio, SORT_STRING);
            foreach ($mesesAnio as $p) {
                $salida[] = $p;
            }
        }
        if (self::permitido($periodoActual) && preg_match('/^20\d{2}00$/', $periodoActual) && !in_array($periodoActual, $salida, true)) {
            array_unshift($salida, $periodoActual);
        }
        return $salida;
    }

    public static function anios(mysqli $conexion, int $idCliente, ?string $periodoActual = null, int $cantidad = 6): array
    {
                                                                         
                                                                              
        return range((int)self::ahoraPeru()->format('Y'), self::ANIO_INICIAL);
    }

    public static function meses(bool $incluirAnual = true): array
    {
        $meses = [
            '01' => 'Enero','02' => 'Febrero','03' => 'Marzo','04' => 'Abril',
            '05' => 'Mayo','06' => 'Junio','07' => 'Julio','08' => 'Agosto',
            '09' => 'Septiembre','10' => 'Octubre','11' => 'Noviembre','12' => 'Diciembre'
        ];
        return $incluirAnual ? ['00' => 'Todos los meses / Anual'] + $meses : $meses;
    }

    public static function construir(string $anio, string $mes): string
    {
        $anio = preg_replace('/\D+/', '', $anio) ?: '';
        $mes = preg_replace('/\D+/', '', $mes) ?: '';
        if (!preg_match('/^20\d{2}$/', $anio) || !preg_match('/^(00|0[1-9]|1[0-2])$/', $mes) || !self::permitido($anio.$mes)) {
            return self::ahoraPeru()->format('Ym');
        }
        return $anio . $mes;
    }

    public static function esAnual(string $periodo): bool
    {
        return (bool)preg_match('/^20\d{2}00$/', $periodo);
    }

    public static function anio(string $periodo): string
    {
        return preg_match('/^(20\d{2})/', $periodo, $m) ? $m[1] : self::ahoraPeru()->format('Y');
    }

    public static function etiqueta(string $periodo): string
    {
        if (preg_match('/^(20\d{2})00$/', $periodo, $m)) {
            return 'Todo ' . $m[1] . ' · Anual';
        }
        if (!preg_match('/^(20\d{2})(0[1-9]|1[0-2])$/', $periodo, $m)) {
            return $periodo;
        }
        $meses = [1 => 'Enero',2 => 'Febrero',3 => 'Marzo',4 => 'Abril',5 => 'Mayo',6 => 'Junio',7 => 'Julio',8 => 'Agosto',9 => 'Septiembre',10 => 'Octubre',11 => 'Noviembre',12 => 'Diciembre'];
        return $meses[(int)$m[2]] . ' ' . $m[1] . ' · ' . $periodo;
    }
}
