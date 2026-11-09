<?php
declare(strict_types=1);

final class EnvioEntregables
{
    public function __construct(private mysqli $conexion) {}

    public function reservar(string $clave, int $cliente, int $usuario, string $destinatario, string $asunto): ?int
    {
        $s=$this->conexion->prepare('INSERT IGNORE INTO envios_entregables(clave_envio,id_cliente,id_usuario,destinatario,asunto) VALUES(?,?,?,?,?)');
        $s->bind_param('siiss',$clave,$cliente,$usuario,$destinatario,$asunto);$s->execute();
        $id=$s->affected_rows===1 ? (int)$s->insert_id : null;$s->close();return $id;
    }

    public function terminar(int $id, bool $correcto): void
    {
        $estado=$correcto?'ENVIADO':'ERROR';
        $s=$this->conexion->prepare('UPDATE envios_entregables SET estado=? WHERE id_envio=?');
        $s->bind_param('si',$estado,$id);$s->execute();$s->close();
    }

    public function historial(string $filtro): array
    {
        return $this->conexion->query("SELECT e.*,c.razon_social,c.ruc FROM envios_entregables e INNER JOIN clientes c ON c.id_cliente=e.id_cliente WHERE {$filtro} ORDER BY e.id_envio DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
    }
}
