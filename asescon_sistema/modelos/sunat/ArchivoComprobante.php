<?php
declare(strict_types=1);
require_once __DIR__.'/../../servicios/sunat/ClaveComprobante.php';
final class ArchivoComprobante
{
    public function __construct(private mysqli $conexion){}
    public function disponible():bool{return (int)$this->conexion->query("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('archivos_comprobantes','cuentas_contables_sire')")->fetch_assoc()['n']===2;}
    public function obtener(array $k):array
    {
        if(!$this->disponible())return [];$k=ClaveComprobante::normalizar($k);
        $s=$this->conexion->prepare('SELECT * FROM archivos_comprobantes WHERE id_cliente=? AND ruc_emisor=? AND tipo_documento=? AND serie=? AND numero=?');$s->bind_param('issss',$k['id_cliente'],$k['ruc_emisor'],$k['tipo_documento'],$k['serie'],$k['numero']);$s->execute();$d=$s->get_result()->fetch_assoc()?:[];$s->close();return $d;
    }
    public function guardar(array $k,string $tipo,string $ruta,string $cuenta='',string $centro=''):void
    {
        if(!$this->disponible())throw new DomainException('La cuenta principal debe preparar los archivos y cuentas desde Configuración de correo.');
        $k=ClaveComprobante::normalizar($k);$col=match($tipo){'PDF'=>'archivo_pdf','XML'=>'archivo_xml','CDR'=>'archivo_cdr',default=>throw new DomainException('Tipo de archivo no válido.')};
        $s=$this->conexion->prepare("INSERT INTO archivos_comprobantes(id_cliente,ruc_emisor,tipo_documento,serie,numero,{$col},cuenta_contable,centro_costos) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE {$col}=COALESCE(NULLIF(VALUES({$col}),''),{$col}),cuenta_contable=VALUES(cuenta_contable),centro_costos=VALUES(centro_costos),actualizado_en=NOW()");
        $s->bind_param('isssssss',$k['id_cliente'],$k['ruc_emisor'],$k['tipo_documento'],$k['serie'],$k['numero'],$ruta,$cuenta,$centro);$s->execute();$s->close();
    }
    public function cuentas(int $cliente):array
    {
        if(!$this->disponible())return [];$s=$this->conexion->prepare('SELECT codigo,nombre FROM cuentas_contables_sire WHERE id_cliente=? ORDER BY codigo');$s->bind_param('i',$cliente);$s->execute();$d=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $d;
    }
    public function agregarCuenta(int $cliente,string $codigo,string $nombre):void
    {
        if(!preg_match('/^[A-Za-z0-9.-]{1,30}$/D',$codigo)||$nombre===''||preg_match_all('/./us',$nombre)>200)throw new DomainException('Revisa el código y nombre de la cuenta.');
        $s=$this->conexion->prepare('INSERT INTO cuentas_contables_sire(id_cliente,codigo,nombre) VALUES(?,?,?) ON DUPLICATE KEY UPDATE nombre=VALUES(nombre)');$s->bind_param('iss',$cliente,$codigo,$nombre);$s->execute();$s->close();
    }
}
