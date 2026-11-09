<?php

declare(strict_types=1);
require_once __DIR__.'/../../servicios/sunat/ClaveComprobante.php';

final class ComprobanteSunat
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(string $filtro, int $idCliente = 0): array
    {
        $extra = $idCliente > 0 ? ' AND s.id_cliente=' . (int)$idCliente : '';
        $limiteLocal=$idCliente>0?'':' LIMIT 500';
        $r = $this->conexion->query("SELECT s.*,c.ruc,c.razon_social FROM comprobantes_sunat s INNER JOIN clientes c ON c.id_cliente=s.id_cliente WHERE {$filtro}{$extra} ORDER BY s.fecha_emision DESC,s.id_comprobante_sunat DESC{$limiteLocal}");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
        $unicas=[];
        foreach($a as $fila){try{$k=ClaveComprobante::normalizar(['id_cliente'=>$fila['id_cliente'],'ruc_emisor'=>$fila['ruc_emisor'],'tipo_documento'=>$fila['tipo_comprobante'],'serie'=>$fila['serie'],'numero'=>$fila['numero']]);$unicas[ClaveComprobante::identificador($k)]=$fila;}catch(DomainException){$unicas['LOCAL-'.$fila['id_comprobante_sunat']]=$fila;}}
                                                                           
        $limiteRce=$idCliente>0?'':' LIMIT 2000';
        $rce=$this->conexion->query("SELECT d.*,h.id_cliente,h.periodo,c.ruc,c.razon_social FROM rce_detalle d INNER JOIN rce h ON h.id_rce=d.id_rce INNER JOIN clientes c ON c.id_cliente=h.id_cliente WHERE {$filtro}".($idCliente>0?' AND h.id_cliente='.(int)$idCliente:'')." ORDER BY h.periodo DESC,d.fecha_emision DESC{$limiteRce}");
        if($rce)while($fila=$rce->fetch_assoc()){
            try{$k=ClaveComprobante::normalizar(['id_cliente'=>$fila['id_cliente'],'ruc_emisor'=>$fila['ruc_proveedor'],'tipo_documento'=>$fila['tipo_comprobante'],'serie'=>$fila['serie'],'numero'=>$fila['numero']]);}catch(DomainException){continue;}
            $clave=ClaveComprobante::identificador($k);if(isset($unicas[$clave]))continue;
            $unicas[$clave]=array_merge($fila,['id_comprobante_sunat'=>null,'ruc_emisor'=>$fila['ruc_proveedor'],'razon_social_emisor'=>$fila['razon_social_proveedor'],'moneda'=>'','subtotal'=>$fila['base_imponible'],'total'=>$fila['importe_total'],'estado'=>$fila['estado_registro']?:'IMPORTADO RCE','origen'=>'RCE '.$fila['periodo']]);
        }
        return array_values($unicas);
    }

                                                                           
    public function clientesConComprobantes(string $filtro):array
    {
        $sql="SELECT c.id_cliente,c.ruc,c.razon_social FROM clientes c WHERE ({$filtro})
                AND (EXISTS(SELECT 1 FROM comprobantes_sunat s WHERE s.id_cliente=c.id_cliente)
                  OR EXISTS(SELECT 1 FROM rce h INNER JOIN rce_detalle d ON d.id_rce=h.id_rce
                            WHERE h.id_cliente=c.id_cliente))
                ORDER BY c.razon_social,c.ruc,c.id_cliente";
        $r=$this->conexion->query($sql);
        $filas=[];
        if($r)while($fila=$r->fetch_assoc())$filas[]=$fila;
        return $filas;
    }

    public function existeParaCliente(array $clave):bool
    {
        $k=ClaveComprobante::normalizar($clave);
        $sql="SELECT 1 existe FROM comprobantes_sunat s WHERE s.id_cliente=? AND s.ruc_emisor=? AND LPAD(TRIM(s.tipo_comprobante),2,'0')=? AND UPPER(TRIM(s.serie))=? AND COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(s.numero)),''),'0')=? UNION ALL SELECT 1 existe FROM rce_detalle d INNER JOIN rce h ON h.id_rce=d.id_rce WHERE h.id_cliente=? AND d.ruc_proveedor=? AND LPAD(TRIM(d.tipo_comprobante),2,'0')=? AND UPPER(TRIM(d.serie))=? AND COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(d.numero)),''),'0')=? LIMIT 1";
        $s=$this->conexion->prepare($sql);$s->bind_param('issssissss',$k['id_cliente'],$k['ruc_emisor'],$k['tipo_documento'],$k['serie'],$k['numero'],$k['id_cliente'],$k['ruc_emisor'],$k['tipo_documento'],$k['serie'],$k['numero']);$s->execute();$existe=$s->get_result()->fetch_assoc()!==null;$s->close();return $existe;
    }

    public function crear(array $d): int
    {
        $s = $this->conexion->prepare("INSERT INTO comprobantes_sunat(id_cliente,tipo_comprobante,serie,numero,fecha_emision,ruc_emisor,razon_social_emisor,ruc_receptor,razon_social_receptor,moneda,subtotal,igv,total,estado) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        if (!$s) {
            throw new RuntimeException('No se pudo registrar el comprobante.');
        }
        $s->bind_param('isssssssssddds', $d['id_cliente'], $d['tipo_comprobante'], $d['serie'], $d['numero'], $d['fecha_emision'], $d['ruc_emisor'], $d['razon_social_emisor'], $d['ruc_receptor'], $d['razon_social_receptor'], $d['moneda'], $d['subtotal'], $d['igv'], $d['total'], $d['estado']);
        if (!$s->execute()) {
            $e = $s->error;
            $s->close();
            throw new RuntimeException($e);
        }
        $id = (int)$this->conexion->insert_id;
        $s->close();
        return $id;
    }

    public function buscarArchivo(int $idCliente, string $rucEmisor, string $tipo, string $serie, string $numero, string $archivo): ?array
    {
        $col = match ($archivo) {
            'XML'=>'archivo_xml','CDR'=>'archivo_cdr',default=>'archivo_pdf'
        };
        $stmt = $this->conexion->prepare("SELECT id_comprobante_sunat,{$col} AS ruta FROM comprobantes_sunat WHERE id_cliente=? AND COALESCE(ruc_emisor,'')=? AND LPAD(TRIM(tipo_comprobante),2,'0')=? AND UPPER(TRIM(serie))=? AND COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(numero)),''),'0')=? LIMIT 1");
        if (!$stmt) {
            return null;
        }
$stmt->bind_param('issss', $idCliente, $rucEmisor, $tipo, $serie, $numero);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $r;
    }
}
