<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelos/seguridad/Usuario.php';
require_once __DIR__ . '/../../modelos/seguridad/RepositorioRecuperacionContrasena.php';
require_once __DIR__ . '/ServicioCorreoRecuperacion.php';
require_once __DIR__ . '/ServicioSmsRecuperacion.php';

final class ServicioRecuperacionContrasena
{
    private RepositorioRecuperacionContrasena $repositorio;

    public function __construct(private mysqli $conexion)
    {
        $this->repositorio = new RepositorioRecuperacionContrasena($conexion);
    }

    public function solicitarPorCorreo(string $correo): void
    {
        $this->limpiarSesionSms();
        $correo = trim($correo);
        if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('correo_invalido');
        }

        $usuario = $this->repositorio->buscarUsuarioActivoPorCorreo($correo);
        if ($usuario === null) {
            usleep(120000);
            return;
        }

        $correoDestino = strcasecmp($correo, (string)$usuario['correo']) === 0
            ? (string)$usuario['correo']
            : (string)($usuario['correo_secundario'] ?? '');
        if ($correoDestino === '') {
            usleep(120000);
            return;
        }

        $idUsuario = (int)$usuario['id_usuario'];
        $token = bin2hex(random_bytes(32));
        $this->repositorio->reemplazarToken(
            $idUsuario,
            hash('sha256', $token),
            date('Y-m-d H:i:s', time() + 1800)
        );

