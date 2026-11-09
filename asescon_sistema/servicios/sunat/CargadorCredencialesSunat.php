<?php

declare(strict_types=1);

require_once __DIR__ . '/ExcepcionSunat.php';
require_once dirname(__DIR__, 2) . '/configuracion/seguridad.php';

final class CargadorCredencialesSunat
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function obtenerSolPorCliente(int $id):array
    {
        $s=$this->conexion->prepare("SELECT c.id_cliente,c.ruc,c.razon_social,c.estado AS estado_cliente,cs.usuario_sol,cs.clave_sol_cifrada,cs.estado AS estado_credencial FROM clientes c INNER JOIN credenciales_sunat cs ON cs.id_cliente=c.id_cliente WHERE c.id_cliente=? LIMIT 1");
        $s->bind_param('i',$id);$s->execute();$d=$s->get_result()->fetch_assoc();$s->close();
        if(!$d||$d['estado_cliente']!=='ACTIVO'||(int)$d['estado_credencial']!==1)throw new DomainException('El cliente debe estar activo y tener sus credenciales SOL habilitadas.');
        $clave=descifrarCredencialSunat((string)$d['clave_sol_cifrada']);$usuario=trim((string)$d['usuario_sol']);
        if(str_starts_with($usuario,(string)$d['ruc'])&&strlen($usuario)>11)$usuario=substr($usuario,11);
        if(!$clave||$usuario==='')throw new DomainException('Falta el usuario o la clave SOL del cliente.');
        return ['id_cliente'=>(int)$d['id_cliente'],'ruc'=>(string)$d['ruc'],'cliente'=>(string)$d['razon_social'],'usuario_sol'=>$usuario,'clave_sol'=>$clave];
    }

    public function obtenerPorRuc(string $ruc): array
    {
        if (!preg_match('/^[0-9]{11}$/', $ruc)) {
            throw new ExcepcionSunat('El RUC debe contener exactamente 11 dígitos.');
        }

        $stmt = $this->conexion->prepare(
            "SELECT c.id_cliente,c.ruc,c.razon_social,c.estado AS estado_cliente,
                    cs.usuario_sol,cs.clave_sol_cifrada,cs.client_id_sire,cs.client_secret_sire_cifrado,
                    cs.estado AS estado_credencial,cs.estado_validacion
             FROM clientes c
             INNER JOIN credenciales_sunat cs ON cs.id_cliente=c.id_cliente
             WHERE c.ruc=? LIMIT 1"
        );
        if (!$stmt) {
            throw new ExcepcionSunat('No se pudo preparar la consulta de credenciales SUNAT.');
        }
        $stmt->bind_param('s', $ruc);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$fila) {
            throw new ExcepcionSunat('No existe un cliente registrado con ese RUC y credenciales SUNAT.');
        }
        if (strtoupper((string)$fila['estado_cliente']) !== 'ACTIVO') {
            throw new ExcepcionSunat('El cliente está inactivo en ASESCON.');
        }
        if ((int)$fila['estado_credencial'] !== 1) {
            throw new ExcepcionSunat('Las credenciales SUNAT del cliente están desactivadas.');
        }

        try {
            $claveSol = descifrarCredencialSunat((string)$fila['clave_sol_cifrada']);
            $clientSecret = descifrarCredencialSunat((string)($fila['client_secret_sire_cifrado'] ?? ''));
        } catch (Throwable $e) {
            throw new ExcepcionSunat(
                'Las credenciales SUNAT fueron cifradas con otra clave local. Vuelve a registrarlas para recuperar la conexión.',
                null,
                'CREDENCIALES_NO_DESCIFRABLES',
                $e
            );
        }

        return [
            'id_cliente' => (int)$fila['id_cliente'],
            'ruc' => (string)$fila['ruc'],
            'razon_social' => (string)$fila['razon_social'],
            'usuario_sol' => trim((string)$fila['usuario_sol']),
            'clave_sol' => (string)$claveSol,
            'client_id_sunat' => trim((string)($fila['client_id_sire'] ?? '')),
            'client_secret_sunat' => (string)$clientSecret,
        ];
    }
}
