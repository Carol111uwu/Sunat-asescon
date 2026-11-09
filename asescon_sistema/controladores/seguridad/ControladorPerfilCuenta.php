<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../servicios/seguridad/ServicioPerfilCuenta.php';

final class ControladorPerfilCuenta
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datosVista(): array
    {
        asesconExigirLogin();
        if (!asesconPuedeAutogestionarCredenciales($this->conexion)) {
            asesconRedirigir('vistas/panel_principal.php');
        }
        $datos = (new ServicioPerfilCuenta($this->conexion))->datosUsuarioActual();
        $datos['csrfPerfil'] = asesconCsrf('csrf_perfil_cuenta');
        return $datos;
    }

    public function procesar(array $post): void
    {
        asesconExigirLogin();
        if (!asesconPuedeAutogestionarCredenciales($this->conexion)) {
            http_response_code(403);
            exit('El acceso de empresa no puede cambiar credenciales. Contacta al administrador de ASESCON.');
        }
        if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_perfil_cuenta')) {
            asesconRedirigir('vistas/seguridad/mi_perfil.php', ['error' => 'csrf']);
        }

        $accion = (string)($post['accion'] ?? '');
        $servicio = new ServicioPerfilCuenta($this->conexion);

        try {
            if ($accion === 'actualizar_dato') {
                $servicio->actualizarDato(
                    (string)($post['campo'] ?? ''),
                    (string)($post['valor'] ?? ''),
                    (string)($post['confirmar_actualizacion'] ?? '') === '1'
                );
                asesconRedirigir('vistas/seguridad/mi_perfil.php', ['actualizado' => 1]);
            }

            if ($accion === 'cambiar_password') {
                $servicio->cambiarPassword(
                    (string)($post['password_actual'] ?? ''),
                    (string)($post['password_nueva'] ?? ''),
                    (string)($post['password_repetida'] ?? ''),
                    (string)($post['confirmar_actualizacion'] ?? '') === '1'
                );
                asesconRedirigir('vistas/seguridad/mi_perfil.php', ['password' => 1]);
            }

            asesconRedirigir('vistas/seguridad/mi_perfil.php', ['error' => 'accion']);
        } catch (DomainException $e) {
            $codigo = $e->getMessage();
            if (str_starts_with($codigo, 'limite_24h|')) {
                [, $desde] = array_pad(explode('|', $codigo, 2), 2, '');
                asesconRedirigir('vistas/seguridad/mi_perfil.php', ['error' => 'limite_24h', 'desde' => $desde]);
            }
            asesconRedirigir('vistas/seguridad/mi_perfil.php', ['error' => $codigo]);
        } catch (Throwable $e) {
            error_log('ControladorPerfilCuenta: ' . $e->getMessage());
            asesconRedirigir('vistas/seguridad/mi_perfil.php', ['error' => 'bd']);
        }
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        asesconRedirigir('vistas/seguridad/mi_perfil.php');
    }
    (new ControladorPerfilCuenta($conexion))->procesar($_POST);
}
