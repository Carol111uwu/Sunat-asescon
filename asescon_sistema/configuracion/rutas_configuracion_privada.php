<?php

declare(strict_types=1);

function asesconDirectoriosPrivadosCandidatos(): array
{
    $explicito=trim((string)(getenv('ASESCON_PRIVATE_DIR')?:''));
                                                                                           
                                                                                                
    $proyecto=dirname(__DIR__);
    $candidatos=[dirname($proyecto).'/asescon_secure'];
    if($explicito!=='')array_unshift($candidatos,$explicito);
    return array_values(array_unique($candidatos));
}

function asesconDirectorioPrivado(): string
{
    $candidatos = asesconDirectoriosPrivadosCandidatos();

    foreach ($candidatos as $directorio) {
        if (is_dir($directorio)) {
            return $directorio;
        }
    }

    return $candidatos[0] ?? (dirname(__DIR__,2) . DIRECTORY_SEPARATOR . 'asescon_secure');
}

function asesconRutaSecretosPrivados(): string
{
    $archivoDefinido = trim((string)(getenv('ASESCON_SECRET_FILE') ?: ''));
    if ($archivoDefinido !== '') {
        return $archivoDefinido;
    }

    foreach (asesconDirectoriosPrivadosCandidatos() as $directorio) {
        $archivo = $directorio . DIRECTORY_SEPARATOR . 'secretos.php';
        if (is_file($archivo)) {
            return $archivo;
        }
    }

    return asesconDirectorioPrivado() . DIRECTORY_SEPARATOR . 'secretos.php';
}

function asesconRutaCredencialesMaestras(): string
{
    foreach (asesconDirectoriosPrivadosCandidatos() as $directorio) {
        $archivo = $directorio . DIRECTORY_SEPARATOR . 'credenciales.servidor.php';
        if (is_file($archivo)) {
            return $archivo;
        }
    }

    return asesconDirectorioPrivado() . DIRECTORY_SEPARATOR . 'credenciales.servidor.php';
}
