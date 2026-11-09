<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/conexiones_bases_datos.php';
final class EstadoBasesDatos
{
    public function consultar(): array
    {
        $estados=[];
        foreach(['principal'=>'Principal: clientes, equipo, casos y notas','historico_sunat'=>'Histórico: declaraciones, pagos y detracciones'] as $perfil=>$titulo){
            try{
                $db=asesconConexionBase($perfil);$r=$db->query('SELECT DATABASE() base');$base=(string)$r->fetch_assoc()['base'];
                $tablas=$perfil==='principal'?['clientes','usuarios','equipo','buzon_sol_sunat','archivos_comprobantes','cuentas_contables_sire','pagos_tributarios']:['sunat_declaraciones','sunat_pagos','sunat_detracciones','sunat_historial_consultas','sunat_historial_cambios','sunat_configuracion_modulo','sunat_consultas_respuestas','sunat_detalles_declaracion'];
                $faltan=[];foreach($tablas as $tabla)if(!$db->query("SHOW TABLES LIKE '{$tabla}'")->num_rows)$faltan[]=$tabla;
                $estados[]=['titulo'=>$titulo,'base'=>$base,'ok'=>!$faltan,'mensaje'=>$faltan?'Faltan tablas: '.implode(', ',$faltan):'Conexión correcta y tablas disponibles.'];
            }catch(Throwable $e){error_log('Estado MySQL '.$perfil.': '.$e->getMessage());$estados[]=['titulo'=>$titulo,'base'=>'','ok'=>false,'mensaje'=>'No se pudo conectar. Revisa secretos.php y que el usuario MySQL tenga permisos en esta base.'];}
        }
        return $estados;
    }
}
