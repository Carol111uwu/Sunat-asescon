<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelos/panel/RepositorioPanelPrincipal.php';
require_once __DIR__ . '/../../servicios/seguridad/ServicioCambioCuenta.php';
require_once __DIR__ . '/../../modelos/comunicaciones/ConfiguracionComunicaciones.php';
require_once __DIR__ . '/../../modelos/sunat/PadronSsco.php';

final class ControladorBarraSuperior
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function preparar(): array
    {
        $usuario = asesconUsuarioActual($this->conexion);
        $filtro = asesconFiltroClientesSql($this->conexion, 'c');
        $servicioCambio = new ServicioCambioCuenta($this->conexion);
        $sscoBarra = null;
        if (asesconTienePermiso($this->conexion, 'ALERTAS_SUNAT', 'ver')
            && (new ConfiguracionComunicaciones($this->conexion))->disponible()) {
            $sscoBarra = (new PadronSsco($this->conexion))->recientes();
        }

        return [
            'usuarioMostrar' => (string)$usuario['usuario'],
            'usuarioActualId' => (int)$usuario['id_usuario'],
            'rolNombreActual' => (string)$usuario['rol_nombre'],
            'tipoCuentaActual' => strtoupper((string)($usuario['tipo_cuenta'] ?? 'EQUIPO')),
            'sinLeerTop' => (new RepositorioPanelPrincipal($this->conexion))->sinLeerBuzonSol($filtro),
            'cuentasMenu' => $servicioCambio->opcionesParaBarra($usuario),
            'csrfCambiarCuenta' => asesconCsrf('csrf_cambiar_cuenta'),
            'sscoBarra' => $sscoBarra,
        ];
    }
}