        try {
            $config = require __DIR__ . '/../../configuracion/correo.php';
            $enlace = rtrim((string)$config['base_url'], '/')
                . '/vistas/restablecer_contrasena.php?token=' . rawurlencode($token);
            (new ServicioCorreoRecuperacion())->enviar($correoDestino, $enlace);
        } catch (Throwable $error) {
            $this->repositorio->eliminarTokensDeUsuario($idUsuario);
            error_log('Correo de recuperación ASESCON: ' . $error->getMessage());
        }
    }

    public function solicitarPorSms(string $numeroNacional): array
    {
        $this->limpiarSesionSms();
        $servicioSms = new ServicioSmsRecuperacion();
        if (!$servicioSms->disponible()) {
            throw new DomainException('sms_no_disponible');
        }
        $telefono = $this->normalizarTelefonoPeru($numeroNacional);
        $usuario = $this->repositorio->buscarUsuarioActivoPorTelefono($telefono);
        $telefonoOculto = $this->ocultarTelefono($telefono);

        if ($usuario === null) {
            $this->prepararSesionSmsFicticia($telefono);
            usleep(120000);
            return ['telefono_oculto' => $telefonoOculto];
        }

        $codigo = (string)random_int(100000, 999999);
        $idUsuario = (int)$usuario['id_usuario'];
        $this->repositorio->reemplazarToken(
            $idUsuario,
            hash('sha256', $idUsuario . ':' . $codigo),
            date('Y-m-d H:i:s', time() + 600)
        );

        try {
            $servicioSms->enviarCodigo($telefono, $codigo);
            $_SESSION['recuperacion_sms_usuario'] = $idUsuario;
        } catch (Throwable $error) {
            $this->repositorio->eliminarTokensDeUsuario($idUsuario);
            error_log('SMS de recuperación ASESCON: ' . $error->getMessage());
            $_SESSION['recuperacion_sms_usuario'] = 0;
        }

        $_SESSION['recuperacion_sms_telefono'] = $telefono;
        $_SESSION['recuperacion_sms_intentos'] = 0;
        $_SESSION['recuperacion_sms_expira_unix'] = time() + 600;

        return ['telefono_oculto' => $telefonoOculto];
    }

    public function verificarCodigoSms(string $codigo): string
    {
        $codigo = preg_replace('/\D+/', '', trim($codigo)) ?? '';
        if (!preg_match('/^[0-9]{6}$/', $codigo)) {
            throw new DomainException('codigo_sms_invalido');
        }

        $idUsuario = (int)($_SESSION['recuperacion_sms_usuario'] ?? 0);
        $intentos = (int)($_SESSION['recuperacion_sms_intentos'] ?? 0);
        $expira = (int)($_SESSION['recuperacion_sms_expira_unix'] ?? 0);
        if ($expira < time()) {
            if ($idUsuario > 0) {
                $this->repositorio->eliminarTokensDeUsuario($idUsuario);
            }
            $this->limpiarSesionSms();
            throw new DomainException('codigo_sms_expirado');
        }
        if ($intentos >= 5) {
            if ($idUsuario > 0) {
                $this->repositorio->eliminarTokensDeUsuario($idUsuario);
            }
            $this->limpiarSesionSms();
            throw new DomainException('codigo_sms_bloqueado');
        }

        if ($idUsuario <= 0) {
            $_SESSION['recuperacion_sms_intentos'] = $intentos + 1;
            if ((int)$_SESSION['recuperacion_sms_intentos'] >= 5) {
                $this->limpiarSesionSms();
                throw new DomainException('codigo_sms_bloqueado');
            }
            throw new DomainException('codigo_sms_incorrecto');
        }

        $registro = $this->repositorio->obtenerTokenVigente(hash('sha256', $idUsuario . ':' . $codigo));
        if ($registro === null || (int)$registro['id_usuario'] !== $idUsuario) {
            $_SESSION['recuperacion_sms_intentos'] = $intentos + 1;
            if ((int)$_SESSION['recuperacion_sms_intentos'] >= 5) {
                $this->repositorio->eliminarTokensDeUsuario($idUsuario);
                $this->limpiarSesionSms();
                throw new DomainException('codigo_sms_bloqueado');
            }
            throw new DomainException('codigo_sms_incorrecto');
        }

        $token = bin2hex(random_bytes(32));
        $this->repositorio->reemplazarToken(
            $idUsuario,
            hash('sha256', $token),
            date('Y-m-d H:i:s', time() + 1800)
        );
        $this->limpiarSesionSms();
        return $token;
    }

    public function restablecer(string $token, string $password, string $confirmacion): void
    {
        $token = trim($token);
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new DomainException('token_invalido');
        }
        if ($password === '' || $confirmacion === '' || $password !== $confirmacion) {
            throw new DomainException('password_confirmacion');
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            throw new DomainException('password_longitud');
        }

        $this->conexion->begin_transaction();
        try {
            $registro = $this->repositorio->obtenerTokenVigenteParaActualizar(hash('sha256', $token));
            if ($registro === null) {
                throw new DomainException('token_invalido');
            }

            $idUsuario = (int)$registro['id_usuario'];
            (new Usuario($this->conexion))->actualizarPassword(
                $idUsuario,
                password_hash($password, PASSWORD_DEFAULT)
            );
            $this->repositorio->marcarUsado((int)$registro['id_recuperacion']);
            if (function_exists('asesconRegistrarAuditoria')) {
                asesconRegistrarAuditoria(
                    $this->conexion,
                    'RECUPERAR_PASSWORD',
                    'AUTENTICACION',
                    null,
                    'usuarios',
                    (string)$idUsuario
                );
            }
            $this->conexion->commit();
        } catch (Throwable $error) {
            $this->conexion->rollback();
            throw $error;
        }
    }

    public function normalizarTelefonoPeru(string $telefono): string
    {
        $digitos = preg_replace('/\D+/', '', trim($telefono)) ?? '';
        if (str_starts_with($digitos, '51') && strlen($digitos) === 11) {
            $digitos = substr($digitos, 2);
        }
        if (!preg_match('/^9[0-9]{8}$/', $digitos)) {
            throw new DomainException('telefono_invalido');
        }
        return '+51' . $digitos;
    }

    private function ocultarTelefono(string $telefono): string
    {
        $digitos = substr($telefono, 3);
        return '+51 *** *** ' . substr($digitos, -3);
    }

    private function prepararSesionSmsFicticia(string $telefono): void
    {
        $_SESSION['recuperacion_sms_usuario'] = 0;
        $_SESSION['recuperacion_sms_telefono'] = $telefono;
        $_SESSION['recuperacion_sms_intentos'] = 0;
        $_SESSION['recuperacion_sms_expira_unix'] = time() + 600;
    }

    private function limpiarSesionSms(): void
    {
        unset(
            $_SESSION['recuperacion_sms_usuario'],
            $_SESSION['recuperacion_sms_telefono'],
            $_SESSION['recuperacion_sms_intentos'],
            $_SESSION['recuperacion_sms_expira_unix'],
            $_SESSION['telefono_recuperacion_oculto']
        );
    }
}
