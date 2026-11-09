<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelos/seguridad/RepositorioCambioCuenta.php';

final class ServicioCambioCuenta
{
    private RepositorioCambioCuenta $repositorio;

    public function __construct(private mysqli $conexion)
    {
        $this->repositorio = new RepositorioCambioCuenta($conexion);
    }

    public function opcionesParaBarra(array $actual): array
    {
        $tipoActual = strtoupper((string)($actual['tipo_cuenta'] ?? 'EQUIPO'));
        $opciones = [$this->normalizarOpcion($actual, true, false)];

        if ($tipoActual === 'CREADOR') {
            foreach ($this->repositorio->listarCuentasClienteActivas() as $cliente) {
                if ((int)$cliente['id_usuario'] === (int)$actual['id_usuario']) {
                    continue;
                }
                $opciones[] = $this->normalizarOpcion($cliente, false, true);
            }
            return $opciones;
        }

        if ($tipoActual === 'CLIENTE') {
            $creador = $this->creadorDeOrigenVigente();
            if ($creador !== null) {
                $opciones[] = $this->normalizarOpcion($creador, false, true);
            }
        }

        return $opciones;
    }

    public function cambiarA(int $idObjetivo): void
    {
        if ($idObjetivo <= 0) {
            throw new DomainException('cuenta_no_valida');
        }

        $actual = asesconUsuarioActual($this->conexion);
        $tipoActual = strtoupper((string)($actual['tipo_cuenta'] ?? 'EQUIPO'));

        if ($tipoActual === 'CREADOR') {
            $objetivo = $this->repositorio->obtenerCuentaActiva($idObjetivo);
            if ($objetivo === null || strtoupper((string)$objetivo['tipo_cuenta']) !== 'CLIENTE') {
                throw new DomainException('cuenta_no_autorizada');
            }

            $cuentaCreador = $this->repositorio->obtenerCuentaActiva((int)$actual['id_usuario']);
            if ($cuentaCreador === null || strtoupper((string)$cuentaCreador['tipo_cuenta']) !== 'CREADOR') {
                throw new DomainException('cuenta_no_autorizada');
            }

            $origen = [
                'id' => (int)$cuentaCreador['id_usuario'],
                'firma' => hash('sha256', (string)$cuentaCreador['password']),
            ];

            $this->repositorio->registrarCambioCuenta((int)$actual['id_usuario'], (int)$objetivo['id_usuario'], 'CREADOR_ENTRAR_CUENTA_CLIENTE');
            $this->establecerSesion($objetivo, $origen);
            return;
        }

        if ($tipoActual === 'CLIENTE') {
            $creador = $this->creadorDeOrigenVigente();
            if ($creador === null || (int)$creador['id_usuario'] !== $idObjetivo) {
                throw new DomainException('cuenta_no_autorizada');
            }

            $idActor = (int)$creador['id_usuario'];
            $this->repositorio->registrarCambioCuenta($idActor, (int)$actual['id_usuario'], 'CREADOR_VOLVER_DESDE_CLIENTE');
            $this->establecerSesion($creador, null);
            return;
        }

        throw new DomainException('cuenta_no_autorizada');
    }

    private function creadorDeOrigenVigente(): ?array
    {
        $idCreador = (int)($_SESSION['id_creador_origen'] ?? 0);
        $firmaGuardada = (string)($_SESSION['firma_creador_origen'] ?? '');
        if ($idCreador <= 0 || $firmaGuardada === '') {
            return null;
        }

        $creador = $this->repositorio->obtenerCuentaActiva($idCreador);
        if ($creador === null || strtoupper((string)$creador['tipo_cuenta']) !== 'CREADOR') {
            $this->limpiarOrigenCreador();
            return null;
        }

        $firmaActual = hash('sha256', (string)$creador['password']);
        if (!hash_equals($firmaGuardada, $firmaActual)) {
            $this->limpiarOrigenCreador();
            return null;
        }

        return $creador;
    }

    private function establecerSesion(array $cuenta, ?array $origenCreador): void
    {
        $_SESSION = [];
        session_regenerate_id(true);

        $_SESSION['firma_password'] = hash('sha256', (string)$cuenta['password']);
        $_SESSION['id_usuario'] = (int)$cuenta['id_usuario'];
        $_SESSION['usuario'] = (string)$cuenta['usuario'];
        $_SESSION['rol'] = (string)$cuenta['rol_codigo'];
        $_SESSION['rol_nombre'] = (string)$cuenta['rol_nombre'];
        $_SESSION['id_rol'] = (int)$cuenta['id_rol'];
        $_SESSION['es_principal'] = (int)($cuenta['es_principal'] ?? 0);
        $_SESSION['acceso_todos_clientes'] = (int)($cuenta['acceso_todos_clientes'] ?? 0);
        $_SESSION['autenticado'] = true;

        if ($origenCreador !== null) {
            $_SESSION['id_creador_origen'] = (int)$origenCreador['id'];
            $_SESSION['firma_creador_origen'] = (string)$origenCreador['firma'];
        }
    }

    private function limpiarOrigenCreador(): void
    {
        unset($_SESSION['id_creador_origen'], $_SESSION['firma_creador_origen']);
    }

    private function normalizarOpcion(array $cuenta, bool $actual, bool $puedeCambiar): array
    {
        return [
            'id_usuario' => (int)$cuenta['id_usuario'],
            'usuario' => (string)$cuenta['usuario'],
            'rol_nombre' => (string)($cuenta['rol_nombre'] ?? ''),
            'tipo_cuenta' => strtoupper((string)($cuenta['tipo_cuenta'] ?? 'EQUIPO')),
            'actual' => $actual,
            'puede_cambiar' => $puedeCambiar,
        ];
    }
}
