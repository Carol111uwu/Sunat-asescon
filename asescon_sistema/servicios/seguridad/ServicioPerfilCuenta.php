<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelos/seguridad/RepositorioPerfilCuenta.php';
require_once __DIR__ . '/GestorContrasenas.php';

final class ServicioPerfilCuenta
{
    private const MAX_CAMBIOS_24H = 3;
    private RepositorioPerfilCuenta $repositorio;

    public function __construct(private mysqli $conexion)
    {
        $this->repositorio = new RepositorioPerfilCuenta($conexion);
    }

    public function datosUsuarioActual(): array
    {
        $usuario = asesconUsuarioActual($this->conexion);
        if (!in_array(strtoupper((string)($usuario['tipo_cuenta'] ?? 'EQUIPO')), ['CLIENTE', 'CREADOR'], true)) {
            throw new DomainException('perfil_restringido');
        }
        $perfil = $this->repositorio->obtenerPerfil((int)$usuario['id_usuario']);
        if (!$perfil) {
            throw new RuntimeException('No se encontró el perfil de la cuenta.');
        }

        $sinLimite = strtoupper((string)$perfil['tipo_cuenta']) === 'CREADOR';
        $limites = [];
        foreach (['usuario', 'correo', 'correo_secundario', 'telefono', 'password'] as $campo) {
            $limites[$campo] = $sinLimite
                ? ['usados' => 0, 'restantes' => null, 'bloqueado' => false, 'disponible_desde' => null]
                : $this->repositorio->estadoLimite((int)$usuario['id_usuario'], $campo, self::MAX_CAMBIOS_24H);
        }

        return [
            'perfil' => $perfil,
            'limites' => $limites,
            'maximoCambios' => self::MAX_CAMBIOS_24H,
            'sinLimiteCambios' => $sinLimite,
        ];
    }

    public function actualizarDato(string $campo, string $valor, bool $confirmado): void
    {
        if (!$confirmado) {
            throw new DomainException('confirmacion_requerida');
        }

        $usuarioActual = asesconUsuarioActual($this->conexion);
        $idUsuario = (int)$usuarioActual['id_usuario'];
        $tipoCuenta = strtoupper((string)($usuarioActual['tipo_cuenta'] ?? 'EQUIPO'));
        if (!in_array($tipoCuenta, ['CLIENTE', 'CREADOR'], true)) {
            throw new DomainException('perfil_restringido');
        }
        $campo = trim($campo);
        $valor = trim($valor);
        $permitidos = ['usuario', 'correo', 'correo_secundario', 'telefono'];
        if (!in_array($campo, $permitidos, true)) {
            throw new DomainException('campo_no_valido');
        }
        if ($tipoCuenta === 'CLIENTE' && $campo === 'correo_secundario') {
            throw new DomainException('campo_no_valido');
        }

        if ($tipoCuenta !== 'CREADOR') {
            $this->validarLimite($idUsuario, $campo);
        }

        $perfil = $this->repositorio->obtenerPerfil($idUsuario);
        if (!$perfil) {
            throw new DomainException('perfil_no_encontrado');
        }

        if ($campo === 'usuario') {
            if (!preg_match('/^[A-Za-z0-9._-]{4,50}$/', $valor)) {
                throw new DomainException('usuario_formato');
            }
            if ($this->repositorio->existeUsuario($valor, $idUsuario)) {
                throw new DomainException('usuario_existe');
            }
        }

        if ($campo === 'correo') {
            if ($valor === '' || !filter_var($valor, FILTER_VALIDATE_EMAIL) || strlen($valor) > 180) {
                throw new DomainException('correo_formato');
            }
            if (!empty($perfil['correo_secundario']) && strcasecmp((string)$perfil['correo_secundario'], $valor) === 0) {
                throw new DomainException('correos_iguales');
            }
            if ($this->repositorio->existeCorreoEnOtraCuenta($valor, $idUsuario)) {
                throw new DomainException('correo_existe');
            }
        }

        if ($campo === 'correo_secundario') {
            if ($valor !== '' && (!filter_var($valor, FILTER_VALIDATE_EMAIL) || strlen($valor) > 180)) {
                throw new DomainException('correo_formato');
            }
            if ($valor !== '' && !empty($perfil['correo']) && strcasecmp((string)$perfil['correo'], $valor) === 0) {
                throw new DomainException('correos_iguales');
            }
            if ($valor !== '' && $this->repositorio->existeCorreoEnOtraCuenta($valor, $idUsuario)) {
                throw new DomainException('correo_existe');
            }
        }

        if ($campo === 'telefono') {
            if ($valor !== '') {
                $digitos = preg_replace('/\D+/', '', $valor) ?? '';
                if (str_starts_with($digitos, '51') && strlen($digitos) === 11) {
                    $digitos = substr($digitos, 2);
                }
                if (!preg_match('/^9[0-9]{8}$/', $digitos)) {
                    throw new DomainException('telefono_formato');
                }
                $valor = '+51' . $digitos;
                if ($this->repositorio->existeTelefonoEnOtraCuenta($valor, $idUsuario)) {
                    throw new DomainException('telefono_existe');
                }
            }
        }

        $anterior = trim((string)($perfil[$campo] ?? ''));
        if ($anterior === $valor) {
            throw new DomainException('sin_cambios');
        }

        $this->conexion->begin_transaction();
        try {
            $this->repositorio->actualizarCampo($idUsuario, $campo, $valor === '' ? null : $valor);
            $this->repositorio->registrarCambio($idUsuario, $campo);
            asesconRegistrarAuditoria($this->conexion, 'ACTUALIZAR_PERFIL', 'SEGURIDAD', null, 'usuarios', (string)$idUsuario, 'Campo actualizado: ' . $campo);
            $this->conexion->commit();
        } catch (Throwable $e) {
            $this->conexion->rollback();
            throw $e;
        }

        if ($campo === 'usuario') {
            $_SESSION['usuario'] = $valor;
        }
    }

    public function cambiarPassword(string $actual, string $nueva, string $repetida, bool $confirmado): void
    {
        if (!$confirmado) {
            throw new DomainException('confirmacion_requerida');
        }
        if ($nueva !== $repetida) {
            throw new DomainException('password_confirmacion');
        }

        $usuario = asesconUsuarioActual($this->conexion);
        $idUsuario = (int)$usuario['id_usuario'];
        $tipoCuenta = strtoupper((string)($usuario['tipo_cuenta'] ?? 'EQUIPO'));
        if (!in_array($tipoCuenta, ['CLIENTE', 'CREADOR'], true)) {
            throw new DomainException('perfil_restringido');
        }
        if ($tipoCuenta !== 'CREADOR') {
            $this->validarLimite($idUsuario, 'password');
        }

        (new GestorContrasenas($this->conexion))->cambiarPropia($actual, $nueva);
        try {
            $this->repositorio->registrarCambio($idUsuario, 'password');
        } catch (Throwable $e) {
            error_log('No se pudo registrar el cambio de contraseña: ' . $e->getMessage());
        }
    }

    private function validarLimite(int $idUsuario, string $campo): void
    {
        $estado = $this->repositorio->estadoLimite($idUsuario, $campo, self::MAX_CAMBIOS_24H);
        if ((bool)$estado['bloqueado']) {
            $desde = (string)($estado['disponible_desde'] ?? 'más adelante');
            throw new DomainException('limite_24h|' . $desde);
        }
    }
}
