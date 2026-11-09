<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelos/seguridad/Usuario.php';

final class GestorContrasenas
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function cambiarDeUsuario(int $idObjetivo, string $nueva): void
    {
        asesconExigirPermiso($this->conexion, 'EQUIPO', 'editar');
        if (!asesconPuedeAdministrarUsuario($this->conexion, $idObjetivo)) {
            throw new DomainException('usuario_no_autorizado');
        }
        $this->validarLongitud($nueva);
        (new Usuario($this->conexion))->actualizarPassword($idObjetivo, password_hash($nueva, PASSWORD_DEFAULT));
        asesconRegistrarAuditoria($this->conexion, 'CAMBIAR_PASSWORD_USUARIO', 'EQUIPO', null, 'usuarios', (string)$idObjetivo);
    }

    public function cambiarPropia(string $actual, string $nueva): void
    {
        $actor = asesconUsuarioActual($this->conexion);
        if (!in_array(strtoupper((string)($actor['tipo_cuenta'] ?? 'EQUIPO')), ['CLIENTE', 'CREADOR'], true)) {
            throw new DomainException('usuario_no_autorizado');
        }
        $modelo = new Usuario($this->conexion);
        $cuenta = $modelo->obtener((int)$actor['id_usuario']);
        if (!$cuenta || !password_verify($actual, (string)$cuenta['password'])) {
            throw new DomainException('password_actual');
        }
        $this->validarLongitud($nueva);
        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $modelo->actualizarPassword((int)$actor['id_usuario'], $hash);
        $_SESSION['firma_password'] = hash('sha256', $hash);
        session_regenerate_id(true);
        asesconRegistrarAuditoria($this->conexion, 'CAMBIAR_PASSWORD_PROPIA', 'EQUIPO', null, 'usuarios', (string)$actor['id_usuario']);
    }

    private function validarLongitud(string $password): void
    {
        if (strlen($password) < 8 || strlen($password) > 72) {
            throw new DomainException('password');
        }
    }
}
