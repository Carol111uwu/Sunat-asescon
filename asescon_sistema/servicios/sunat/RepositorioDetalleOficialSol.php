<?php
declare(strict_types=1);

                                                                      
final class RepositorioDetalleOficialSol
{
    public function __construct(private mysqli $conexion){}

    public function disponible():bool
    {
        $s=$this->conexion->query("SHOW TABLES LIKE 'buzon_sol_detalles_oficiales'");
        return $s!==false && $s->num_rows>0;
    }

    public function guardar(int $idCliente,array $detalle):void
    {
        if(!$this->disponible())throw new RuntimeException('Falta importar la migración de contenido oficial del Buzón SOL.');
        $id=(int)($detalle['id_documento']??0);
        $contenido=trim((string)($detalle['contenido']??''));
        if($id<1||strlen($contenido)<65||strlen($contenido)>120000)throw new RuntimeException('El contenido oficial no tiene un formato verificable.');
        $s=$this->conexion->prepare('SELECT id_documento FROM buzon_sol_sunat WHERE id_documento=? AND id_cliente=? LIMIT 1');
        $s->bind_param('ii',$id,$idCliente);$s->execute();$existe=$s->get_result()->num_rows===1;$s->close();
        if(!$existe)throw new RuntimeException('El contenido no corresponde a la empresa solicitada.');
        $archivos=[];
        foreach(($detalle['archivos']??[]) as $nombre){
            if(!is_string($nombre))continue;
            $nombre=trim($nombre);
            if($nombre!=='' && strlen($nombre)<=720 && !in_array($nombre,$archivos,true))$archivos[]=$nombre;
            if(count($archivos)>=25)break;
        }
        $json=json_encode($archivos,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        if($json===false)throw new RuntimeException('No se pudo registrar la lista de documentos.');
        $s=$this->conexion->prepare('INSERT INTO buzon_sol_detalles_oficiales(id_documento,contenido,archivos_json,obtenido_en) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE contenido=VALUES(contenido),archivos_json=VALUES(archivos_json),obtenido_en=NOW()');
        $s->bind_param('iss',$id,$contenido,$json);$s->execute();$s->close();
    }

    public function obtener(int $id):?array
    {
        if($id<1||!$this->disponible())return null;
        $s=$this->conexion->prepare('SELECT contenido,archivos_json,obtenido_en FROM buzon_sol_detalles_oficiales WHERE id_documento=? LIMIT 1');
        $s->bind_param('i',$id);$s->execute();$fila=$s->get_result()->fetch_assoc();$s->close();
        if(!$fila)return null;
        $archivos=json_decode((string)($fila['archivos_json']??'[]'),true);
        return ['contenido'=>(string)$fila['contenido'],'archivos'=>is_array($archivos)?$archivos:[],'obtenido_en'=>(string)$fila['obtenido_en']];
    }
}
