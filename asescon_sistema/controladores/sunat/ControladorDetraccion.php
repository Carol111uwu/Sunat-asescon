<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/RepositorioDetraccionesSpot.php';
require_once __DIR__ . '/../../servicios/sunat/ServicioImportacionDetraccionesSpot.php';
require_once __DIR__ . '/../../servicios/sunat/PeriodosSire.php';
final class ControladorDetraccion
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function clientes(): array
    {
        asesconExigirPermiso($this->conexion, 'DETRACCIONES', 'ver');
        $clientes = [];
        $r = asesconClientesVisibles($this->conexion, true);
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $clientes[] = $x;
            }
        }
        usort($clientes,static fn($a,$b)=>strnatcasecmp((string)$a['razon_social'],(string)$b['razon_social']));
        return $clientes;
    }

    public function constancias(array $ids, string $periodo): array
    {
        $repo = new RepositorioDetraccionesSpot($this->conexion);
        return ['habilitado'=>$repo->disponible(),
            'filas'=>$repo->listar($ids, $periodo)];
    }

                                                                                    
    public function candidatosRce(int $idCliente, string $periodo): array
    {
        if ($idCliente <= 0 || !asesconPuedeGestionarCliente($this->conexion,$idCliente)) return [];
        if (!PeriodosSire::permitido($periodo)) return [];
        $periodoCondicion = str_ends_with($periodo,'00') ? 'r.periodo LIKE ?' : 'r.periodo=?';
        $valor = str_ends_with($periodo,'00') ? substr($periodo,0,4).'%' : $periodo;
        $s=$this->conexion->prepare("SELECT r.periodo,d.fecha_emision,d.tipo_comprobante,d.serie,d.numero,d.ruc_proveedor,d.razon_social_proveedor,d.importe_total FROM rce_detalle d INNER JOIN rce r ON r.id_rce=d.id_rce WHERE r.id_cliente=? AND r.estado='PROCESADO' AND {$periodoCondicion} ORDER BY d.fecha_emision DESC,d.id_rce_detalle DESC LIMIT 501");
        if (!$s) return [];
        $s->bind_param('is',$idCliente,$valor);$s->execute();
        $res=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
        return $res;
    }

    private function mensaje(string $tipo, string $texto, int $cliente=0, string $periodo=''): never
    {
        $_SESSION['spot_aviso']=['tipo'=>$tipo,'texto'=>$texto];
        $params=[];
        if ($cliente>0) $params['cliente']=$cliente;
        if (PeriodosSire::permitido($periodo)) {$params['anio']=substr($periodo,0,4);$params['mes']=substr($periodo,4,2);}
        $params['seccion']='consultas';
        asesconRedirigir('vistas/sunat/detracciones.php',$params);
        exit;
    }

    public function procesar(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_detracciones')) {
            asesconRedirigir('vistas/sunat/detracciones.php', ['error' => 'csrf']);
        }
$a = (string)($p['accion'] ?? '');
        if ($a==='importar_sunat') {
            asesconExigirPermiso($this->conexion,'DETRACCIONES','crear');
            $id=(int)($p['id_cliente']??0);$periodoArchivo=(string)($p['periodo_archivo']??'');
            if (!asesconPuedeGestionarCliente($this->conexion,$id) || $id<=0) $this->mensaje('bad','Selecciona una empresa autorizada para importar.');
            try {
                $file=$_FILES['archivo_sunat']??null;
                if (!is_array($file) || (int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new DomainException('Selecciona un archivo exportado desde SUNAT.');
                if ((int)$file['size']<1 || (int)$file['size']>4*1024*1024 || !is_uploaded_file((string)$file['tmp_name'])) throw new DomainException('Archivo vacío, no válido o mayor de 4 MB.');
                $nombre=basename((string)$file['name']);
                if (!preg_match('/^[\pL\pN_.() -]{1,180}\.(csv|txt|xlsx)$/iu',$nombre)) throw new DomainException('Nombre de archivo o extensión no admitidos.');
                $s=$this->conexion->prepare('SELECT ruc FROM clientes WHERE id_cliente=? AND estado=\'ACTIVO\' LIMIT 1');
                $s->bind_param('i',$id);$s->execute();$c=$s->get_result()->fetch_assoc();$s->close();
                if (!$c) throw new DomainException('Empresa no disponible.');
                $filas=(new ServicioImportacionDetraccionesSpot())->analizar((string)$file['tmp_name'],$nombre,(string)$c['ruc'],$periodoArchivo);
                $n=(new RepositorioDetraccionesSpot($this->conexion))->guardar($id,(string)$c['ruc'],$filas,$nombre)['procesadas'];
                $periodoMostrar=$periodoArchivo!==''?$periodoArchivo:(string)($filas[0]['periodo']??'');
                $this->mensaje('ok',"Se procesaron {$n} constancias del archivo. Identificadas como IMPORTADAS; no se ha verificado en SUNAT en tiempo real.",$id,$periodoMostrar);
            } catch (Throwable $e) {
                error_log('Importación SPOT: '.get_class($e));
                $this->mensaje('bad',$e instanceof DomainException || $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo importar el archivo. Revisa el formato y la migración.',$id,$periodoArchivo);
            }
        }
        $this->mensaje('bad','El registro manual anterior fue retirado. Solo se admiten importaciones con procedencia identificable.');
    }
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    (new ControladorDetraccion($conexion))->procesar($_POST);
}
