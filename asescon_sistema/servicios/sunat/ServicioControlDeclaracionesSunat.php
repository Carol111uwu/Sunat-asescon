<?php
declare(strict_types=1);

require_once __DIR__.'/CargadorCredencialesSunat.php';
require_once __DIR__.'/declaraciones_pagos/TransporteConsultaSol.php';
require_once __DIR__.'/declaraciones_pagos/SesionConsultaSol.php';
require_once __DIR__.'/declaraciones_pagos/ConsultaDeclaracionesSunat.php';
require_once __DIR__.'/PeriodosSire.php';

   
                                                                  
                                                                           
                                                                                                
   
final class ServicioControlDeclaracionesSunat
{
    public function __construct(private mysqli $db) {}

    private function cliente(int $id): array
    {
        if ($id <= 0 || !asesconPuedeGestionarCliente($this->db, $id)) {
            throw new DomainException('No tienes acceso a este cliente.');
        }
        return (new CargadorCredencialesSunat($this->db))->obtenerSolPorCliente($id);
    }

    private function consulta(array $credencial): ConsultaDeclaracionesSunat
    {
        $id=(int)$credencial['id_cliente'];
        $firma=hash('sha256',$credencial['ruc']."\0".$credencial['usuario_sol']."\0".$credencial['clave_sol']);
        $cache=$_SESSION['control_declaraciones_sol'][$id]??[];
        if (($cache['firma']??'')!==$firma || (int)($cache['vence']??0)<=time()) {
            unset($_SESSION['control_declaraciones_sol'][$id]);
            $http=new TransporteConsultaSol();
            $entrada=$http->solicitar('https://www.sunat.gob.pe/p/js/apps/main.js');
            if ((int)$entrada['status']!==200) {
                throw new DomainException('SUNAT no permitió cargar el acceso SOL. No se modificó el control.');
            }
            $cache=SesionConsultaSol::iniciar($credencial,$http,(string)$entrada['body']);
            $cache['firma']=$firma;
            $_SESSION['control_declaraciones_sol'][$id]=$cache;
        } else {
            $http=new TransporteConsultaSol((array)($cache['cookies']??[]));
        }
        SesionConsultaSol::comprobarToken((string)$cache['token'],(string)$credencial['ruc']);
        return new ConsultaDeclaracionesSunat($http,(string)$cache['token'],(string)$credencial['ruc']);
    }

    private function ventanaPresentacion(string $periodo): array
    {
        if (!preg_match('/^20\d{2}(0[1-9]|1[0-2])$/D',$periodo)) {
            throw new DomainException('Selecciona un período mensual para sincronizar. La vista anual no se sincroniza directamente.');
        }
        $zona=new DateTimeZone('America/Lima');
        $tributario=DateTimeImmutable::createFromFormat('!Ym',$periodo,$zona);
        if (!$tributario || $tributario->format('Ym')!==$periodo) throw new DomainException('Período tributario inválido.');
                                                                                        
                                                                                 
        $mesActual=(new DateTimeImmutable('now',$zona))->format('Ym');
        $inicio=$tributario->modify($periodo===$mesActual?'first day of this month':'first day of next month')->setTime(0,0);
        $fin=$inicio->modify('+6 months')->modify('-1 day')->setTime(23,59,59);
        $hoy=(new DateTimeImmutable('now',$zona))->setTime(23,59,59);
        if ($inicio>$hoy) throw new DomainException('El período todavía no inicia su ventana de presentación. No hay información que sincronizar en SUNAT.');
        if ($fin>$hoy) $fin=$hoy;
        return [$inicio->format('Y-m-d'),$fin->format('Y-m-d')];
    }

    public function sincronizar(int $idCliente,string $periodo): array
    {
        if (!PeriodosSire::permitido($periodo,false)) throw new DomainException('Período fuera del rango permitido.');
        $credencial=$this->cliente($idCliente);
        [$inicio,$fin]=$this->ventanaPresentacion($periodo);
        $consulta=$this->consulta($credencial)->consultar($inicio,$fin);
        $coincidentes=[];
        foreach ($consulta['filas'] as $fila) {
            if ((string)($fila['perTri']??'')!==$periodo) continue;
            $form=(string)($fila['codFor']??'');
            $fecha=DateTimeImmutable::createFromFormat('!d/m/Y',(string)($fila['strFecPres']??''));
            if (!preg_match('/^\d{4}$/D',$form) || !$fecha) continue;
            $clave=$form;
            $fechaIso=$fecha->format('Y-m-d');
                                                                                                  
            if (!isset($coincidentes[$clave]) || $fechaIso>$coincidentes[$clave]['fecha']) {
                $coincidentes[$clave]=['formulario'=>$form,'orden'=>(string)$fila['numOrd'],'fecha'=>$fechaIso];
            }
        }
        $resultado=(new DeclaracionSunat($this->db))->aplicarResultadosOficiales($idCliente,$periodo,array_values($coincidentes));
        return $resultado+['oficiales'=>count($coincidentes),'filas'=>array_values($coincidentes),
            'inicio'=>$inicio,'fin'=>$fin,'ruc'=>$credencial['ruc']];
    }
}
