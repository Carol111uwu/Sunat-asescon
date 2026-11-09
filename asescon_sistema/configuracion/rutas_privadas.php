<?php
declare(strict_types=1);

   
                                                                      
                                                                 
                                                       
   
function asesconRaizDatosPrivados(): string
{
    return dirname(__DIR__, 2) . '/asescon_datos_privados';
}

function asesconRutaAlmacenamiento(): string
{
    if (!is_file(asesconRaizDatosPrivados() . '/.htaccess')) {
        throw new RuntimeException('Falta preparar la carpeta privada de documentos ASESCON.');
    }
    return asesconRaizDatosPrivados() . '/almacenamiento';
}
