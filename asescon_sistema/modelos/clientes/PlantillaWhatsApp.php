<?php

declare(strict_types=1);

final class PlantillaWhatsApp
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(): array
    {
        $res = $this->conexion->query("SELECT p.*,u.usuario AS creado_por_usuario FROM plantillas_whatsapp p LEFT JOIN usuarios u ON u.id_usuario=p.creado_por WHERE p.estado='ACTIVA' ORDER BY p.categoria,p.nombre,p.id_plantilla DESC");
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        } return $rows;
    }

    public function crear(string $nombre, string $categoria, string $mensaje, ?int $creadoPor): int
    {
        $stmt = $this->conexion->prepare("INSERT INTO plantillas_whatsapp(nombre,categoria,mensaje,estado,creado_por) VALUES(?,?,?,'ACTIVA',?)");
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar la plantilla.');
        }
        $stmt->bind_param('sssi', $nombre, $categoria, $mensaje, $creadoPor);
        if (!$stmt->execute()) {
            $e = $stmt->error;
            $stmt->close();
            throw new RuntimeException($e);
        }
        $id = (int)$this->conexion->insert_id;
        $stmt->close();
        return $id;
    }

    public function editar(int $id,string $nombre,string $categoria,string $mensaje):void
    {
        $s=$this->conexion->prepare('UPDATE plantillas_whatsapp SET nombre=?,categoria=?,mensaje=? WHERE id_plantilla=?');
        $s->bind_param('sssi',$nombre,$categoria,$mensaje,$id);$s->execute();$s->close();
    }

    public function eliminar(int $id):void
    {
        $s=$this->conexion->prepare('DELETE FROM plantillas_whatsapp WHERE id_plantilla=?');
        $s->bind_param('i',$id);$s->execute();$n=$s->affected_rows;$s->close();
        if($n!==1)throw new DomainException('Plantilla no disponible.');
    }
}
