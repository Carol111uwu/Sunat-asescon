<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/sunat/AccesoDirectoSol.php';
require_once __DIR__.'/../../servicios/sunat/CargadorCredencialesSunat.php';
asesconExigirLogin();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; form-action https://api-seguridad.sunat.gob.pe https://e-menu.sunat.gob.pe; base-uri 'none'; frame-ancestors 'none'");
try {
    if (empty($_SERVER['HTTPS']) || strtolower((string)$_SERVER['HTTPS']) === 'off') {
        http_response_code(403); throw new DomainException('El acceso SOL requiere HTTPS.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !asesconValidarCsrf((string)($_POST['csrf_token'] ?? ''), 'csrf_acceso_sol')) {
        http_response_code(403); throw new DomainException('Solicitud de acceso no válida.');
    }
    $id = (int)($_POST['id_cliente'] ?? 0);
    if (!AccesoDirectoSol::autorizado($conexion, $id)) {
        http_response_code(403); throw new DomainException('Cliente no autorizado.');
    }
    $parametros = (new AccesoDirectoSol())->parametros((string)($_POST['destino'] ?? 'declaraciones'));
    $cliente = (new CargadorCredencialesSunat($conexion))->obtenerSolPorCliente($id);
    $campos = ['tipo'=>'2', 'dni'=>'', 'custom_ruc'=>$cliente['ruc'], 'j_username'=>$cliente['usuario_sol'],
        'j_password'=>$cliente['clave_sol'], 'captcha'=>'', 'originalUrl'=>$parametros['originalUrl'],
        'state'=>$parametros['state'], 'lang'=>$parametros['lang']];
    asesconRegistrarAuditoria($conexion, 'ABRIR_SOL_DIRECTO', 'SUNAT', $id, 'clientes', (string)$id, 'Envío al formulario oficial de acceso.');
} catch (Throwable $e) {
    if (http_response_code()<400) http_response_code(503);
    error_log('Acceso directo SOL: '.get_class($e));
    $mensaje = $e instanceof DomainException ? $e->getMessage() : 'No se pudo preparar el acceso a SUNAT. Revisa la conexión del servidor y las credenciales SOL guardadas.';
    exit(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'));
}
$esc = static fn($valor): string => htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Abriendo SUNAT</title></head><body>
<p>Abriendo SUNAT para <?=$esc($cliente['cliente'])?> · RUC <?=$esc($cliente['ruc'])?>…</p>
<form id="enviarAccesoSunat" method="post" action="<?=$esc($parametros['accion'])?>" hidden>
<?php foreach ($campos as $nombre=>$valor): ?><input type="hidden" name="<?=$esc($nombre)?>" value="<?=$esc($valor)?>"><?php endforeach; ?>
</form><noscript><button type="submit" form="enviarAccesoSunat">Continuar a SUNAT</button></noscript>
<script src="<?=$esc(asesconUrl('publico/js/sunat/enviar_acceso_sol.js'))?>"></script>
</body></html>
<?php unset($campos, $cliente); ?>
