<?php
declare(strict_types=1);

final class ConfiguracionComunicaciones
{
    public function __construct(private mysqli $conexion) {}

    public function disponible(): bool
    {
        $r = $this->conexion->query("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('configuracion_comunicaciones','tipos_entregable','cola_avisos_buzones','envios_entregables','padron_ssco','actualizacion_padron_ssco')");
        return (int)$r->fetch_assoc()['n'] === 6;
    }

    public function instalar(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../base_datos/migraciones/2026_10_14_comunicaciones_y_ssco.sql');
        $complemento = file_get_contents(__DIR__.'/../../base_datos/migraciones/2026_10_14_archivos_y_cuentas.sql');
        if (!is_string($sql) || !is_string($complemento)) { throw new RuntimeException('No se encontró la migración del módulo.'); }
        $sql .= "\n".$complemento;
        $this->conexion->multi_query($sql);
        do {
            $resultado = $this->conexion->store_result();
            if ($resultado) { $resultado->free(); }
        } while ($this->conexion->more_results() && $this->conexion->next_result());
    }

    public function obtener(): array
    {
        return $this->conexion->query('SELECT * FROM configuracion_comunicaciones WHERE id=1')->fetch_assoc()
            ?: throw new RuntimeException('Prepara la configuración antes de continuar.');
    }

    public function guardar(array $datos): void
    {
        $bloqueo=$this->conexion->query("SELECT GET_LOCK(CONCAT(DATABASE(),':avisos'),5) obtenido")->fetch_assoc();
        if (!(int)$bloqueo['obtenido']) { throw new DomainException('Hay avisos en proceso. Intenta guardar de nuevo cuando termine el envío.'); }
        try {
            $this->conexion->begin_transaction();
            $anterior = $this->conexion->query('SELECT * FROM configuracion_comunicaciones WHERE id=1 FOR UPDATE')->fetch_assoc();
            $sol = (int)$anterior['ultimo_sol_inicial'];
            $sunafil = (int)$anterior['ultimo_sunafil_inicial'];
            if ($datos['avisos_activos'] && !$anterior['avisos_activos']) {
                $sol = (int)$this->conexion->query('SELECT COALESCE(MAX(id_documento),0) n FROM buzon_sol_sunat')->fetch_assoc()['n'];
                $sunafil = (int)$this->conexion->query('SELECT COALESCE(MAX(id_documento),0) n FROM buzon_sunafil')->fetch_assoc()['n'];
            }
            if (!$datos['avisos_activos']) {
                $this->conexion->query("UPDATE cola_avisos_buzones SET estado='ANULADO' WHERE estado='PENDIENTE'");
            }
            $s = $this->conexion->prepare('UPDATE configuracion_comunicaciones SET nombre_remitente=?,correo_copia=?,correo_avisos=?,avisos_activos=?,plantilla_asunto=?,plantilla_mensaje=?,ultimo_sol_inicial=?,ultimo_sunafil_inicial=?,actualizado_en=NOW() WHERE id=1');
            $s->bind_param('sssissii', $datos['nombre_remitente'], $datos['correo_copia'], $datos['correo_avisos'], $datos['avisos_activos'], $datos['plantilla_asunto'], $datos['plantilla_mensaje'], $sol, $sunafil);
            $s->execute();
            $s->close();
            $this->conexion->commit();
        } catch (Throwable $e) { $this->conexion->rollback(); throw $e; }
        finally { $this->conexion->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(),':avisos'))"); }
    }

    public function entregables(): array
    {
        return $this->conexion->query('SELECT id_entregable,nombre FROM tipos_entregable ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
    }

    public function agregarEntregable(string $nombre): void
    {
        $s = $this->conexion->prepare('INSERT IGNORE INTO tipos_entregable(nombre) VALUES(?)');
        $s->bind_param('s', $nombre); $s->execute(); $s->close();
    }

    public function eliminarEntregable(int $id): void
    {
        $s = $this->conexion->prepare('DELETE FROM tipos_entregable WHERE id_entregable=?');
        $s->bind_param('i', $id); $s->execute(); $s->close();
    }
}
