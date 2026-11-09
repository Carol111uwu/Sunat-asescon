<?php

declare(strict_types=1);

final class RepositorioRecuperacionContrasena
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function buscarUsuarioActivoPorCorreo(string $correo): ?array
    {
        $stmt = $this->conexion->prepare(
            "SELECT id_usuario,usuario,correo,correo_secundario,telefono,tipo_cuenta
             FROM usuarios
             WHERE estado=1
               AND tipo_cuenta IN ('CLIENTE','CREADOR')
               AND (
                    LOWER(correo)=LOWER(?)
                    OR (tipo_cuenta='CREADOR' AND correo_secundario IS NOT NULL AND LOWER(correo_secundario)=LOWER(?))
               )
             LIMIT 1"
        );
        $stmt->bind_param('ss', $correo, $correo);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $fila;
    }

    public function buscarUsuarioActivoPorTelefono(string $telefono): ?array
    {
        $telefonoNormalizado = preg_replace('/\D+/', '', $telefono) ?? '';
        $stmt = $this->conexion->prepare(
            "SELECT id_usuario,usuario,correo,correo_secundario,telefono,tipo_cuenta
             FROM usuarios
             WHERE estado=1
               AND tipo_cuenta IN ('CLIENTE','CREADOR')
               AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono,'+',''),' ',''),'-',''),'(',''),')','')=?
             LIMIT 1"
        );
        $stmt->bind_param('s', $telefonoNormalizado);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $fila;
    }

    public function reemplazarToken(int $idUsuario, string $tokenHash, string $expiraEn): void
    {
        $this->eliminarTokensDeUsuario($idUsuario);
        $stmt = $this->conexion->prepare(
            'INSERT INTO recuperacion_password (id_usuario,token_hash,expira_en) VALUES (?,?,?)'
        );
        $stmt->bind_param('iss', $idUsuario, $tokenHash, $expiraEn);
        $stmt->execute();
        $stmt->close();
    }

    public function obtenerTokenVigente(string $tokenHash): ?array
    {
        $stmt = $this->conexion->prepare(
            "SELECT rp.id_recuperacion,rp.id_usuario FROM recuperacion_password rp INNER JOIN usuarios u ON u.id_usuario=rp.id_usuario WHERE rp.token_hash=? AND rp.usado_en IS NULL AND rp.expira_en>=NOW() AND u.estado=1 AND u.tipo_cuenta IN ('CLIENTE','CREADOR') LIMIT 1"
        );
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $fila;
    }

    public function obtenerTokenVigenteParaActualizar(string $tokenHash): ?array
    {
        $stmt = $this->conexion->prepare(
            "SELECT rp.id_recuperacion,rp.id_usuario FROM recuperacion_password rp INNER JOIN usuarios u ON u.id_usuario=rp.id_usuario WHERE rp.token_hash=? AND rp.usado_en IS NULL AND rp.expira_en>=NOW() AND u.estado=1 AND u.tipo_cuenta IN ('CLIENTE','CREADOR') LIMIT 1 FOR UPDATE"
        );
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $fila;
    }

    public function marcarUsado(int $idRecuperacion): void
    {
        $stmt = $this->conexion->prepare(
            'UPDATE recuperacion_password SET usado_en=NOW() WHERE id_recuperacion=? LIMIT 1'
        );
        $stmt->bind_param('i', $idRecuperacion);
        $stmt->execute();
        $stmt->close();
    }

    public function eliminarTokensDeUsuario(int $idUsuario): void
    {
        $stmt = $this->conexion->prepare('DELETE FROM recuperacion_password WHERE id_usuario=?');
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $stmt->close();
    }
}
