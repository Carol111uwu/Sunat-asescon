<?php

declare(strict_types=1);

final class DeclaracionAfp
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(string $filtroClientes, string $periodo, string $buscar): array
    {
        $stmt = $this->conexion->prepare(
            "SELECT a.*,c.ruc,c.razon_social FROM declaraciones_afp a
             INNER JOIN clientes c ON c.id_cliente=a.id_cliente
             WHERE {$filtroClientes} AND a.periodo>='202101' AND a.periodo<=? AND (?='' OR a.periodo=?)
               AND (c.ruc LIKE ? OR c.razon_social LIKE ?)
             ORDER BY a.periodo DESC,c.razon_social"
        );
        $termino = '%' . $buscar . '%';
        $maximo = (new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Ym');
        $stmt->bind_param('sssss', $maximo, $periodo, $periodo, $termino, $termino);
        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $filas;
    }
}
