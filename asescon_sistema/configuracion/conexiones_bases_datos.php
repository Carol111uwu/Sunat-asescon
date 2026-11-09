<?php
declare(strict_types=1);
require_once __DIR__.'/cargador_configuracion_privada.php';

function asesconConfiguracionBase(string $perfil, ?array $privada=null, ?array $entorno=null): array
{
    if (!in_array($perfil,['principal','historico_sunat'],true)) throw new InvalidArgumentException('Base no reconocida.');
    $privada ??= asesconCargarConfiguracionPrivada();
    $prefijo=$perfil==='principal'?'ASESCON_DB_':'ASESCON_DB_SUNAT_';
    $principal=is_array($privada['db']??null)?$privada['db']:[];
    $config=$perfil==='principal'?$principal:array_replace(
        $principal,
        ['base_datos'=>'sep17ases1126d_bd_asescon_sunat'],
        is_array($privada['db_sunat']??null)?$privada['db_sunat']:[]
    );
    foreach (['host'=>'HOST','usuario'=>'USER','password'=>'PASSWORD','base_datos'=>'NAME','puerto'=>'PORT'] as $campo=>$variable) {
        if ($perfil==='historico_sunat' && $campo!=='base_datos' && !array_key_exists($campo,$privada['db_sunat']??[])) {
            $v=$entorno!==null?($entorno['ASESCON_DB_'.$variable]??false):getenv('ASESCON_DB_'.$variable);
            if($v!==false) $config[$campo]=$v;
        }
        $v=$entorno!==null?($entorno[$prefijo.$variable]??false):getenv($prefijo.$variable);
        if($v!==false) $config[$campo]=$v;
    }
    $config=array_replace(['host'=>'','usuario'=>'','password'=>'','base_datos'=>'','puerto'=>3306],$config);
    foreach(['host','usuario','base_datos'] as $campo) if(trim((string)$config[$campo])==='') throw new DomainException('Completa la configuración MySQL de '.$perfil.' en secretos.php.');
    $config['puerto']=(int)$config['puerto'];
    if($config['puerto']<1||$config['puerto']>65535) throw new DomainException('Puerto MySQL no válido para '.$perfil.'.');
    if($perfil==='historico_sunat') {
        $p=asesconConfiguracionBase('principal',$privada,$entorno);
        if(strcasecmp((string)$p['host'],(string)$config['host'])===0 && $p['puerto']===$config['puerto'] && $p['base_datos']===$config['base_datos']) throw new DomainException('El histórico SUNAT debe usar su propia base de datos.');
    }
    return $config;
}

function asesconConexionBase(string $perfil): mysqli
{
    static $conexiones=[];
    if(isset($conexiones[$perfil])) return $conexiones[$perfil];
    $c=asesconConfiguracionBase($perfil);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db=mysqli_init();$db->options(MYSQLI_OPT_CONNECT_TIMEOUT,5);
    $db->real_connect((string)$c['host'],(string)$c['usuario'],(string)$c['password'],(string)$c['base_datos'],$c['puerto']);
    $db->set_charset('utf8mb4');$db->query("SET time_zone='-05:00'");
    return $conexiones[$perfil]=$db;
}
function asesconConexionPrincipal(): mysqli { return asesconConexionBase('principal'); }
function asesconConexionHistoricoSunat(): mysqli { return asesconConexionBase('historico_sunat'); }
