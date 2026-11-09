<?php

declare(strict_types=1);

final class PresentacionDatosSire
{
    public static function inicioPeriodo(string $periodo): ?string
    {
        $limites = self::limitesEmision($periodo);
        return $limites === null ? null : $limites[0];
    }

                                                                                     
    public static function limitesEmision(string $periodo): ?array
    {
        if (!preg_match('/^(20\d{2})(00|0[1-9]|1[0-2])$/D', $periodo, $m)) return null;
        $anio = (int)$m[1];
        $mes = (int)$m[2];
        if ($mes === 0) return [sprintf('%04d-01-01', $anio), sprintf('%04d-01-01', $anio + 1)];
        $inicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fin = $mes === 12 ? sprintf('%04d-01-01', $anio + 1) : sprintf('%04d-%02d-01', $anio, $mes + 1);
        return [$inicio, $fin];
    }

    public static function filtrarMesesAnteriores(array $filas, string $periodo, bool $excluir): array
    {
        if (!$excluir) {
            return array_values($filas);
        }
        $limites = self::limitesEmision($periodo);
        if ($limites === null) {
            return array_values($filas);
        }
        [$inicio, $fin] = $limites;
        return array_values(array_filter($filas, static function (array $fila) use ($inicio, $fin): bool {
            $fecha = trim((string)($fila['fecha_emision'] ?? ''));
                                                                                         
            return $fecha !== '' && $fecha >= $inicio && $fecha < $fin;
        }));
    }

    public static function totales(array $filas): array
    {
        $base = 0.0;
        $igv = 0.0;
        $total = 0.0;
        foreach ($filas as $f) {
            $base += (float)($f['base_imponible'] ?? 0);
            $igv += (float)($f['igv'] ?? 0);
            $total += (float)($f['importe_total'] ?? 0);
        }
        return [
            'cantidad_registros' => count($filas),
            'base_imponible' => $base,
            'igv' => $igv,
            'total' => $total,
        ];
    }

    public static function agruparPorTipo(array $filas): array
    {
        $grupos = [];
        foreach ($filas as $f) {
            $tipo = trim((string)($f['tipo_comprobante'] ?? ''));
            if ($tipo === '') {
                $tipo = '—';
            }
            if (!isset($grupos[$tipo])) {
                $grupos[$tipo] = ['tipo' => $tipo,'documentos' => 0,'base' => 0.0,'igv' => 0.0,'total' => 0.0];
            }
            $grupos[$tipo]['documentos']++;
            $grupos[$tipo]['base'] += (float)($f['base_imponible'] ?? 0);
            $grupos[$tipo]['igv'] += (float)($f['igv'] ?? 0);
            $grupos[$tipo]['total'] += (float)($f['importe_total'] ?? 0);
        }
        usort($grupos, static fn(array $a, array $b): int => ($b['documentos'] <=> $a['documentos']) ?: strcmp($a['tipo'], $b['tipo']));

        unset($g);
        return array_values($grupos);
    }

    public static function formatearMonto(float|int|string|null $valor, int $maxDecimales = 4): string
    {
        $numero = (float)($valor ?? 0);
        $texto = number_format($numero, $maxDecimales, '.', ',');
        if (str_contains($texto, '.')) {
            [$entero,$decimal] = explode('.', $texto, 2);
            $decimal = rtrim($decimal, '0');
            if (strlen($decimal) < 2) {
                $decimal = str_pad($decimal, 2, '0');
            }
            return $entero . '.' . $decimal;
        }
        return $texto . '.00';
    }
}
