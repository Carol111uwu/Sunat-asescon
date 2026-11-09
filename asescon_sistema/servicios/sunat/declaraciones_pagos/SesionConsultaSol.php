<?php
declare(strict_types=1);
require_once __DIR__.'/TransporteConsultaSol.php';
require_once dirname(__DIR__).'/AccesoDirectoSol.php';

final class SesionConsultaSol
{
    public static function comprobarToken(string $token, string $ruc): int
    {
        $trozos=explode('.', $token);
        $json=count($trozos)===3 ? base64_decode(strtr($trozos[1], '-_', '+/'), true) : false;
        $datos=is_string($json) ? json_decode($json, true) : null;
        if (!is_array($datos) || (string)($datos['sub'] ?? '') !== $ruc || (int)($datos['exp'] ?? 0) <= time()+15) {
            throw new DomainException('SUNAT no confirmó una sesión vigente para el RUC seleccionado.');
        }
                                                                                                                        
        return (int)$datos['exp'];
    }

    public static function iniciar(array $credencial, ContratoConsultaSol $http, string $fuente): array
    {
        $p=AccesoDirectoSol::interpretar($fuente, 'declaraciones');
        $r=$http->solicitar($p['accion'], [
            'tipo'=>'2','dni'=>'','custom_ruc'=>$credencial['ruc'],'j_username'=>$credencial['usuario_sol'],
            'j_password'=>$credencial['clave_sol'],'captcha'=>'','originalUrl'=>$p['originalUrl'],
            'lang'=>$p['lang'],'state'=>$p['state'],
        ], ['Content-Type: application/x-www-form-urlencoded']);
        for ($i=0; $i<6 && in_array($r['status'], [301,302,303,307,308], true); $i++) {
            $destino=(string)($r['headers']['location'] ?? '');
                                                                                                           
            TransporteConsultaSol::validarUrl($destino);
            $r=$http->solicitar($destino);
        }
        $html=(string)$r['body'];
        if ($r['status']!==200 || !str_contains($html, (string)$credencial['ruc'])
            || preg_match('/type=["\']password/i', $html)
            || !preg_match('/idCache=(eyJ[A-Za-z0-9_.=\-]+)/', $html, $m)) {
            throw new DomainException('SUNAT no completó el acceso de consulta. Puede solicitar una verificación o rechazar el acceso; el historial se conserva.');
        }
        $token=$m[1]; self::comprobarToken($token, $credencial['ruc']);
        $url='https://e-menu.sunat.gob.pe/cl-ti-itmenu2/MenuInternetPlataforma.htm?'.http_build_query([
            'action'=>'execute','code'=>'55.2.1.1.1','idCache'=>$token,'s'=>'e-plataformaunica',
        ], '', '&', PHP_QUERY_RFC3986);
        $r=$http->solicitar($url);
        for ($i=0; $i<6 && in_array($r['status'], [301,302,303,307,308], true); $i++) {
            $r=$http->solicitar((string)($r['headers']['location'] ?? ''));
        }
        if ($r['status']!==200 || !preg_match('/sessionStorage\.setItem\("token",\s*"([^"]+)"/', $r['body'], $m)) {
            throw new DomainException('SUNAT no habilitó la consulta de declaraciones y pagos. No se modificaron los registros guardados.');
        }
        $vence=self::comprobarToken($m[1], $credencial['ruc']);
        return ['token'=>$m[1], 'vence'=>min($vence-15,time()+300), 'cookies'=>$http->cookies()];
    }
}
