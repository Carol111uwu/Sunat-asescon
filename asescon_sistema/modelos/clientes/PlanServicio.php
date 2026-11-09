<?php

declare(strict_types=1);

final class PlanServicio
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(): array
    {
        $r = $this->conexion->query("SELECT * FROM planes_servicio ORDER BY estado='ACTIVO' DESC,nombre");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }

    public function crear(string $n, ?string $d, float $p, string $per): void
    {
        $s = $this->conexion->prepare("INSERT INTO planes_servicio(nombre,descripcion,precio,periodicidad,estado) VALUES(?,?,?,?,'ACTIVO')");
        $s->bind_param('ssds', $n, $d, $p, $per);
        $s->execute();
        $s->close();
    }

    public function editar(int $id, string $nombre, ?string $descripcion, float $precio, string $periodicidad): void
    {
        $s = $this->conexion->prepare('UPDATE planes_servicio SET nombre=?,descripcion=?,precio=?,periodicidad=? WHERE id_plan=?');
        $s->bind_param('ssdsi', $nombre, $descripcion, $precio, $periodicidad, $id);
        $s->execute();
        $s->close();
    }

    public function estado(int $id, string $estado): void
    {
        $s = $this->conexion->prepare('UPDATE planes_servicio SET estado=? WHERE id_plan=?');
        $s->bind_param('si', $estado, $id);
        $s->execute();
        $s->close();
    }

    public function eliminar(int $id): void
    {
        $s = $this->conexion->prepare('DELETE FROM planes_servicio WHERE id_plan=?');
        $s->bind_param('i', $id);
        $s->execute();
        if ($s->affected_rows !== 1) { $s->close(); throw new DomainException('El plan ya no existe.'); }
        $s->close();
    }
}
