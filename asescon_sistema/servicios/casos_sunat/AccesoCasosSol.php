<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/conexiones_bases_datos.php';
require_once __DIR__.'/../../modelos/casos_sunat/RepositorioCasosSol.php';
final class AccesoCasosSol
{
    public static function repositorio():?RepositorioCasosSol
    {
        static $repo=null;
        if($repo===null){$repo=new RepositorioCasosSol(asesconConexionPrincipal());$repo->instalar();}
        return $repo;
    }
    public static function filtro(mysqli $db,string $cartera,string $alias='b'):string
    {
        if(asesconEsPrincipal($db))return $cartera;
        $repo=self::repositorio();if(!$repo||!$repo->disponible())return $cartera;
        $actor=(int)asesconUsuarioActual($db)['id_usuario'];
        return self::condicion($cartera,$repo->alcance($actor,true),$repo->alcance($actor),$alias);
    }
    public static function condicion(string $cartera,array $todos,array $permitidos,string $alias='b'):string
    {
        $ids=array_values(array_filter(array_map(static fn(array $c):int=>(int)$c['id_documento'],$todos),static fn(int $n):bool=>$n>0));
        $base=$ids?'(('.$cartera.') AND '.$alias.'.id_documento NOT IN('.implode(',',$ids).'))':'('.$cartera.')';
        $concedidos=[];foreach($permitidos as $c){$id=(int)$c['id_documento'];$cliente=(int)$c['id_cliente'];if($id>0&&$cliente>0)$concedidos[]='('.$alias.'.id_documento='.$id.' AND '.$alias.'.id_cliente='.$cliente.')';}
        return $concedidos?'('.$base.' OR '.implode(' OR ',$concedidos).')':$base;
    }
    public static function puedeAnotar(mysqli $db,int $documento):bool
    {
        if(!asesconTienePermiso($db,'BUZON_SOL','ver'))return false;
        if(asesconEsPrincipal($db))return asesconTienePermiso($db,'BUZON_SOL','editar');
        $actor=(int)(asesconUsuarioActual($db)['id_usuario']??0);
        if($actor<1)return false;
        $repo=self::repositorio();$caso=$repo?->caso($documento);
        if(!$caso||($caso['estado']??'ABIERTO')==='CERRADO')return false;
        if((int)($caso['id_responsable']??0)===$actor)return true;
        return in_array($actor,$repo?->accesos($documento)??[],true);
    }
}
