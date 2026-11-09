<?php

declare(strict_types=1);

final class RepositorioOperacionesSire
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function obtenerClienteActivo(int $idCliente): ?array
    {
        $stmt = $this->conexion->prepare(
            "SELECT id_cliente,ruc,razon_social FROM clientes WHERE id_cliente=? AND estado='ACTIVO' LIMIT 1"
        );
        $stmt->bind_param('i', $idCliente);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $fila;
    }

    public function actualizarEstadoValidacionCredencial(int $idCliente, string $estado, string $mensaje): void
    {
        $mensaje = function_exists('mb_substr') ? mb_substr($mensaje, 0, 480, 'UTF-8') : substr($mensaje, 0, 480);
        $stmt = $this->conexion->prepare(
            'UPDATE credenciales_sunat SET estado_validacion=?,fecha_ultima_validacion=NOW(),mensaje_ultima_validacion=? WHERE id_cliente=?'
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ssi', $estado, $mensaje, $idCliente);
        $stmt->execute();
        $stmt->close();
    }

    public function obtenerTicketEnProceso(int $idCliente, string $periodo, string $libro): ?array
    {
        $stmt = $this->conexion->prepare(
            "SELECT id_descarga,numero_ticket,estado_ticket,fecha_solicitud
             FROM sire_descargas
             WHERE id_cliente=? AND periodo=? AND tipo_libro=?
               AND numero_ticket IS NOT NULL AND numero_ticket<>''
               AND estado_procesamiento IN ('PENDIENTE','PROCESANDO')
               AND fecha_solicitud >= (NOW() - INTERVAL 15 MINUTE)
             ORDER BY id_descarga DESC LIMIT 1"
        );
        $stmt->bind_param('iss', $idCliente, $periodo, $libro);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $fila;
    }

                                                                                       
    public function obtenerTicketReciente(int $idCliente, string $periodo, string $libro): ?array
    {
        $stmt = $this->conexion->prepare(
            "SELECT numero_ticket,estado_ticket,estado_procesamiento,cantidad_registros,fecha_solicitud
             FROM sire_descargas
             WHERE id_cliente=? AND periodo=? AND tipo_libro=?
               AND numero_ticket IS NOT NULL AND numero_ticket<>''
               AND fecha_solicitud >= (NOW() - INTERVAL 30 MINUTE)
             ORDER BY id_descarga DESC LIMIT 1"
        );
        $stmt->bind_param('iss', $idCliente, $periodo, $libro);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $fila;
    }

    public function registrarTicket(int $idCliente, string $periodo, string $libro, string $ticket): int
    {
        $stmt = $this->conexion->prepare(
            "INSERT INTO sire_descargas
            (id_cliente,periodo,tipo_libro,numero_ticket,estado_ticket,fecha_solicitud,estado_procesamiento)
            VALUES(?,?,?,?,'EN PROCESO',NOW(),'PENDIENTE')"
        );
        $stmt->bind_param('isss', $idCliente, $periodo, $libro, $ticket);
        $stmt->execute();
        $id = (int)$this->conexion->insert_id;
        $stmt->close();
        return $id;
    }

    public function existeTicket(int $idCliente, string $periodo, string $libro, string $ticket): bool
    {
        $stmt = $this->conexion->prepare(
            'SELECT 1 FROM sire_descargas
             WHERE id_cliente=? AND periodo=? AND tipo_libro=? AND numero_ticket=?
             LIMIT 1'
        );
        $stmt->bind_param('isss', $idCliente, $periodo, $libro, $ticket);
        $stmt->execute();
        $existe = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        return $existe;
    }

    public function actualizarEstado(
        int $idCliente,
        string $periodo,
        string $libro,
        string $ticket,
        string $estado
    ): void {
        $stmt = $this->conexion->prepare(
            'UPDATE sire_descargas SET estado_ticket=?
             WHERE id_cliente=? AND periodo=? AND tipo_libro=? AND numero_ticket=?'
        );
        $stmt->bind_param('sisss', $estado, $idCliente, $periodo, $libro, $ticket);
        $stmt->execute();
        $stmt->close();
    }

    public function marcarDescarga(
        int $idCliente,
        string $periodo,
        string $libro,
        string $ticket,
        array $guardado,
        int $cantidad,
        array $mapeo = []
    ): void {
        $zip = isset($guardado['zip']) ? (string)$guardado['zip'] : null;
        $datos = isset($guardado['archivos'][0]) ? (string)$guardado['archivos'][0] : null;
        $nomZip = $zip ? basename($zip) : null;
        $nomDat = $datos ? basename($datos) : null;
        $hashZip = trim((string)($guardado['hash_zip'] ?? '')) ?: null;
        $hashDatos = trim((string)($guardado['hash_archivos'][0] ?? '')) ?: null;
        $mensaje = $cantidad === 0
            ? 'Propuesta SIRE descargada y validada, sin comprobantes. No acredita presentación oficial.'
            : 'Archivo real descargado e importado desde SIRE.';
        if ((int)($mapeo['omitidas'] ?? 0) > 0) {
            $mensaje .= ' Filas omitidas/no compatibles: ' . (int)$mapeo['omitidas'] . '.';
        }

        $stmt = $this->conexion->prepare(
            "UPDATE sire_descargas SET
             estado_ticket='TERMINADO',nombre_zip=?,nombre_datos=?,ruta_zip=?,ruta_datos=?,
             hash_zip=?,hash_datos=?,cantidad_registros=?,fecha_descarga=NOW(),fecha_procesamiento=NOW(),
             estado_procesamiento='PROCESADO',mensaje=?
             WHERE id_cliente=? AND periodo=? AND tipo_libro=? AND numero_ticket=?
             ORDER BY id_descarga DESC LIMIT 1"
        );
        $stmt->bind_param(
            'ssssssisisss',
            $nomZip,
            $nomDat,
            $zip,
            $datos,
            $hashZip,
            $hashDatos,
            $cantidad,
            $mensaje,
            $idCliente,
            $periodo,
            $libro,
            $ticket
        );
        $stmt->execute();
        $stmt->close();
    }
}
