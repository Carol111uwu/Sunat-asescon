<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelos/sunat/RepositorioLecturaSire.php';
require_once __DIR__ . '/../../modelos/sunat/RepositorioOperacionesSire.php';
require_once __DIR__ . '/../../servicios/sunat/AutenticadorSunat.php';
require_once __DIR__ . '/../../servicios/sunat/NormalizadorCredencialesSunat.php';
require_once __DIR__ . '/../../servicios/sunat/CargadorCredencialesSunat.php';

final class ControladorAutenticacionSire
{
    private RepositorioLecturaSire $lectura;
    private RepositorioOperacionesSire $operaciones;

    public function __construct(private mysqli $conexion)
    {
        $this->lectura = new RepositorioLecturaSire($conexion);
        $this->operaciones = new RepositorioOperacionesSire($conexion);
    }

    public function validar(array $input): array
    {
        $puedeConectar = asesconTienePermiso($this->conexion, 'SIRE', 'ejecutar')
            || asesconTienePermiso($this->conexion, 'CLIENTES', 'crear')
            || asesconTienePermiso($this->conexion, 'CLIENTES', 'editar');
        if (!$puedeConectar) {
            throw new RuntimeException('No tienes permiso para validar una conexión SIRE.', 403);
        }

        $idCliente = (int)($input['id_cliente'] ?? 0);
        $usarGuardadas = !empty($input['usar_guardadas']);
        $ruc = preg_replace('/\D+/', '', (string)($input['ruc'] ?? '')) ?? '';

        if ($usarGuardadas) {
            if ($idCliente <= 0 || !asesconPuedeVerCliente($this->conexion, $idCliente)) {
                throw new RuntimeException('Cliente no autorizado para validar SIRE.', 403);
            }
            $cliente = $this->operaciones->obtenerClienteActivo($idCliente);
            if (!$cliente) {
                throw new RuntimeException('Cliente no disponible.');
            }
            $ruc = (string)$cliente['ruc'];
            $guardadas = (new CargadorCredencialesSunat($this->conexion))->obtenerPorRuc($ruc);
            $input = [
                'ruc' => $guardadas['ruc'] ?? $ruc,
                'usuario_sol' => $guardadas['usuario_sol'] ?? '',
                'clave_sol' => $guardadas['clave_sol'] ?? '',
                'client_id_sunat' => $guardadas['client_id_sunat'] ?? '',
                'client_secret_sunat' => $guardadas['client_secret_sunat'] ?? '',
            ];
        } elseif ($idCliente > 0 && preg_match('/^[0-9]{11}$/', $ruc)) {
            if (!asesconPuedeVerCliente($this->conexion, $idCliente)) {
                throw new RuntimeException('No tienes permiso para consultar las credenciales de esta empresa.', 403);
            }
            $cliente = $this->lectura->obtenerCliente($idCliente);
            if (!$cliente || (string)$cliente['ruc'] !== $ruc) {
                throw new InvalidArgumentException('El RUC no corresponde a la empresa seleccionada.');
            }
            $faltan = trim((string)($input['usuario_sol'] ?? '')) === ''
                || (string)($input['clave_sol'] ?? '') === ''
                || trim((string)($input['client_id_sunat'] ?? '')) === ''
                || (string)($input['client_secret_sunat'] ?? '') === '';
            if ($faltan) {
                $guardadas = (new CargadorCredencialesSunat($this->conexion))->obtenerPorRuc($ruc);
                foreach (['usuario_sol','clave_sol','client_id_sunat','client_secret_sunat'] as $campo) {
                    if (trim((string)($input[$campo] ?? '')) === '') {
                        $input[$campo] = (string)($guardadas[$campo] ?? '');
                    }
                }
            }
        }

        $credenciales = NormalizadorCredencialesSunat::normalizar($input);
        $huella = NormalizadorCredencialesSunat::huellaSire($credenciales);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        try {
            $token = (new AutenticadorSunat())->generarTokenSire($credenciales);
            if ($usarGuardadas && $idCliente > 0) {
                $this->operaciones->actualizarEstadoValidacionCredencial($idCliente, 'VALIDA', 'Conexión SIRE validada correctamente desde Dashboard SIRE.');
            }
        } catch (ExcepcionSunat $e) {
            if ($usarGuardadas && $idCliente > 0) {
                $this->operaciones->actualizarEstadoValidacionCredencial($idCliente, 'ERROR', $e->getMessage());
            }
            throw $e;
        }

        asesconIniciarSesion();
        $_SESSION['sire_validaciones'] = is_array($_SESSION['sire_validaciones'] ?? null) ? $_SESSION['sire_validaciones'] : [];
        $ahora = time();
        foreach ($_SESSION['sire_validaciones'] as $clave => $marca) {
            if (($ahora - (int)$marca) > 900) unset($_SESSION['sire_validaciones'][$clave]);
        }
        $_SESSION['sire_validaciones'][$huella] = $ahora;

        return [
            'token_type' => $token['token_type'] ?? 'Bearer',
            'expires_in' => $token['expires_in'] ?? null,
            'scope' => $token['scope'] ?? null,
            'estado_validacion' => 'VALIDA',
        ];
    }
}
