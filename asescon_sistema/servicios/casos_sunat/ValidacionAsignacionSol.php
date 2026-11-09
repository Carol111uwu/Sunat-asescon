<?php
declare(strict_types=1);
final class ValidacionAsignacionSol
{
    public static function preparar(array $p,array $equipo):array
    {
        $usuarios=[];foreach($equipo as $u)$usuarios[(int)$u['id_usuario']]=$u;
        $responsable=(int)($p['id_responsable']??0);
        if(!isset($usuarios[$responsable]))throw new DomainException('Selecciona un integrante activo con permiso para Buzón SOL.');
        $correo=trim((string)($usuarios[$responsable]['correo']??''));
        if($correo!==''&&!filter_var($correo,FILTER_VALIDATE_EMAIL))throw new DomainException('El correo del responsable registrado en Mi equipo no tiene un formato válido.');
        $prioridad=(string)($p['prioridad']??'NORMAL');if(!in_array($prioridad,['NORMAL','ALTA','URGENTE'],true))throw new DomainException('La prioridad no es válida.');
        $fecha=(string)($p['fecha_limite']??'');$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
        if(!$dt||$dt->format('Y-m-d')!==$fecha)throw new DomainException('Selecciona una fecha límite válida.');
        $observaciones=trim((string)($p['observaciones']??''));if(strlen($observaciones)>20000||preg_match('//u',$observaciones)!==1)throw new DomainException('Las observaciones son demasiado largas o contienen texto inválido.');
        $compartidos=array_values(array_unique(array_filter(array_map('intval',is_array($p['compartidos']??null)?$p['compartidos']:[]),fn(int $n):bool=>$n!==$responsable)));
        foreach($compartidos as $id)if(!isset($usuarios[$id]))throw new DomainException('Una persona seleccionada ya no tiene permiso para ver Buzón SOL.');
        return ['responsable'=>$usuarios[$responsable],'compartidos'=>$compartidos,'parametros'=>['prioridad'=>$prioridad,'fecha_limite'=>$fecha,'observaciones'=>$observaciones,'version'=>(int)($p['version']??0)]];
    }
}
