<?php
declare(strict_types=1);
require_once __DIR__.'/../../../servicios/sunat/declaraciones_pagos/SesionConsultaSol.php';
require_once __DIR__.'/../../../servicios/sunat/declaraciones_pagos/ConsultaDeclaracionesSunat.php';
require_once __DIR__.'/../../../servicios/sunat/declaraciones_pagos/ArchivoConsultaOficial.php';
require_once __DIR__.'/../../../servicios/sunat/CargadorCredencialesSunat.php';
require_once __DIR__.'/../../../modelos/sunat/declaraciones_pagos/RepositorioDeclaracionesOficiales.php';

final class ControladorConsultaDeclaraciones
{
    private RepositorioDeclaracionesOficiales $repo;
    public function __construct(private mysqli $db,?mysqli $historico=null) {$this->repo=new RepositorioDeclaracionesOficiales($db,$historico);}
    public function datosVista(): array
    {
        asesconExigirPermiso($this->db,'DECLARACIONES_PAGOS_SUNAT','ver');
        return [
            'clientesOficiales'=>$this->repo->clientes(asesconFiltroClientesSql($this->db,'c')),
            'puedeConsultarOficial'=>asesconTienePermiso($this->db,'DECLARACIONES_PAGOS_SUNAT','ejecutar'),
            'historialOficialDisponible'=>$this->repo->disponible(),
        ];
    }
    private function servicio(int $cliente): ConsultaDeclaracionesSunat
    {
        $c=(new CargadorCredencialesSunat($this->db))->obtenerSolPorCliente($cliente);
        $firma=hash('sha256', $c['ruc']."\0".$c['usuario_sol']."\0".$c['clave_sol']);
        $cache=$_SESSION['consulta_oficial_sol'][$cliente]??[];
        if (($cache['firma']??'')!==$firma || (int)($cache['vence']??0)<=time()) {
            unset($_SESSION['consulta_oficial_sol'][$cliente]);
            $http=new TransporteConsultaSol();
            $respuesta=$http->solicitar('https://www.sunat.gob.pe/p/js/apps/main.js');
            if ($respuesta['status']!==200) throw new DomainException('No se pudo leer la entrada oficial de SUNAT.');
            $cache=SesionConsultaSol::iniciar($c,$http,$respuesta['body']);$cache['firma']=$firma;
            $_SESSION['consulta_oficial_sol'][$cliente]=$cache;
        } else {$http=new TransporteConsultaSol($cache['cookies']);}
        SesionConsultaSol::comprobarToken($cache['token'],$c['ruc']);
        return new ConsultaDeclaracionesSunat($http,$cache['token'],$c['ruc']);
    }
    private function documento(int $cliente,array $fila,string $tipo): array
    {
        if (!in_array($tipo,['constancia','casillas','anexos'],true)) throw new DomainException('Tipo de documento no válido.');
        $extension=$tipo==='anexos'?'zip':'pdf';
        $identidad=[$fila['numRuc'],$fila['codFor'],$fila['numOrd'],$tipo,$fila['ecmFormulario']??'',$fila['ecmCasillas']??''];
        $nombre=$tipo.'_'.$fila['codFor'].'_'.$fila['numOrd'].'.'.$extension;
        $archivos=new ArchivoConsultaOficial();
        $guardado=$archivos->obtener($cliente,$identidad,$extension,$nombre);
        if ($guardado!==null) return $guardado;
        $archivo=$this->servicio($cliente)->documento($fila,$tipo);
        $archivos->guardar($cliente,$identidad,$archivo);return $archivo;
    }
    public function ejecutar(array $p): array
    {
        $cliente=filter_var($p['id_cliente']??0,FILTER_VALIDATE_INT);
        if (!$cliente || !asesconPuedeGestionarCliente($this->db,$cliente)) throw new DomainException('No tienes acceso a esa empresa.');
        $accion=(string)($p['accion']??'');
        if ($accion==='historial'){if (!$this->repo->disponible()) throw new DomainException('El historial oficial todavía no está preparado en el servidor. Solicita completar la actualización del sistema.');return ['consulta'=>$this->repo->ultima($cliente)];}
        if (!in_array($accion,['consultar','reporte','detalle','detalle_guardado','documento','correo'],true)) throw new DomainException('Acción de consulta no válida.');
        if ($accion!=='detalle_guardado') asesconExigirPermiso($this->db,'DECLARACIONES_PAGOS_SUNAT','ejecutar');
        if (!$this->repo->disponible()) throw new DomainException('El historial oficial todavía no está preparado en el servidor. Solicita completar la actualización del sistema.');
        if ($accion==='consultar') {
            $inicio=(string)($p['inicio']??'');$fin=(string)($p['fin']??'');
            ConsultaDeclaracionesSunat::validarFechas($inicio,$fin);
            $consulta=$this->servicio($cliente)->consultar($inicio,$fin);
            $this->repo->guardar($cliente,$consulta);
            asesconRegistrarAuditoria($this->db,'CONSULTAR_HISTORIAL_SOL','DECLARACIONES_PAGOS_SUNAT',$cliente,'declaraciones_oficiales_sunat',null,'Consulta oficial: '.count($consulta['filas']).' registros.');
            return ['consulta'=>$consulta];
        }
        if ($accion==='reporte') {
            $consulta=$this->repo->ultima($cliente);
            if (!$consulta || !$consulta['filas']) throw new DomainException('Consulta primero un rango con resultados.');
            return ['archivo'=>$this->servicio($cliente)->reporte($consulta,(string)($p['tipo']??''))];
        }
        $form=(string)($p['formulario']??'');$orden=(string)($p['numero_orden']??'');
        $r=$this->repo->obtener($cliente,$form,$orden);
        if (!$r) throw new DomainException('La declaración no está vinculada a esta empresa. Consulta primero su historial.');
        if ($accion==='detalle_guardado') return ['detalle'=>$r['detalle']];
        if ($accion==='detalle') {
            $d=$this->servicio($cliente)->detalle($orden);$this->repo->guardarDetalle($cliente,$form,$orden,$d);return ['detalle'=>$d];
        }
        if ($accion==='correo') {
            require_once __DIR__.'/../../../servicios/sunat/declaraciones_pagos/EnvioConstanciaDeclaracion.php';
            if (($p['confirmado']??'')!=='1') throw new DomainException('Confirma el destinatario antes de enviar.');
            $email=trim((string)($p['destinatario']??''));
            if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Escribe un correo válido.');
            $tipoCorreo=(string)($p['tipo_documento']??'constancia');
            if (!in_array($tipoCorreo,['constancia','casillas'],true)) throw new DomainException('Selecciona una constancia PDF o el PDF de casillas.');
            $claveEnvio=hash('sha256',$cliente.'|'.$form.'|'.$orden.'|'.$tipoCorreo.'|'.strtolower($email));
            if (($_SESSION['envios_constancias'][$claveEnvio]??0)>time()-60) throw new DomainException('Esta constancia ya se envió a ese destinatario hace menos de un minuto.');
            $archivo=$this->documento($cliente,$r['fila'],$tipoCorreo);
            (new EnvioConstanciaDeclaracion($this->db))->enviar($email,$archivo,$r['fila'],$tipoCorreo);
            $_SESSION['envios_constancias'][$claveEnvio]=time();
            asesconRegistrarAuditoria($this->db,'ENVIAR_CONSTANCIA','DECLARACIONES_PAGOS_SUNAT',$cliente,'declaraciones_oficiales_sunat',$orden,'Envío SMTP solicitado por el operador.');
            return ['mensaje'=>'El servidor SMTP aceptó el envío del PDF oficial.'];
        }
        if ($accion==='documento') return ['archivo'=>$this->documento($cliente,$r['fila'],(string)($p['tipo']??''))];
        throw new DomainException('Acción de consulta no válida.');
    }
}
