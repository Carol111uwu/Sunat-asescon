<?php

declare(strict_types=1);

final class RepositorioPerfilCuenta
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function obtenerPerfil(int $idUsuario): ?array
    {
        $stmt = $this->conexion->prepare(
            "SELECT u.id_usuario,u.usuario,u.correo,u.correo_secundario,u.telefono,u.tipo_cuenta,u.id_rol,u.estado,u.fecha_creacion,u.fecha_cambio_password,
                    r.codigo AS rol_codigo,r.nombre AS rol_nombre,r.nivel AS rol_nivel
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol=u.id_rol
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

    public function existeUsuario(string $usuario, int $exceptoId): bool
    {
        $stmt = $this->conexion->prepare('SELECT 1 FROM usuarios WHERE usuario=? AND id_usuario<>? LIMIT 1');
        if (!$stmt) {
            return true;
        }
        $stmt->bind_param('si', $usuario, $exceptoId);
        $stmt->execute();
        $existe = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $existe;
    }

    public function existeCorreoEnOtraCuenta(string $correo, int $exceptoId): bool
    {
        $stmt = $this->conexion->prepare(
            'SELECT 1 FROM usuarios WHERE id_usuario<>? AND (LOWER(correo)=LOWER(?) OR LOWER(correo_secundario)=LOWER(?)) LIMIT 1'
        );
        if (!$stmt) {
            return true;
        }
        $stmt->bind_param('iss', $exceptoId, $correo, $correo);
        $stmt->execute();
        $existe = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $existe;
    }

    public function existeTelefonoEnOtraCuenta(string $telefono, int $exceptoId): bool
    {
        $telefonoNormalizado = preg_replace('/\D+/', '', $telefono) ?? '';
        $stmt = $this->conexion->prepare(
            "SELECT 1 FROM usuarios
             WHERE id_usuario<>?
               AND telefono IS NOT NULL
               AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono,'+',''),' ',''),'-',''),'(',''),')','')=?
             LIMIT 1"
        );
        if (!$stmt) {
            return true;
        }
        $stmt->bind_param('is', $exceptoId, $telefonoNormalizado);
        $stmt->execute();
        $existe = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $existe;
    }

    public function actualizarCampo(int $idUsuario, string $campo, ?string $valor): void
    {
        $permitidos = ['usuario', 'correo', 'correo_secundario', 'telefono'];
        if (!in_array($campo, $permitidos, true)) {
            throw new InvalidArgumentException('Campo de perfil no permitido.');
        }

        $sql = "UPDATE usuarios SET {$campo}=? WHERE id_usuario=? LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar la actualización del perfil.');
        }
        $stmt->bind_param('si', $valor, $idUsuario);
        if (!$stmt->execute()) {
            $errno = $stmt->errno;
            $error = $stmt->error;
            $stmt->close();
            if ($errno === 1062) {
                throw new DomainException('dato_duplicado');
            }
            throw new RuntimeException($error);
        }
        $stmt->close();
    }

    public function registrarCambio(int $idUsuario, string $campo): void
    {
        $stmt = $this->conexion->prepare('INSERT INTO usuario_cambios_perfil (id_usuario,campo,fecha_cambio) VALUES (?,?,NOW())');
        if (!$stmt) {
            throw new RuntimeException('No se pudo registrar el cambio del perfil.');
        }
        $stmt->bind_param('is', $idUsuario, $campo);
        $stmt->execute();
        $stmt->close();
    }

    public function estadoLimite(int $idUsuario, string $campo, int $maximo = 3): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT COUNT(*) AS total,MIN(fecha_cambio) AS primera_fecha FROM usuario_cambios_perfil WHERE id_usuario=? AND campo=? AND fecha_cambio>DATE_SUB(NOW(),INTERVAL 24 HOUR)'
        );
        if (!$stmt) {
            return ['usados' => 0, 'restantes' => $maximo, 'bloqueado' => false, 'disponible_desde' => null];
        }
        $stmt->bind_param('is', $idUsuario, $campo);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        $usados = (int)($fila['total'] ?? 0);
        $bloqueado = $usados >= $maximo;
        $disponibleDesde = null;
        if ($bloqueado && !empty($fila['primera_fecha'])) {
            $fecha = new DateTimeImmutable((string)$fila['primera_fecha'], new DateTimeZone('America/Lima'));
            $disponibleDesde = $fecha->modify('+24 hours')->format('d/m/Y H:i');
        }

        return [
            'usados' => $usados,
            'restantes' => max(0, $maximo - $usados),
            'bloqueado' => $bloqueado,
            'disponible_desde' => $disponibleDesde,
        ];
    }
}
