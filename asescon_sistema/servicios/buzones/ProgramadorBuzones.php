<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/sunat/ServicioColaBuzonSol.php';
require_once dirname(__DIR__) . '/sunafil/ServicioColaBuzonSunafil.php';

                                                                                       
final class ProgramadorBuzones
{
    public function __construct(private mysqli $conexion) {}

    public function todos(string $buzon): array
    {
        if (!in_array($buzon, ['SOL', 'SUNAFIL'], true)) {
            throw new InvalidArgumentException('Buzón no reconocido.');
        }

        $cola = $buzon === 'SOL' ? new ServicioColaBuzonSol() : new ServicioColaBuzonSunafil();
        $filtro = asesconFiltroClientesSql($this->conexion, 'c');
        $consulta = $this->conexion->query(
            "SELECT DISTINCT c.id_cliente FROM clientes c " .
            "INNER JOIN credenciales_sunat cs ON cs.id_cliente=c.id_cliente " .
            "WHERE ({$filtro}) AND c.estado='ACTIVO' AND cs.estado=1 " .
            "AND TRIM(COALESCE(cs.usuario_sol,''))<>'' " .
            "AND TRIM(COALESCE(cs.clave_sol_cifrada,''))<>'' ORDER BY c.id_cliente"
        );
        if (!$consulta) {
            throw new RuntimeException('No se pudo obtener la lista de empresas autorizadas.');
        }

        $resultado = ['empresas' => 0, 'programadas' => 0, 'en_curso' => 0, 'errores' => 0];
        while ($empresa = $consulta->fetch_assoc()) {
            $resultado['empresas']++;
            try {
                $estado = $cola->solicitar((int)$empresa['id_cliente'], (int)($_SESSION['id_usuario'] ?? 0));
                $resultado[!empty($estado['reutilizada']) ? 'en_curso' : 'programadas']++;
            } catch (Throwable $e) {
                $resultado['errores']++;
                error_log('Buzón ' . $buzon . ': empresa ' . (int)$empresa['id_cliente'] . ' ' . get_class($e));
            }
        }
        return $resultado;
    }

    public static function mensaje(string $buzon, array $resumen): string
    {
        return $buzon . ': ' . (int)$resumen['programadas'] . ' consultas programadas, ' .
            (int)$resumen['en_curso'] . ' ya en curso y ' . (int)$resumen['errores'] .
            ' con error de programación (' . (int)$resumen['empresas'] . ' empresas con acceso configurado).';
    }
}
