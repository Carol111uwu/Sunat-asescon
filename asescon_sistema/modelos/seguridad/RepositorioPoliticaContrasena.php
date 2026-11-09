<?php

declare(strict_types=1);

final class RepositorioPoliticaContrasena
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function obtenerEstado(int $idUsuario): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT fecha_cambio_password,fecha_ultimo_aviso_password,fecha_creacion,tipo_cuenta FROM usuarios WHERE id_usuario=? AND estado=1 LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $fila;
    }

    public function diasRecordatorio(): int
    {
        $clave = 'seguridad_dias_recordatorio_password';
        $stmt = $this->conexion->prepare('SELECT valor FROM configuracion_sistema WHERE clave=? LIMIT 1');
        if (!$stmt) {
            return 45;
        }
        $stmt->bind_param('s', $clave);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $dias = (int)($fila['valor'] ?? 45);
        return $dias >= 1 && $dias <= 365 ? $dias : 45;
    }

    public function registrarAvisoDescartado(int $idUsuario): void
    {
        $stmt = $this->conexion->prepare('UPDATE usuarios SET fecha_ultimo_aviso_password=NOW() WHERE id_usuario=? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('No se pudo registrar el aviso de contraseña.');
        }
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $stmt->close();
    }
}
