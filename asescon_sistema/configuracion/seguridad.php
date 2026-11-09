<?php

declare(strict_types=1);

function rutaClaveMaestraAsescon(): string
{
    require_once __DIR__ . '/rutas_configuracion_privada.php';
    return asesconRutaCredencialesMaestras();
}

function obtenerClaveCifradoSunat(): string
{
    static $clave = null;
    if ($clave !== null) {
        return $clave;
    }
    if (!extension_loaded('openssl')) {
        throw new RuntimeException('La extensión OpenSSL de PHP es necesaria para proteger las credenciales SUNAT.');
    }

    $archivo = rutaClaveMaestraAsescon();
    $directorio = dirname($archivo);
    if (!is_dir($directorio) && !@mkdir($directorio, 0700, true) && !is_dir($directorio)) {
        throw new RuntimeException('No se pudo crear la carpeta segura de credenciales fuera del proyecto.');
    }

    $candado=fopen($archivo.'.lock','c+');
    if(!$candado||!flock($candado,LOCK_EX))throw new RuntimeException('No se pudo proteger la clave maestra.');
    try {
        if (!file_exists($archivo)) {
            $contenido="<?php\nreturn '".base64_encode(random_bytes(32))."';\n";
            $temporal=tempnam($directorio,'clave_');
            if($temporal===false)throw new RuntimeException('No se pudo preparar la clave maestra.');
            try {
                if(file_put_contents($temporal,$contenido)!==strlen($contenido))throw new RuntimeException('No se pudo escribir la clave maestra.');
                chmod($temporal,0600);
                if(!rename($temporal,$archivo))throw new RuntimeException('No se pudo confirmar la clave maestra.');
            } finally {if(is_file($temporal))unlink($temporal);}
        }
        $valor=require $archivo;
    } finally {flock($candado,LOCK_UN);fclose($candado);}
    $decodificada = base64_decode((string)$valor, true);
    if ($decodificada === false || strlen($decodificada) !== 32) {
        throw new RuntimeException('La clave maestra de credenciales no es válida.');
    }
    $clave = $decodificada;
    return $clave;
}

function cifrarCredencialSunat(?string $valor): ?string
{
    $valor = trim((string)$valor);
    if ($valor === '') {
        return null;
    }
    $iv = random_bytes(12);
    $tag = '';
    $cifrado = openssl_encrypt($valor, 'aes-256-gcm', obtenerClaveCifradoSunat(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cifrado === false) {
        throw new RuntimeException('No se pudo cifrar una credencial SUNAT.');
    }
    return base64_encode($iv . $tag . $cifrado);
}

function descifrarCredencialSunat(?string $valorCifrado): ?string
{
    $valorCifrado = trim((string)$valorCifrado);
    if ($valorCifrado === '') {
        return null;
    }
    $datos = base64_decode($valorCifrado, true);
    if ($datos === false || strlen($datos) < 29) {
        throw new RuntimeException('La credencial SUNAT almacenada no tiene un formato válido.');
    }
    $iv = substr($datos, 0, 12);
    $tag = substr($datos, 12, 16);
    $cifrado = substr($datos, 28);
    $plano = openssl_decrypt($cifrado, 'aes-256-gcm', obtenerClaveCifradoSunat(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($plano === false) {
        throw new RuntimeException('No se pudo descifrar la credencial SUNAT. Vuelve a registrarla si la clave maestra cambió.');
    }
    return $plano;
}
