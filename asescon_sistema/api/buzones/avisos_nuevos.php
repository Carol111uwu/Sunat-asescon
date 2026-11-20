<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
asesconExigirLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

$puedeSol=asesconTienePermiso($conexion,'BUZON_SOL','ver');
$puedeSunafil=asesconTienePermiso($conexion,'BUZON_SUNAFIL','ver');
if (!$puedeSol && !$puedeSunafil) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'mensaje'=>'Acceso no autorizado.']);
    exit;
}
try {
    $existe=$conexion->query("SHOW TABLES LIKE 'buzon_novedades'");
    if(!$existe || !$existe->num_rows) {
        echo json_encode(['ok'=>true,'disponible'=>false,'nuevas'=>0,'avisos'=>[]]);
        exit;
    }
    $idUsuario=(int)(asesconUsuarioActual($conexion)['id_usuario']??0);
    $filtro=asesconFiltroClientesSql($conexion,'c');
    $accesos=[];
    if($puedeSol)$accesos[]="'SOL'";
    if($puedeSunafil)$accesos[]="'SUNAFIL'";
    $tipos=implode(',',$accesos);
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        if(!asesconValidarCsrf((string)($_POST['csrf_token']??''),'csrf_novedades_buzones')){
            http_response_code(403);echo json_encode(['ok'=>false,'mensaje'=>'Sesión vencida.']);exit;
        }
        $id=(int)($_POST['id_novedad']??0);
        if($id<1){http_response_code(400);echo json_encode(['ok'=>false,'mensaje'=>'Aviso no válido.']);exit;}
        // Sólo puede marcar como visto un evento dentro de las empresas y buzones autorizados.
        $verificar=$conexion->prepare("SELECT n.id_novedad FROM buzon_novedades n
            JOIN clientes c ON c.id_cliente=n.id_cliente
            WHERE n.id_novedad=? AND n.buzon IN ($tipos) AND ($filtro) LIMIT 1");
        $verificar->bind_param('i',$id);$verificar->execute();
        $permitido=(bool)$verificar->get_result()->fetch_assoc();$verificar->close();
        if(!$permitido){http_response_code(403);echo json_encode(['ok'=>false,'mensaje'=>'Aviso no autorizado.']);exit;}
        $marcar=$conexion->prepare("INSERT IGNORE INTO buzon_novedades_vistas(id_novedad,id_usuario) VALUES(?,?)");
        $marcar->bind_param('ii',$id,$idUsuario);$marcar->execute();$marcar->close();
        echo json_encode(['ok'=>true]);exit;
    }
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){
        http_response_code(405);echo json_encode(['ok'=>false,'mensaje'=>'Método no permitido.']);exit;
    }
    $base=" FROM buzon_novedades n
        JOIN clientes c ON c.id_cliente=n.id_cliente
        LEFT JOIN buzon_novedades_vistas v ON v.id_novedad=n.id_novedad AND v.id_usuario=".(int)$idUsuario."
        WHERE n.buzon IN ($tipos) AND ($filtro) AND v.id_novedad IS NULL";
    $r=$conexion->query('SELECT COUNT(*) cantidad '.$base);
    $total=(int)($r->fetch_assoc()['cantidad']??0);
    $r=$conexion->query('SELECT n.id_novedad,n.buzon,n.id_cliente,n.id_documento,n.detectada_en,c.ruc,c.razon_social '.$base.' ORDER BY n.id_novedad DESC LIMIT 30');
    $avisos=[];
    while($fila=$r->fetch_assoc()){
        $avisos[]=[
            'id_novedad'=>(int)$fila['id_novedad'],
            'buzon'=>(string)$fila['buzon'],
            'id_cliente'=>(int)$fila['id_cliente'],
            'cliente'=>(string)$fila['razon_social'],
            'ruc'=>(string)$fila['ruc'],
            'fecha'=>(string)$fila['detectada_en'],
        ];
    }
    echo json_encode(['ok'=>true,'disponible'=>true,'nuevas'=>$total,'avisos'=>$avisos],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
    error_log('Novedades buzones: '.get_class($e));
    http_response_code(500);
    echo json_encode(['ok'=>false,'mensaje'=>'No se pudieron consultar las novedades. Verifica que la migración esté instalada.']);
}
