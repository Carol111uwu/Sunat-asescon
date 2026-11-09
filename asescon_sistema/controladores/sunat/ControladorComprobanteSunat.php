<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/ComprobanteSunat.php';

final class ControladorComprobanteSunat
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(int $clienteFiltro = 0, int $pagina = 1): array
    {
        asesconExigirPermiso($this->conexion, 'COMPROBANTES_SUNAT', 'ver');

        if ($clienteFiltro > 0 && !asesconPuedeVerCliente($this->conexion, $clienteFiltro)) {
            $clienteFiltro = 0;
        }
        $modelo=new ComprobanteSunat($this->conexion);
        $filtro=asesconFiltroClientesSql($this->conexion,'c');
        $clientesPaginados=$clienteFiltro===0?$modelo->clientesConComprobantes($filtro):[];
        $totalPaginas=$clienteFiltro===0?max(1,count($clientesPaginados)):1;
        $paginaActual=max(1,min($pagina,$totalPaginas));
        $clientePagina=$clienteFiltro===0?($clientesPaginados[$paginaActual-1]??null):null;
        $clienteListado=$clienteFiltro>0?$clienteFiltro:(int)($clientePagina['id_cliente']??0);
        $filas=$clienteListado>0?$modelo->listar($filtro,$clienteListado):[];
        $clientes = [];
        $r = asesconClientesVisibles($this->conexion, true);
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $clientes[] = $x;
            }
        }
        return compact('filas','clientes','clientePagina','paginaActual','totalPaginas');
    }

    public function descargarIndividual(array $p): never
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_comprobantes')) {
            asesconRedirigir('vistas/sunat/comprobantes_sunat.php', ['error' => 'csrf']);
        }
        asesconExigirPermiso($this->conexion, 'COMPROBANTES_SUNAT', 'ejecutar');
        $id = (int)($p['id_cliente'] ?? 0);
        if ($id <= 0 || !asesconPuedeVerCliente($this->conexion, $id)) {
            asesconRedirigir('vistas/sunat/comprobantes_sunat.php', ['error' => 'cliente']);
        }
        require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
        require_once __DIR__.'/../../modelos/sunat/ArchivoComprobante.php';
        try {
            $k=ClaveComprobante::normalizar($p+['numero'=>$p['numero_documento']??'']);
            $archivo=strtoupper((string)($p['tipo_archivo']??'PDF'));
            $col=match($archivo){'PDF'=>'archivo_pdf','XML'=>'archivo_xml','CDR'=>'archivo_cdr',default=>throw new DomainException('Tipo no válido.')};
            $d=(new ArchivoComprobante($this->conexion))->obtener($k);
            $ref=(string)($d[$col]??'');
            if($ref==='')$ref=(string)((new ComprobanteSunat($this->conexion))->buscarArchivo($id,$k['ruc_emisor'],$k['tipo_documento'],$k['serie'],$k['numero'],$archivo)['ruta']??'');
            $servicio=new ServicioArchivosPrivados();$ruta=$servicio->resolver($ref,'COMPROBANTES');
            if(!$ruta)asesconRedirigir('vistas/sunat/comprobantes_sunat.php',['descarga'=>'no_disponible']);
            $servicio->servir($ruta,true,'comprobante_'.$k['serie'].'_'.$k['numero'].'.'.($archivo==='CDR'?'zip':strtolower($archivo)));
        } catch(DomainException) { asesconRedirigir('vistas/sunat/comprobantes_sunat.php',['error'=>'datos_descarga']); }

    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    $c = new ControladorComprobanteSunat($conexion);
    $accion = (string)($_POST['accion'] ?? '');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'descargar_individual') {
        $c->descargarIndividual($_POST);
    }
asesconRedirigir('vistas/sunat/comprobantes_sunat.php');
}
