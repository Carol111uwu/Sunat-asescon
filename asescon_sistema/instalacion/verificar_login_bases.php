<?php
declare(strict_types=1);
                                                                    
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
$proyecto=dirname(__DIR__);
$ruta=dirname($proyecto).'/asescon_recursos_privados/diagnostico_login_bases.json';
$informe=['fecha'=>gmdate('c'),'estado'=>'ERROR','php_cli'=>PHP_VERSION,'principal'=>false,'sunat'=>false,'usuarios'=>[], 'datos_guardados'=>[]];
try {
    require_once $proyecto.'/configuracion/conexiones_bases_datos.php';
    $principal=asesconConexionPrincipal();
    $sunat=asesconConexionHistoricoSunat();
    $principal->query('SELECT 1');
    $sunat->query('SELECT 1');
    $informe['principal']=true;
    $informe['sunat']=true;
                                                                                                
    foreach (['clientes'=>'clientes','buzon_sol_sunat'=>'notificaciones_sol','buzon_sunafil'=>'notificaciones_sunafil'] as $tabla=>$etiqueta) {
        $consulta=$principal->query('SELECT COUNT(*) AS total FROM `'.$tabla.'`');
        $informe['datos_guardados'][$etiqueta]=(int)($consulta->fetch_assoc()['total']??0);
    }
    $consulta=$principal->query("SELECT COUNT(*) AS total FROM buzon_sol_sunat WHERE archivo IS NOT NULL AND TRIM(archivo)<>''");
    $informe['datos_guardados']['pdf_sol_vinculados']=(int)($consulta->fetch_assoc()['total']??0);
    $informe['runtime_preparado']=is_file(dirname($proyecto).'/asescon_recursos_privados/RUNTIME_OK');
    $sql="SELECT u.usuario,u.tipo_cuenta,u.estado,u.acceso_todos_clientes,r.codigo AS rol,
          u.password FROM usuarios u INNER JOIN roles r ON r.id_rol=u.id_rol
          WHERE u.usuario IN ('asescon','creador')";
    $res=$principal->query($sql);
    while ($fila=$res->fetch_assoc()) {
        $nombre=(string)$fila['usuario'];
        $correcto=($nombre==='asescon' && $fila['tipo_cuenta']==='CLIENTE') ||
                  ($nombre==='creador' && $fila['tipo_cuenta']==='CREADOR');
        $informe['usuarios'][$nombre]=[
            'tipo_correcto'=>$correcto,
            'rol_main'=>$fila['rol']==='MAIN',
            'activo'=>(int)$fila['estado']===1,
            'todos_clientes'=>(int)$fila['acceso_todos_clientes']===1,
            'hash_bcrypt'=>(password_get_info((string)$fila['password'])['algoName']??'')==='bcrypt',
        ];
    }
    $informe['estado']=count($informe['usuarios'])===2 &&
        array_reduce($informe['usuarios'],static fn($ok,$u)=>$ok && !in_array(false,$u,true),true)
        ? 'OK' : 'REVISAR_USUARIOS';
} catch (Throwable $e) {
                                                                                           
    $informe['estado']='ERROR_CONEXION_O_ESQUEMA';
}
if (!is_dir(dirname($ruta))) mkdir(dirname($ruta),0700,true);
file_put_contents($ruta,json_encode($informe,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
chmod($ruta,0600);
echo $informe['estado'].PHP_EOL;
