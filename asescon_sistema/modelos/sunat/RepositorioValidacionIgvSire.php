<?php
declare(strict_types=1);

                                                                                           
final class RepositorioValidacionIgvSire
{
    public function __construct(private mysqli $conexion) {}

    public function disponible(): bool
    {
        $resultado = $this->conexion->query("SHOW TABLES LIKE 'sire_validaciones_igv'");
        return $resultado instanceof mysqli_result && $resultado->num_rows > 0;
    }

    public function obtener(int $idCliente, string $periodo): ?array
    {
        if (!$this->disponible()) return null;
        $stmt = $this->conexion->prepare('SELECT * FROM sire_validaciones_igv WHERE id_cliente=? AND periodo=? LIMIT 1');
        if (!$stmt) throw new RuntimeException('No se pudo preparar la revisión IGV.');
        $stmt->bind_param('is', $idCliente, $periodo);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $fila;
    }

    public function guardar(int $idCliente, string $periodo, array $valores, int $usuario): void
    {
        if (!$this->disponible()) throw new DomainException('Falta instalar la migración de revisión IGV.');
        $stmt = $this->conexion->prepare(
            'INSERT INTO sire_validaciones_igv
              (id_cliente,periodo,credito_validado,saldo_favor_anterior,retenciones_aplicables,percepciones_aplicables,otros_ajustes,observaciones,actualizado_por)
             VALUES (?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE credito_validado=VALUES(credito_validado),
             saldo_favor_anterior=VALUES(saldo_favor_anterior),retenciones_aplicables=VALUES(retenciones_aplicables),
             percepciones_aplicables=VALUES(percepciones_aplicables),otros_ajustes=VALUES(otros_ajustes),
             observaciones=VALUES(observaciones),actualizado_por=VALUES(actualizado_por),actualizado_en=CURRENT_TIMESTAMP'
        );
        if (!$stmt) throw new RuntimeException('No se pudo preparar el guardado IGV.');
        $credito=$valores['credito_validado'];
        $saldo=$valores['saldo_favor_anterior'];
        $retenciones=$valores['retenciones_aplicables'];
        $percepciones=$valores['percepciones_aplicables'];
        $ajustes=$valores['otros_ajustes'];
        $observaciones=$valores['observaciones'];
        $stmt->bind_param('isssssssi',$idCliente,$periodo,$credito,$saldo,$retenciones,$percepciones,$ajustes,$observaciones,$usuario);
        try { $stmt->execute(); } finally { $stmt->close(); }
    }
}
