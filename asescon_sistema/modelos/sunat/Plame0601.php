<?php

declare(strict_types=1);

final class Plame0601
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(string $filtro, string $periodo = ''): array
    {
        $extra = '';
        if ($periodo !== '') {
            if (preg_match('/^(20\d{2})00$/', $periodo, $m)) {
                $y = $this->conexion->real_escape_string($m[1]);
                $extra = " AND p.periodo LIKE '" . $y . "%'";
            } else {
                $extra = " AND p.periodo='" . $this->conexion->real_escape_string($periodo) . "'";
            }
        }
$r = $this->conexion->query("SELECT p.*,c.ruc,c.razon_social,(SELECT COUNT(*) FROM plame_0601_personas x WHERE x.id_plame=p.id_plame) personas,(SELECT COUNT(*) FROM plame_0601_archivos a WHERE a.id_plame=p.id_plame) archivos FROM plame_0601 p INNER JOIN clientes c ON c.id_cliente=p.id_cliente WHERE {$filtro}{$extra} ORDER BY p.periodo DESC,c.razon_social");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }

    public function detalle(int $id): array
    {
        $s = $this->conexion->prepare('SELECT * FROM plame_0601 WHERE id_plame=?');
        $s->bind_param('i', $id);
        $s->execute();
        $cab = $s->get_result()->fetch_assoc() ?: [];
        $s->close();
        $conceptos = [];
        $personas = [];
        $archivos = [];
        if ($cab) {
            foreach (['plame_0601_conceptos' => 'conceptos','plame_0601_personas' => 'personas','plame_0601_archivos' => 'archivos'] as $t => $k) {
                $r = $this->conexion->query('SELECT * FROM ' . $t . ' WHERE id_plame=' . (int)$id . ' ORDER BY 1');
                while ($r && $x = $r->fetch_assoc()) {
                    ${$k}[] = $x;
                }
            }
        }
return compact('cab', 'conceptos', 'personas', 'archivos');
    }
}
