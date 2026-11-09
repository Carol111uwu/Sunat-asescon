<?php
declare(strict_types=1);

                                                                                      
final class SelectorClientes
{
    public static function visibles(mysqli $conexion): array
    {
        $filtro = asesconFiltroClientesSql($conexion, 'c');
        $sql = "SELECT c.id_cliente,c.ruc,c.razon_social,c.nombre_comercial,c.celular
                FROM clientes c WHERE {$filtro} ORDER BY c.razon_social,c.id_cliente";
        $resultado = $conexion->query($sql);
        if (!$resultado) return [];
        $clientes = [];
        while ($fila = $resultado->fetch_assoc()) $clientes[] = $fila;
        return $clientes;
    }

    public static function idAutorizado(array $clientes, mixed $solicitado): int
    {
        $id = filter_var($solicitado, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) return 0;
        foreach ($clientes as $cliente) {
            if ((int)$cliente['id_cliente'] === $id) return $id;
        }
        return 0;
    }
}
