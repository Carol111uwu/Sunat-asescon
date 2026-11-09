<?php
declare(strict_types=1);

require_once __DIR__.'/ClienteHttpSunat.php';

                                                                                      
final class AccesoDirectoSol
{
    private const FUENTE = 'https://www.sunat.gob.pe/p/js/apps/main.js';
    private const DESTINOS = [
        'declaraciones' => ['declaraPagoCarrito', '/cl-ti-itmenu2/AutenticaMenuInternetPlataforma.htm'],
        'tramites' => ['tramiteConsulta', '/cl-ti-itmenucabina/AutenticaMenuInternet.htm'],
    ];

    public function __construct(private ?ContratoHttpSunat $http = null)
    {
        $this->http ??= new ClienteHttpSunat();
    }

    public static function autorizado(mysqli $db, int $cliente): bool
    {
        if (!asesconPuedeGestionarCliente($db, $cliente)) return false;
        foreach (['CLIENTES'=>'editar', 'DECLARACIONES'=>'ejecutar', 'PDT_0621'=>'ejecutar', 'SIRE'=>'ejecutar'] as $modulo=>$accion) {
            if (asesconTienePermiso($db, $modulo, $accion)) return true;
        }
        return false;
    }

    public function parametros(string $destino): array
    {
        if (!isset(self::DESTINOS[$destino])) throw new DomainException('Destino SUNAT no válido.');
        $cache = $_SESSION['sunat_entrada_publica'][$destino] ?? [];
        if (session_status() === PHP_SESSION_ACTIVE && (int)($cache['vence'] ?? 0) > time()) {
            return self::interpretar((string)$cache['fuente'], $destino);
        }
        $respuesta = $this->http->getBinary(self::FUENTE);
        $fuente = (string)($respuesta['body'] ?? '');
        $parametros = self::interpretar($fuente, $destino);
        if (session_status() === PHP_SESSION_ACTIVE) {
                                                                                            
            $_SESSION['sunat_entrada_publica'][$destino] = ['fuente'=>$fuente, 'vence'=>time()+3600];
        }
        return $parametros;
    }

    public static function interpretar(string $fuente, string $destino): array
    {
        if (!isset(self::DESTINOS[$destino]) || strlen($fuente)>1024*1024) {
            throw new DomainException('Entrada SUNAT no válida.');
        }
        [$funcion, $retorno] = self::DESTINOS[$destino];
        if (!preg_match('~function\s+'.preg_quote($funcion, '~').'\s*\(\s*\)\s*\{(.*?)\n\}~s', $fuente, $bloque)) {
            throw new DomainException('SUNAT cambió su entrada de acceso. Se requiere actualizar el conector.');
        }
        $patronUrl = <<<'REGEX'
~window\.open\(\s*['"](https://[^'"]+)['"]~
REGEX;
        if (!preg_match($patronUrl, $bloque[1], $enlace)) throw new DomainException('SUNAT no publicó una entrada reconocible.');
        $url = parse_url($enlace[1]);
        $ruta = (string)($url['path'] ?? '');
        if (($url['scheme'] ?? '') !== 'https' || ($url['host'] ?? '') !== 'api-seguridad.sunat.gob.pe'
            || isset($url['user']) || isset($url['pass']) || isset($url['port'])
            || !preg_match('~^/v1/clientessol/[a-f0-9-]{36}/oauth2/loginMenuSol$~D', $ruta)) {
            throw new DomainException('La entrada no corresponde al autenticador oficial de SUNAT.');
        }
        $consulta = [];
        foreach (explode('&', (string)($url['query'] ?? '')) as $parte) {
            [$nombre, $valor] = array_pad(explode('=', $parte, 2), 2, '');
            $consulta[rawurldecode($nombre)] = rawurldecode($valor);
        }
                                                                          
        $original = parse_url((string)($consulta['originalUrl'] ?? ''));
        $estado = (string)($consulta['state'] ?? '');
        if (($original['scheme'] ?? '') !== 'https' || ($original['host'] ?? '') !== 'e-menu.sunat.gob.pe'
            || ($original['path'] ?? '') !== $retorno || isset($original['user']) || isset($original['port'])
            || isset($original['query']) || isset($original['fragment'])
            || !preg_match('~^[A-Za-z0-9+/=_-]{1,12000}$~D', $estado)) {
            throw new DomainException('Los parámetros de retorno de SUNAT no son válidos.');
        }
        return [
            'accion'=>'https://api-seguridad.sunat.gob.pe'.substr($ruta, 0, -strlen('loginMenuSol')).'j_security_check',
            'originalUrl'=>'https://e-menu.sunat.gob.pe'.$retorno,
            'state'=>$estado,
            'lang'=>'es-PE',
        ];
    }
}
