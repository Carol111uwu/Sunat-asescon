<?php

declare(strict_types=1);

final class RepositorioCambioCuenta
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function obtenerCuentaActiva(int $idUsuario): ?array
    {
        $stmt = $this->conexion->prepare(
            "SELECT u.id_usuario,u.usuario,u.password,u.id_rol,u.acceso_todos_clientes,u.estado,u.tipo_cuenta,
                    r.codigo AS rol_codigo,r.nombre AS rol_nombre,r.es_principal
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol=u.id_rol AND r.estado=1
             WHERE u.id_usuario=? AND u.estado=1
             LIMIT 1"
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

    public function listarCuentasClienteActivas(): array
    {
        $resultado = $this->conexion->query(
            "SELECT u.id_usuario,u.usuario,u.tipo_cuenta,r.codigo AS rol_codigo,r.nombre AS rol_nombre
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol=u.id_rol AND r.estado=1
             WHERE u.estado=1 AND u.tipo_cuenta='CLIENTE'
             ORDER BY u.id_usuario"
        );

        if (!$resultado) {
            return [];
        }

        $cuentas = [];
        while ($fila = $resultado->fetch_assoc()) {
            $cuentas[] = $fila;
        }
        return $cuentas;
    }

    public function registrarCambioCuenta(int $idActor, int $idObjetivo, string $accion): void
    {
        $modulo = 'SEGURIDAD';
        $entidad = 'usuarios';
        $idEntidad = (string)$idObjetivo;
        $detalle = 'Cambio de contexto entre cuentas principales';
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

        $stmt = $this->conexion->prepare(
            'INSERT INTO auditoria (id_usuario,id_cliente,accion,modulo,entidad,id_entidad,detalle,ip) VALUES (?,NULL,?,?,?,?,?,?)'
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('issssss', $idActor, $accion, $modulo, $entidad, $idEntidad, $detalle, $ip);
        @$stmt->execute();
        $stmt->close();
    }
}
