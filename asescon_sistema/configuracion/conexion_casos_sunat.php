<?php
declare(strict_types=1);
require_once __DIR__.'/cargador_configuracion_privada.php';
function asesconConfiguracionCasosSunat():array
{
    $privada=asesconCargarConfiguracionPrivada();$config=$privada['db_sunat']??[];
    foreach(['host'=>'HOST','usuario'=>'USER','password'=>'PASSWORD','base_datos'=>'NAME','puerto'=>'PORT'] as $k=>$e){$v=getenv('ASESCON_DB_SUNAT_'.$e);if($v!==false)$config[$k]=$v;}
    return is_array($config)?$config:[];
}
function asesconConexionCasosSunat():mysqli
{
    static $db=null;if($db instanceof mysqli)return $db;
    $c=asesconConfiguracionCasosSunat();
    foreach(['host','usuario','base_datos'] as $campo)if(trim((string)($c[$campo]??''))==='')throw new DomainException('Falta configurar db_sunat para guardar los casos en bd_asescon_sunat.');
    $privada=asesconCargarConfiguracionPrivada();$principal=(string)(getenv('ASESCON_DB_NAME')?:($privada['db']['base_datos']??''));
    if($principal!==''&&(string)$c['base_datos']===$principal)throw new DomainException('db_sunat debe apuntar a la base separada bd_asescon_sunat, no a la base principal.');
    $db=new mysqli((string)$c['host'],(string)$c['usuario'],(string)($c['password']??''),(string)$c['base_datos'],(int)($c['puerto']??3306));
    $db->set_charset('utf8mb4');$db->query("SET time_zone='-05:00'");return $db;
}
