<?php

declare(strict_types=1);

final class ControladorIndicadoresMenu
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function obtener(): array
    {
        $filtro = asesconFiltroClientesSql($this->conexion, 'c');
        return [
            'sol' => $this->contar("SELECT COUNT(*) AS total FROM buzon_sol_sunat b LEFT JOIN clientes c ON c.id_cliente=b.id_cliente WHERE b.estado='NO_REVISADO' AND ({$filtro})"),
            'sunafil' => $this->contar("SELECT COUNT(*) AS total FROM buzon_sunafil b LEFT JOIN clientes c ON c.id_cliente=b.id_cliente WHERE b.estado='NO_REVISADO' AND ({$filtro})"),
        ];
    }

    private function contar(string $sql): int
    {
        try {
            $resultado = $this->conexion->query($sql);
            return $resultado ? (int)($resultado->fetch_assoc()['total'] ?? 0) : 0;
        } catch (mysqli_sql_exception $error) {
            error_log('Indicadores del menú de ASESCON: ' . $error->getMessage());
            return 0;
        }
    }
}
