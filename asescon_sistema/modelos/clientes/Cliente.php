<?php

declare(strict_types=1);
require_once __DIR__ . '/../../servicios/clientes/BusquedaClientes.php';

final class Cliente
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(string $filtroSql, string $buscar = ''): array
    {
        $busqueda = BusquedaClientes::condicionSql($buscar, ['c.ruc','c.razon_social','c.nombre_contacto','c.nombre_comercial','c.celular','c.correo']);
        $extra = $busqueda['sql'] !== '' ? ' AND (' . $busqueda['sql'] . ')' : '';
        $stmt = $this->conexion->prepare("SELECT c.*, CASE WHEN cs.id_credencial_sunat IS NOT NULL AND TRIM(cs.usuario_sol)<>'' AND TRIM(cs.clave_sol_cifrada)<>'' THEN 1 ELSE 0 END AS tiene_sol_configurado, COALESCE(cs.estado_validacion,'SIN_CONFIGURAR') AS estado_sire FROM clientes c LEFT JOIN credenciales_sunat cs ON cs.id_cliente=c.id_cliente WHERE {$filtroSql}{$extra} ORDER BY (c.estado='ACTIVO') DESC,c.razon_social,c.ruc");
        if (!$stmt) { throw new RuntimeException('No se pudo preparar la búsqueda de clientes.'); }
        $parametros = $busqueda['parametros'];
        if ($parametros !== []) { $stmt->bind_param(str_repeat('s', count($parametros)), ...$parametros); }
        if (!$stmt->execute()) { throw new RuntimeException('No se pudo consultar los clientes.'); }
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        $stmt->close();
        return $rows;
    }

    public function obtener(int $idCliente): ?array
    {
        $stmt = $this->conexion->prepare('SELECT * FROM clientes WHERE id_cliente=? LIMIT 1');
        if (!$stmt) {
            return null;
        } $stmt->bind_param('i', $idCliente);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $r;
    }

    public function obtenerPorRuc(string $ruc): ?array
    {
        $stmt = $this->conexion->prepare('SELECT * FROM clientes WHERE ruc=? LIMIT 1');
        if (!$stmt) {
            return null;
        } $stmt->bind_param('s', $ruc);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $r;
    }

    public function crear(array $d): int
    {
        $sql = "INSERT INTO clientes (
            ruc,razon_social,nombre_comercial,nombre_contacto,tipo_contribuyente,ultimo_digito_ruc,estado,
            estado_ruc,condicion_ruc,ubigeo,domicilio_fiscal,actividad_economica,
            fecha_inscripcion,fecha_inicio_actividades,celular,correo,regimen_tributario,
            tiene_plame,presenta_sire,tiene_afp,fuente_datos_ruc,fecha_consulta_ruc,fecha_consulta_sol
        ) VALUES (?,?,?,?,?,?,'ACTIVO',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $this->conexion->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar el registro del cliente.');
        }
        $vals = [
            $d['ruc'],$d['razon_social'],$d['nombre_comercial'],$d['nombre_contacto'],$d['tipo_contribuyente'],$d['ultimo_digito_ruc'],
            $d['estado_ruc'],$d['condicion_ruc'],$d['ubigeo'],$d['domicilio_fiscal'],$d['actividad_economica'],
            $d['fecha_inscripcion'],$d['fecha_inicio_actividades'],$d['celular'],$d['correo'],$d['regimen_tributario'],
            $d['tiene_plame'],$d['presenta_sire'],$d['tiene_afp'],$d['fuente_datos_ruc'],
            $d['fecha_consulta_ruc'],$d['fecha_consulta_sol']
        ];
        $types = 'ssssssssssssssssiiisss';
        $refs = [];
        foreach ($vals as $k => &$v) {
            $refs[$k]=&$v;
        } $stmt->bind_param($types, ...$refs);
        if (!$stmt->execute()) {
            $e = $stmt->error;
            $stmt->close();
            throw new RuntimeException($e);
        }
        $id = (int)$this->conexion->insert_id;
        $stmt->close();
        return $id;
    }

    public function actualizar(int $idCliente, array $d): void
    {
        $campos = [
            'razon_social','nombre_comercial','nombre_contacto','tipo_contribuyente','estado_ruc','condicion_ruc','ubigeo','domicilio_fiscal',
            'actividad_economica','fecha_inscripcion','fecha_inicio_actividades','celular','correo','regimen_tributario',
            'tiene_plame','presenta_sire','tiene_afp','fuente_datos_ruc','fecha_consulta_ruc','fecha_consulta_sol'
        ];
        $sets = implode(',', array_map(fn($c)=>$c . '=?', $campos));
        $stmt = $this->conexion->prepare("UPDATE clientes SET {$sets} WHERE id_cliente=? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar la actualización del cliente.');
        }
        $values = [];
        foreach ($campos as $c) {
            $values[] = $d[$c] ?? null;
        } $values[] = $idCliente;
        $types = '';
        foreach ($campos as $c) {
            $types .= in_array($c, ['tiene_plame','presenta_sire','tiene_afp'], true) ? 'i' : 's';
        } $types .= 'i';
        $refs = [];
        foreach ($values as $k => &$v) {
            $refs[$k]=&$v;
        } $stmt->bind_param($types, ...$refs);
        if (!$stmt->execute()) {
            $e = $stmt->error;
            $stmt->close();
            throw new RuntimeException($e);
        }
        $stmt->close();
    }

    public function cambiarEstado(int $idCliente, string $estado): void
    {
        $estado = strtoupper($estado) === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO';
        $sql = $estado === 'ACTIVO' ? "UPDATE clientes SET estado='ACTIVO',fecha_inactivacion=NULL,fecha_reactivacion=NOW() WHERE id_cliente=?" : "UPDATE clientes SET estado='INACTIVO',fecha_inactivacion=NOW() WHERE id_cliente=?";
        $stmt = $this->conexion->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('No se pudo cambiar el estado del cliente.');
        } $stmt->bind_param('i', $idCliente);
        if (!$stmt->execute()) {
            $e = $stmt->error;
            $stmt->close();
            throw new RuntimeException($e);
        } $stmt->close();
    }
}
