<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelos/seguridad/RepositorioPoliticaContrasena.php';

final class ServicioRecordatorioContrasena
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function obtenerParaUsuarioActual(): array
    {
        $usuario = asesconUsuarioActual($this->conexion);
        $repo = new RepositorioPoliticaContrasena($this->conexion);
        $estado = $repo->obtenerEstado((int)$usuario['id_usuario']);
        $diasObjetivo = $repo->diasRecordatorio();

        if (!$estado) {
            return ['mostrar' => false, 'dias' => 0, 'cada_dias' => $diasObjetivo];
        }

        $tipoCuenta = strtoupper((string)($estado['tipo_cuenta'] ?? 'EQUIPO'));
        if (!in_array($tipoCuenta, ['CLIENTE', 'CREADOR'], true)) {
            return ['mostrar' => false, 'dias' => 0, 'cada_dias' => $diasObjetivo];
        }

        $origen = (string)($estado['fecha_cambio_password'] ?: $estado['fecha_creacion']);
        $desdeCambio = $this->diasTranscurridos($origen);
        if ($desdeCambio < $diasObjetivo) {
            return ['mostrar' => false, 'dias' => $desdeCambio, 'cada_dias' => $diasObjetivo];
        }

        $ultimoAviso = (string)($estado['fecha_ultimo_aviso_password'] ?? '');
        if ($ultimoAviso !== '' && $this->diasTranscurridos($ultimoAviso) < $diasObjetivo) {
            return ['mostrar' => false, 'dias' => $desdeCambio, 'cada_dias' => $diasObjetivo];
        }

        return ['mostrar' => true, 'dias' => $desdeCambio, 'cada_dias' => $diasObjetivo];
    }

    public function descartarAvisoActual(): void
    {
        $usuario = asesconUsuarioActual($this->conexion);
        if (!asesconPuedeAutogestionarCredenciales($this->conexion)) {
            return;
        }
        (new RepositorioPoliticaContrasena($this->conexion))->registrarAvisoDescartado((int)$usuario['id_usuario']);
        asesconRegistrarAuditoria(
            $this->conexion,
            'DESCARTAR_RECORDATORIO_PASSWORD',
            'SEGURIDAD',
            null,
            'usuarios',
            (string)$usuario['id_usuario'],
            'Recordatorio de cambio de contraseña pospuesto'
        );
    }

    private function diasTranscurridos(string $fecha): int
    {
        try {
            $zona = new DateTimeZone('America/Lima');
            $inicio = new DateTimeImmutable($fecha, $zona);
            $hoy = new DateTimeImmutable('now', $zona);
            if ($inicio > $hoy) {
                return 0;
            }
            return (int)$inicio->diff($hoy)->days;
        } catch (Throwable) {
            return 0;
        }
    }
}
