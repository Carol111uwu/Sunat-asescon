<?php
declare(strict_types=1);
require_once __DIR__.'/ConfiguracionComunicaciones.php';

final class ColaAvisosBuzones
{
    public function __construct(private mysqli $conexion) {}

    public function registrar(string $entidad, ?int $idCliente = null): void
    {
        [$tabla,$columna]=self::origen($entidad);
        if (!(new ConfiguracionComunicaciones($this->conexion))->disponible()) { return; }
        $filtro=$idCliente===null ? 'b.id_cliente IS NOT NULL' : 'b.id_cliente=?';
        $s=$this->conexion->prepare("INSERT IGNORE INTO cola_avisos_buzones(entidad,id_documento,id_cliente) SELECT ?,b.id_documento,b.id_cliente FROM {$tabla} b INNER JOIN configuracion_comunicaciones cfg ON cfg.id=1 AND cfg.avisos_activos=1 WHERE {$filtro} AND b.id_documento>cfg.{$columna}");
        if($idCliente===null) { $s->bind_param('s',$entidad); } else { $s->bind_param('si',$entidad,$idCliente); }
        $s->execute();$s->close();
    }

    public static function origen(string $entidad): array
    {
        return match ($entidad) {
            'SUNAT'=>['buzon_sol_sunat','ultimo_sol_inicial'],
            'SUNAFIL'=>['buzon_sunafil','ultimo_sunafil_inicial'],
            default=>throw new InvalidArgumentException('Buzón no reconocido.')
        };
    }

    public function pendientes(): array
    {
        return $this->conexion->query("SELECT * FROM cola_avisos_buzones WHERE estado='PENDIENTE' AND proximo_intento<=NOW() ORDER BY id_aviso LIMIT 10")->fetch_all(MYSQLI_ASSOC);
    }

    public function documento(array $aviso): ?array
    {
        [$tabla]=self::origen($aviso['entidad']);
        $s=$this->conexion->prepare("SELECT b.asunto,b.fecha_recepcion,c.ruc,c.razon_social FROM {$tabla} b INNER JOIN clientes c ON c.id_cliente=b.id_cliente WHERE b.id_documento=? AND b.id_cliente=?");
        $s->bind_param('ii',$aviso['id_documento'],$aviso['id_cliente']);$s->execute();$d=$s->get_result()->fetch_assoc();$s->close();return $d;
    }

    public function registrarIntento(int $id): int
    {
        $s=$this->conexion->prepare("UPDATE cola_avisos_buzones SET intentos=intentos+1,proximo_intento=DATE_ADD(NOW(),INTERVAL 30 MINUTE) WHERE id_aviso=? AND estado='PENDIENTE'");
        $s->bind_param('i',$id);$s->execute();$s->close();
        $s=$this->conexion->prepare('SELECT intentos FROM cola_avisos_buzones WHERE id_aviso=?');
        $s->bind_param('i',$id);$s->execute();$n=(int)$s->get_result()->fetch_assoc()['intentos'];$s->close();return $n;
    }

    public function estado(int $id, string $estado): void
    {
        if (!in_array($estado,['ENVIADO','ERROR','ANULADO'],true)) { throw new InvalidArgumentException('Estado no válido.'); }
        $s=$this->conexion->prepare("UPDATE cola_avisos_buzones SET estado=?,enviado_en=IF(?='ENVIADO',NOW(),NULL) WHERE id_aviso=?");
        $s->bind_param('ssi',$estado,$estado,$id);$s->execute();$s->close();
    }

    public function resumen(): array
    {
        return $this->conexion->query('SELECT estado,COUNT(*) total FROM cola_avisos_buzones GROUP BY estado')->fetch_all(MYSQLI_ASSOC);
    }

    public function reintentarErrores(): void
    {
        $this->conexion->query("UPDATE cola_avisos_buzones SET estado='PENDIENTE',intentos=0,proximo_intento=NOW() WHERE estado='ERROR'");
    }
}
