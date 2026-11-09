<?php
declare(strict_types=1);

final class PlantillaCorreo
{
    public static function validarConfiguracion(array $datos): array
    {
        $salida = [];
        foreach (['nombre_remitente'=>120,'plantilla_asunto'=>200,'plantilla_mensaje'=>10000] as $campo=>$limite) {
            $valor = trim((string)($datos[$campo] ?? ''));
            if ($valor === '' || !preg_match('//u',$valor) || preg_match_all('/./us',$valor)>$limite || ($campo !== 'plantilla_mensaje' && preg_match('/[\r\n]/', $valor))) {
                throw new DomainException('Revisa el nombre del remitente y las plantillas.');
            }
            if ($campo !== 'nombre_remitente') { self::validarVariables($valor); }
            $salida[$campo] = $valor;
        }
        foreach (['correo_copia','correo_avisos'] as $campo) {
            $valor = trim((string)($datos[$campo] ?? ''));
            if ($valor !== '' && (!filter_var($valor, FILTER_VALIDATE_EMAIL) || strlen($valor) > 254)) {
                throw new DomainException('Escribe direcciones de correo válidas.');
            }
            $salida[$campo] = $valor;
        }
        $salida['avisos_activos'] = !empty($datos['avisos_activos']) ? 1 : 0;
        if ($salida['avisos_activos'] && $salida['correo_avisos'] === '') {
            throw new DomainException('Indica el correo que recibirá los avisos.');
        }
        return $salida;
    }

    private static function validarVariables(string $texto): void
    {
        preg_match_all('/\{[^{}]+\}/', $texto, $m);
        if (array_diff($m[0], ['{cliente}','{ruc}','{entregables}'])) {
            throw new DomainException('Usa solamente {cliente}, {ruc} y {entregables} en las plantillas.');
        }
    }

    public static function preparar(array $config, array $cliente, array $entregables): array
    {
        if (!$entregables) { throw new DomainException('Selecciona al menos un entregable.'); }
        $variables = ['{cliente}'=>(string)$cliente['razon_social'],'{ruc}'=>(string)$cliente['ruc'],
            '{entregables}'=>implode("\n", array_map(static fn(string $n): string => '• ' . $n, $entregables))];
        $asunto = preg_replace('/[\r\n]+/', ' ', strtr($config['plantilla_asunto'], $variables));
        return ['asunto'=>$asunto,'mensaje'=>strtr($config['plantilla_mensaje'], $variables)];
    }

    public static function nombreEntregable(string $nombre): string
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
        if ($nombre === '' || !preg_match('//u',$nombre) || preg_match_all('/./us',$nombre)>120) { throw new DomainException('Escribe un nombre de entregable de hasta 120 caracteres.'); }
        return $nombre;
    }
}
