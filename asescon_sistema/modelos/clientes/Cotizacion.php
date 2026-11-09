<?php

declare(strict_types=1);

final class Cotizacion
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(string $f): array
    {
        $sql = "SELECT q.*,c.ruc,c.razon_social,p.nombre AS prospecto_nombre,p.ruc AS prospecto_ruc,
                       COALESCE(NULLIF(p.nombre,''),NULLIF(c.razon_social,''),'Sin prospecto') AS destino
                FROM cotizaciones q
                LEFT JOIN clientes c ON c.id_cliente=q.id_cliente
                LEFT JOIN prospectos p ON p.id_prospecto=q.id_prospecto
                WHERE ({$f})
                ORDER BY q.fecha_emision DESC,q.id_cotizacion DESC";
        $r = $this->conexion->query($sql);
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }

    public function listarServicios(): array
    {
        $r = $this->conexion->query("SELECT * FROM servicios_cotizacion WHERE estado='ACTIVO' ORDER BY nombre,id_servicio");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }

    public function listarProspectos(): array
    {
        $r = $this->conexion->query("SELECT * FROM prospectos WHERE estado='ACTIVO' ORDER BY nombre,id_prospecto");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }

    public function crearProspecto(string $nombre, ?string $ruc, ?string $contacto, ?string $telefono, ?string $correo): int
    {
        $s = $this->conexion->prepare("INSERT INTO prospectos(nombre,ruc,contacto,telefono,correo,estado) VALUES(?,?,?,?,?,'ACTIVO')");
        if (!$s) {
            throw new RuntimeException('No se pudo preparar el prospecto.');
        }
        $s->bind_param('sssss', $nombre, $ruc, $contacto, $telefono, $correo);
        if (!$s->execute()) {
            $e = $s->error;
            $s->close();
            throw new RuntimeException($e);
        }
        $id = (int)$this->conexion->insert_id;
        $s->close();
        return $id;
    }

    public function crearServicio(string $nombre, ?string $descripcion, float $precio, string $moneda): int
    {
        $moneda = in_array($moneda, ['PEN','USD'], true) ? $moneda : 'PEN';
        $s = $this->conexion->prepare("INSERT INTO servicios_cotizacion(nombre,descripcion,precio_referencial,moneda,estado) VALUES(?,?,?,?,'ACTIVO')");
        if (!$s) {
            throw new RuntimeException('No se pudo preparar el servicio.');
        }
        $s->bind_param('ssds', $nombre, $descripcion, $precio, $moneda);
        if (!$s->execute()) {
            $e = $s->error;
            $s->close();
            throw new RuntimeException($e);
        }
        $id = (int)$this->conexion->insert_id;
        $s->close();
        return $id;
    }

    public function siguienteCodigo(): string
    {
        $anio = date('Y');
        $stmt = $this->conexion->prepare("SELECT COUNT(*) n FROM cotizaciones WHERE YEAR(fecha_emision)=?");
        if (!$stmt) {
            return 'COT-' . $anio . '-001';
        }
        $y = (int)$anio;
        $stmt->bind_param('i', $y);
        $stmt->execute();
        $n = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);
        $stmt->close();
        return 'COT-' . $anio . '-' . str_pad((string)($n + 1), 3, '0', STR_PAD_LEFT);
    }

    public function crearCotizacion(?int $idCliente, ?int $idProspecto, ?string $codigo, string $fecha, ?string $validaHasta, string $moneda, ?string $notas, array $items): int
    {
        $moneda = in_array($moneda, ['PEN','USD'], true) ? $moneda : 'PEN';
        $codigo = trim((string)$codigo);
        if ($codigo === '') {
            $codigo = $this->siguienteCodigo();
        }
        if ($items === []) {
            throw new InvalidArgumentException('La cotización debe tener al menos un ítem.');
        }

        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += max(0, (float)$item['cantidad']) * max(0, (float)$item['precio_unitario']);
        }
        $subtotal = round($subtotal, 2);
        $igv = 0.0;
        $total = $subtotal;

        $this->conexion->begin_transaction();
        try {
            $s = $this->conexion->prepare("INSERT INTO cotizaciones(id_cliente,id_prospecto,numero_cotizacion,fecha_emision,fecha_vencimiento,descripcion,notas_condiciones,moneda,subtotal,igv,total,estado) VALUES(?,?,?,?,?,?,?,?,?,?,?,'PENDIENTE')");
            if (!$s) {
                throw new RuntimeException('No se pudo preparar la cotización.');
            }
            $descripcion = $notas;
            $s->bind_param('iissssssddd', $idCliente, $idProspecto, $codigo, $fecha, $validaHasta, $descripcion, $notas, $moneda, $subtotal, $igv, $total);
            if (!$s->execute()) {
                $e = $s->error;
                $s->close();
                throw new RuntimeException($e);
            }
            $id = (int)$this->conexion->insert_id;
            $s->close();

            $ins = $this->conexion->prepare("INSERT INTO cotizacion_items(id_cotizacion,id_servicio,tipo_item,descripcion,cantidad,precio_unitario,total) VALUES(?,?,?,?,?,?,?)");
            if (!$ins) {
                throw new RuntimeException('No se pudieron preparar los ítems de la cotización.');
            }
            foreach ($items as $item) {
                $idServicio = $item['id_servicio'];
                $tipo = $item['tipo'];
                $desc = $item['descripcion'];
                $cant = max(.01, (float)$item['cantidad']);
                $precio = max(0, (float)$item['precio_unitario']);
                $linea = round($cant * $precio, 2);
                $ins->bind_param('iissddd', $id, $idServicio, $tipo, $desc, $cant, $precio, $linea);
                if (!$ins->execute()) {
                    $e = $ins->error;
                    $ins->close();
                    throw new RuntimeException($e);
                }
            }
            $ins->close();
            $this->conexion->commit();
            return $id;
        } catch (Throwable $e) {
            $this->conexion->rollback();
            throw $e;
        }
    }

    public function listarItems(array $cotizaciones):array
    {
        $ids=array_values(array_unique(array_filter(array_map(static fn($r)=>(int)$r['id_cotizacion'],$cotizaciones))));
        if(!$ids)return [];
        $result=$this->conexion->query('SELECT id_cotizacion,id_servicio,tipo_item,descripcion,cantidad,precio_unitario FROM cotizacion_items WHERE id_cotizacion IN ('.implode(',',$ids).') ORDER BY id_cotizacion');
        $out=[];
        if($result)while($r=$result->fetch_assoc())$out[(int)$r['id_cotizacion']][]=$r;
        return $out;
    }

                                                                                      
    public function editarCotizacion(int $id,string $filtro,?int $idCliente,?int $idProspecto,string $codigo,string $fecha,?string $hasta,string $moneda,?string $notas,array $items):void
    {
        if(!$items)throw new DomainException('Agrega al menos un ítem.');
        $subtotal=0.0;foreach($items as $item)$subtotal+=(float)$item['cantidad']*(float)$item['precio_unitario'];$subtotal=round($subtotal,2);
        $this->conexion->begin_transaction();
        try{
            $s=$this->conexion->prepare("SELECT q.id_cotizacion FROM cotizaciones q LEFT JOIN clientes c ON c.id_cliente=q.id_cliente WHERE q.id_cotizacion=? AND ({$filtro}) FOR UPDATE");
            $s->bind_param('i',$id);$s->execute();$ok=(bool)$s->get_result()->fetch_assoc();$s->close();
            if(!$ok)throw new DomainException('Cotización no encontrada o sin acceso.');
            $s=$this->conexion->prepare('UPDATE cotizaciones SET id_cliente=?,id_prospecto=?,numero_cotizacion=?,fecha_emision=?,fecha_vencimiento=?,descripcion=?,notas_condiciones=?,moneda=?,subtotal=?,igv=0,total=? WHERE id_cotizacion=?');
            $s->bind_param('iissssssddi',$idCliente,$idProspecto,$codigo,$fecha,$hasta,$notas,$notas,$moneda,$subtotal,$subtotal,$id);$s->execute();$s->close();
            $s=$this->conexion->prepare('DELETE FROM cotizacion_items WHERE id_cotizacion=?');$s->bind_param('i',$id);$s->execute();$s->close();
            $s=$this->conexion->prepare('INSERT INTO cotizacion_items(id_cotizacion,id_servicio,tipo_item,descripcion,cantidad,precio_unitario,total) VALUES(?,?,?,?,?,?,?)');
            foreach($items as $item){$sid=$item['id_servicio'];$tipo=$item['tipo'];$desc=$item['descripcion'];$cant=(float)$item['cantidad'];$precio=(float)$item['precio_unitario'];$total=round($cant*$precio,2);$s->bind_param('iissddd',$id,$sid,$tipo,$desc,$cant,$precio,$total);$s->execute();}
            $s->close();$this->conexion->commit();
        }catch(Throwable $e){$this->conexion->rollback();throw $e;}
    }

    public function eliminarCotizacion(int $id,string $filtro):void
    {
        $this->conexion->begin_transaction();
        try{
            $s=$this->conexion->prepare("SELECT q.id_cotizacion FROM cotizaciones q LEFT JOIN clientes c ON c.id_cliente=q.id_cliente WHERE q.id_cotizacion=? AND ({$filtro}) FOR UPDATE");
            $s->bind_param('i',$id);$s->execute();$ok=(bool)$s->get_result()->fetch_assoc();$s->close();
            if(!$ok)throw new DomainException('Cotización no encontrada o sin acceso.');
            $s=$this->conexion->prepare('DELETE FROM cotizacion_items WHERE id_cotizacion=?');$s->bind_param('i',$id);$s->execute();$s->close();
            $s=$this->conexion->prepare('DELETE FROM cotizaciones WHERE id_cotizacion=?');$s->bind_param('i',$id);$s->execute();$s->close();
            $this->conexion->commit();
        }catch(Throwable $e){$this->conexion->rollback();throw $e;}
    }
}
