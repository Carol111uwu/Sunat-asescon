<?php
declare(strict_types=1);

final class BusquedaClientes
{
    public static function normalizar(string $texto): string
    {
        $texto = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $texto) ?? $texto;
        $texto = trim(preg_replace('/[\s\p{Z}]+/u', ' ', $texto) ?? $texto);
        if (preg_match('/^[\d\s.\-]+$/', $texto)) {
            return preg_replace('/\D/', '', $texto) ?? $texto;
        }
        return $texto;
    }

    public static function terminos(string $texto): array
    {
        $texto = self::normalizar($texto);
        return $texto === '' ? [] : array_values(array_unique(explode(' ', $texto)));
    }

    public static function coincide(array $cliente, string $buscar): bool
    {
        $campos = ['ruc', 'razon_social', 'nombre_comercial', 'nombre_contacto', 'celular', 'telefono', 'correo'];
        $texto = self::comparar(implode(' ', array_map(static fn(string $campo): string => (string)($cliente[$campo] ?? ''), $campos)));
        foreach (self::terminos($buscar) as $termino) {
            if (!str_contains($texto, self::comparar($termino))) { return false; }
        }
        return true;
    }

    public static function condicionSql(string $buscar, array $columnas): array
    {
        foreach ($columnas as $columna) {
            if (!preg_match('/^[a-z_]+\.[a-z_]+$/i', $columna)) {
                throw new InvalidArgumentException('Columna de búsqueda inválida.');
            }
        }
        if ($columnas === []) { throw new InvalidArgumentException('Faltan columnas de búsqueda.'); }
        $condiciones = [];
        $parametros = [];
        foreach (self::terminos($buscar) as $termino) {
            $grupo = [];
            $patron = '%' . strtr($termino, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            foreach ($columnas as $columna) {
                $grupo[] = "COALESCE({$columna},'') LIKE ? ESCAPE '!'";
                $parametros[] = $patron;
            }
            $condiciones[] = '(' . implode(' OR ', $grupo) . ')';
        }
        return ['sql' => implode(' AND ', $condiciones), 'parametros' => $parametros];
    }

    private static function comparar(string $texto): string
    {
        if (class_exists('Normalizer')) {
            $texto = Normalizer::normalize($texto, Normalizer::FORM_D) ?: $texto;
        }
        $texto = preg_replace('/\p{M}/u', '', $texto) ?? $texto;
        $texto = strtr($texto, ['Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n','á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return strtolower(self::normalizar($texto));
    }
}
