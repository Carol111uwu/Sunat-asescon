<?php
declare(strict_types=1);

final class RepositorioDocumentoBuzon
{
    public function __construct(private mysqli $conexion){}
    public static function tabla(string $buzon):string{return match($buzon){'SOL'=>'buzon_sol_sunat','SUNAFIL'=>'buzon_sunafil',default=>throw new DomainException('Buzón no válido.')};}
    public static function permiso(string $buzon):string{return $buzon==='SOL'?'BUZON_SOL':($buzon==='SUNAFIL'?'BUZON_SUNAFIL':throw new DomainException('Buzón no válido.'));}
    public function obtener(string $buzon,int $id,string $filtro):?array
    {
        $tabla=self::tabla($buzon);$general='';
        $s=$this->conexion->prepare("SELECT b.*,c.ruc,c.razon_social,c.correo AS correo_cliente,c.celular AS celular_cliente FROM {$tabla} b LEFT JOIN clientes c ON c.id_cliente=b.id_cliente WHERE b.id_documento=? AND ({$general}{$filtro}) LIMIT 1");
        $s->bind_param('i',$id);$s->execute();$d=$s->get_result()->fetch_assoc();$s->close();return $d;
    }
    public function vincular(string $buzon,int $id,string $ruta,string $filtro):void
    {
        $tabla=self::tabla($buzon);$s=$this->conexion->prepare("UPDATE {$tabla} b INNER JOIN clientes c ON c.id_cliente=b.id_cliente SET b.archivo=? WHERE b.id_documento=? AND ({$filtro})");
        $s->bind_param('si',$ruta,$id);$s->execute();$s->close();
    }
    public function estado(string $buzon,int $id,string $estado,string $filtro):void
    {
        if(!in_array($estado,['NO_REVISADO','REVISADO'],true))throw new DomainException('Estado no válido.');
        $tabla=self::tabla($buzon);$general='';
        $s=$this->conexion->prepare("UPDATE {$tabla} b LEFT JOIN clientes c ON c.id_cliente=b.id_cliente SET b.estado=? WHERE b.id_documento=? AND ({$general}{$filtro})");
        $s->bind_param('si',$estado,$id);$s->execute();$s->close();
    }
}
