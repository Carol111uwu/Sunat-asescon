<?php

declare(strict_types=1);

final class Usuario
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function buscarPorUsuario(string $usuario): ?array
    {
        $stmt = $this->conexion->prepare(
            "SELECT u.*,r.codigo AS rol_codigo,r.nombre AS rol_nombre,r.nivel AS rol_nivel,
                    r.es_principal,r.puede_crear_usuarios,r.nivel_maximo_creable,r.protegido AS rol_protegido
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol=u.id_rol AND r.estado=1
             WHERE u.usuario=? AND u.estado=1 LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $usuario);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $fila;
    }

    public function existeUsuario(string $usuario, int $exceptoId = 0): bool
    {
        if ($exceptoId > 0) {
            $stmt = $this->conexion->prepare('SELECT 1 FROM usuarios WHERE usuario=? AND id_usuario<>? LIMIT 1');
            if (!$stmt) {
                return true;
            }
            $stmt->bind_param('si', $usuario, $exceptoId);
        } else {
            $stmt = $this->conexion->prepare('SELECT 1 FROM usuarios WHERE usuario=? LIMIT 1');
            if (!$stmt) {
                return true;
            }
            $stmt->bind_param('s', $usuario);
        }
        $stmt->execute();
        $existe = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $existe;
    }

    public function listarGestionables(int $idActor, bool $actorEsMain): array
    {
        if ($actorEsMain) {
            $sql = "SELECT u.id_usuario,u.usuario,u.id_rol,u.estado,u.acceso_todos_clientes,u.ultimo_acceso,u.creado_por,u.fecha_creacion,
                           r.codigo AS rol_codigo,r.nombre AS rol_nombre,r.nivel
                    FROM usuarios u
                    INNER JOIN roles r ON r.id_rol=u.id_rol
                    WHERE u.tipo_cuenta='EQUIPO'
                    ORDER BY r.nivel DESC,u.usuario";
            $res = $this->conexion->query($sql);
        } else {
            $stmt = $this->conexion->prepare(
                "SELECT u.id_usuario,u.usuario,u.id_rol,u.estado,u.acceso_todos_clientes,u.ultimo_acceso,u.creado_por,u.fecha_creacion,
                        r.codigo AS rol_codigo,r.nombre AS rol_nombre,r.nivel
                 FROM usuarios u
                 INNER JOIN roles r ON r.id_rol=u.id_rol
                 WHERE u.tipo_cuenta='EQUIPO' AND (u.id_usuario=? OR u.creado_por=?)
                 ORDER BY r.nivel DESC,u.usuario"
            );
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('ii', $idActor, $idActor);
            $stmt->execute();
            $res = $stmt->get_result();
        }

        $filas = [];
        if ($res) {
            while ($fila = $res->fetch_assoc()) {
                $filas[] = $fila;
            }
        }
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
        return $filas;
    }

    public function obtener(int $idUsuario): ?array
    {
        $stmt = $this->conexion->prepare(
            "SELECT u.*,r.codigo AS rol_codigo,r.nombre AS rol_nombre,r.nivel
             FROM usuarios u INNER JOIN roles r ON r.id_rol=u.id_rol
             WHERE u.id_usuario=? LIMIT 1"
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

    public function crear(string $usuario, string $hash, int $idRol, int $accesoTodos, int $creadoPor): int
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO usuarios (usuario,password,id_rol,acceso_todos_clientes,estado,creado_por,tipo_cuenta,fecha_cambio_password) VALUES (?,?,?,?,1,?,\'EQUIPO\',NOW())'
        );
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar la creación del usuario.');
        }
        $stmt->bind_param('ssiii', $usuario, $hash, $idRol, $accesoTodos, $creadoPor);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $errno = $stmt->errno;
            $stmt->close();
            if ($errno === 1062) {
                throw new DomainException('USUARIO_DUPLICADO');
            }
            throw new RuntimeException($error);
        }
        $id = (int)$this->conexion->insert_id;
        $stmt->close();
        return $id;
    }

    public function actualizar(int $idUsuario, string $usuario, int $idRol, int $accesoTodos, ?string $hash = null): void
    {
        if ($hash !== null) {
            $stmt = $this->conexion->prepare('UPDATE usuarios SET usuario=?,password=?,id_rol=?,acceso_todos_clientes=?,fecha_cambio_password=NOW(),fecha_ultimo_aviso_password=NULL WHERE id_usuario=? LIMIT 1');
            if (!$stmt) {
                throw new RuntimeException('No se pudo preparar la actualización.');
            }
            $stmt->bind_param('ssiii', $usuario, $hash, $idRol, $accesoTodos, $idUsuario);
        } else {
            $stmt = $this->conexion->prepare('UPDATE usuarios SET usuario=?,id_rol=?,acceso_todos_clientes=? WHERE id_usuario=? LIMIT 1');
            if (!$stmt) {
                throw new RuntimeException('No se pudo preparar la actualización.');
            }
            $stmt->bind_param('siii', $usuario, $idRol, $accesoTodos, $idUsuario);
        }
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $errno = $stmt->errno;
            $stmt->close();
            if ($errno === 1062) {
                throw new DomainException('USUARIO_DUPLICADO');
            }
            throw new RuntimeException($error);
        }
        $stmt->close();
    }

    public function actualizarPassword(int $idUsuario, string $hash): void
    {
        $stmt = $this->conexion->prepare('UPDATE usuarios SET password=?,intentos_fallidos=0,bloqueado_hasta=NULL,ultimo_intento=NULL,fecha_cambio_password=NOW(),fecha_ultimo_aviso_password=NULL WHERE id_usuario=? LIMIT 1');
        $stmt->bind_param('si', $hash, $idUsuario);
        $stmt->execute();
        $stmt->close();
    }

    public function cambiarRol(int $idUsuario, int $idRol): void
    {
        $stmt = $this->conexion->prepare('UPDATE usuarios SET id_rol=? WHERE id_usuario=? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('No se pudo cambiar el rol.');
        }
        $stmt->bind_param('ii', $idRol, $idUsuario);
        if (!$stmt->execute()) {
            $e = $stmt->error;
            $stmt->close();
            throw new RuntimeException($e);
        }
        $stmt->close();
    }

    public function cambiarEstado(int $idUsuario, int $estado): void
    {
        $stmt = $this->conexion->prepare('UPDATE usuarios SET estado=? WHERE id_usuario=? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('No se pudo cambiar el estado.');
        }
        $stmt->bind_param('ii', $estado, $idUsuario);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException($error);
        }
        $stmt->close();
    }

    public function eliminar(int $idUsuario): void
    {
        $stmt = $this->conexion->prepare('DELETE FROM usuarios WHERE id_usuario=? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar la eliminación.');
        }
        $stmt->bind_param('i', $idUsuario);
        if (!$stmt->execute()) {
            $e = $stmt->error;
            $stmt->close();
            throw new RuntimeException($e);
        }
        $stmt->close();
    }

    public function reiniciarIntentos(int $idUsuario): void
    {
        $stmt = $this->conexion->prepare('UPDATE usuarios SET intentos_fallidos=0,bloqueado_hasta=NULL,ultimo_intento=NULL WHERE id_usuario=?');
        if ($stmt) {
            $stmt->bind_param('i', $idUsuario);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function registrarIntentoFallido(int $idUsuario): int
    {
        $stmt = $this->conexion->prepare('UPDATE usuarios SET intentos_fallidos=intentos_fallidos+1,ultimo_intento=NOW() WHERE id_usuario=?');
        if ($stmt) {
            $stmt->bind_param('i', $idUsuario);
            $stmt->execute();
            $stmt->close();
        }
        $fila = $this->obtener($idUsuario);
        return (int)($fila['intentos_fallidos'] ?? 0);
    }

    public function bloquearUsuario(int $idUsuario, int $minutos = 15): void
    {
        $stmt = $this->conexion->prepare('UPDATE usuarios SET bloqueado_hasta=DATE_ADD(NOW(),INTERVAL ? MINUTE) WHERE id_usuario=?');
        if ($stmt) {
            $stmt->bind_param('ii', $minutos, $idUsuario);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function registrarAcceso(int $idUsuario): void
    {
        $stmt = $this->conexion->prepare('UPDATE usuarios SET ultimo_acceso=NOW() WHERE id_usuario=?');
        if ($stmt) {
            $stmt->bind_param('i', $idUsuario);
            $stmt->execute();
            $stmt->close();
        }
    }
}
