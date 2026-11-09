<?php
declare(strict_types=1);
final class DiagnosticoPhp
{
    public static function evaluar(?string $version=null):array
    {
        $version??=PHP_VERSION;$rama=implode('.',array_slice(explode('.',$version),0,2));
        $minimos=['8.2'=>'8.2.35','8.3'=>'8.3.36','8.4'=>'8.4.27','8.5'=>'8.5.12'];$minimo=$minimos[$rama]??null;
        $ok=$minimo!==null&&version_compare($version,$minimo,'>=');
        return ['version'=>$version,'minimo'=>$minimo,'parche_upstream'=>$ok,'mensaje'=>$ok?'Esta versión alcanza el mínimo publicado por PHP el 21/10/2026. Mantén las actualizaciones del proveedor.':'Revisa el parche de PHP con tu hosting. Si usa una versión con parches retroportados, verifica el paquete con el proveedor.','fuente'=>'https://www.php.net/index.php'];
    }
}
