<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../configuracion/seguridad.php';
require_once __DIR__ . '/../../modelos/clientes/Cliente.php';
require_once __DIR__ . '/../../servicios/sunat/AutenticadorSunat.php';
require_once __DIR__ . '/../../servicios/sunat/NormalizadorCredencialesSunat.php';
require_once __DIR__ . '/../../servicios/sunat/ExcepcionSunat.php';

final class ControladorCliente
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(string $buscar = ''): array
    {
        asesconExigirPermiso($this->conexion, 'CLIENTES', 'ver');

        return (new Cliente($this->conexion))->listar(asesconFiltroClientesSql($this->conexion, 'c'), $buscar);
    }

    public function obtener(int $idCliente): ?array
    {
        asesconExigirPermiso($this->conexion, 'CLIENTES', 'ver');

        if ($idCliente <= 0 || !asesconPuedeVerCliente($this->conexion, $idCliente)) {
            return null;
        }
        $cliente = (new Cliente($this->conexion))->obtener($idCliente);
        if (!$cliente) {
            return null;
        }
        $stmt = $this->conexion->prepare('SELECT usuario_sol,client_id_sire,estado,estado_validacion,fecha_ultima_validacion,mensaje_ultima_validacion FROM credenciales_sunat WHERE id_cliente=? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $idCliente);
            $stmt->execute();
            $cliente['credenciales'] = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
        }
        return $cliente;
    }

    private function fichaRucSesion(string $ruc): ?array
    {
        $entry = $_SESSION['ficha_ruc_sunat'][$ruc] ?? null;
        if (!is_array($entry) || !is_array($entry['data'] ?? null)) {
            return null;
        }
        if ((time() - (int)($entry['time'] ?? 0)) > 1800) {
            unset($_SESSION['ficha_ruc_sunat'][$ruc]);
            return null;
        }
        $data = $entry['data'];
        if (trim((string)($data['ruc'] ?? '')) !== $ruc) {
            return null;
        }
        return $data;
    }

    private function mezclarFichaRucEnFormulario(array $post, array $f): array
    {
        $auto = [
            'razon_social' => (string)($f['razon_social'] ?? ''),
            'nombre_comercial' => (string)($f['nombre_comercial'] ?? ''),
            'tipo_contribuyente' => (string)($f['tipo_contribuyente'] ?? ''),
            'estado_ruc' => (string)($f['estado_ruc'] ?? ''),
            'condicion_ruc' => (string)($f['condicion_ruc'] ?? ''),
            'ubigeo' => (string)($f['ubigeo'] ?? ''),
            'domicilio_fiscal' => (string)($f['domicilio_fiscal'] ?? ''),
            'actividad_economica' => (string)($f['actividad_economica'] ?? ''),
            'fecha_inscripcion' => (string)($f['fecha_inscripcion'] ?? ''),
            'fecha_inicio_actividades' => (string)($f['fecha_inicio_actividades'] ?? ''),
            'fuente_datos_ruc' => (string)($f['fuente'] ?? 'SUNAT_RUC'),
            'fecha_consulta_ruc' => date('Y-m-d H:i:s'),
        ];
        foreach ($auto as $k => $v) {
            if (trim((string)$v) !== '') {
                $post[$k] = $v;
            }
        }
        return $post;
    }

    private function datosFormulario(array $post, ?array $existente = null): array
    {
        $ruc = preg_replace('/\D+/', '', (string)($post['ruc'] ?? ($existente['ruc'] ?? ''))) ?? '';
        if (!preg_match('/^[0-9]{11}$/', $ruc)) {
            throw new InvalidArgumentException('ruc');
        }
        $razon = trim((string)($post['razon_social'] ?? ($existente['razon_social'] ?? '')));
        if ($razon === '') {
            throw new InvalidArgumentException('razon_social');
        }
        $nullable = static fn($v)=>trim((string)$v) !== '' ? trim((string)$v) : null;
        $campos = [
            'nombre_comercial','nombre_contacto','tipo_contribuyente','estado_ruc','condicion_ruc','ubigeo','domicilio_fiscal',
            'actividad_economica','fecha_inscripcion','fecha_inicio_actividades','celular','correo',
            'fuente_datos_ruc','fecha_consulta_ruc','fecha_consulta_sol'
        ];
        $d = ['ruc' => $ruc,'razon_social' => $razon,'ultimo_digito_ruc' => substr($ruc, -1)];
        foreach ($campos as $campo) {
            $d[$campo] = $nullable($post[$campo] ?? ($existente[$campo] ?? ''));
        }
        if ($d['correo'] !== null && filter_var($d['correo'], FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('correo');
        }
        $d['regimen_tributario'] = $nullable($post['regimen_tributario'] ?? ($existente['regimen_tributario'] ?? ''));
        $d['tiene_plame'] = isset($post['tiene_plame']) ? 1 : 0;
        $d['presenta_sire'] = isset($post['presenta_sire']) ? 1 : 0;
        $d['tiene_afp'] = isset($post['tiene_afp']) ? 1 : 0;
        return $d;
    }

    private function sireValidadaEnSesion(array $credenciales): bool
    {
        $huella = NormalizadorCredencialesSunat::huellaSire($credenciales);
        $ts = (int)($_SESSION['sire_validaciones'][$huella] ?? 0);
        if ($ts <= 0 || (time() - $ts) > 900) {
            return false;
        }
        unset($_SESSION['sire_validaciones'][$huella]);
        return true;
    }

    private function guardarCredenciales(int $idCliente, string $ruc, array $post, bool $permitirVacio = true): void
    {
        $entrada = NormalizadorCredencialesSunat::normalizar([
            'ruc' => $ruc,
            'usuario_sol' => (string)($post['usuario_sol'] ?? ''),
            'clave_sol' => (string)($post['clave_sol'] ?? ''),
            'client_id_sunat' => (string)($post['client_id_sire'] ?? ''),
            'client_secret_sunat' => (string)($post['client_secret_sire'] ?? ''),
        ]);

        $stmt = $this->conexion->prepare('SELECT usuario_sol,clave_sol_cifrada,client_id_sire,client_secret_sire_cifrado FROM credenciales_sunat WHERE id_cliente=? LIMIT 1');
        $actual = null;
        if ($stmt) {
            $stmt->bind_param('i', $idCliente);
            $stmt->execute();
            $actual = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
        }

        if (!$actual && $permitirVacio
            && $entrada['usuario_sol'] === ''
            && $entrada['clave_sol'] === ''
            && $entrada['client_id_sunat'] === ''
            && $entrada['client_secret_sunat'] === '') {
            return;
        }

        $usuarioActual = trim((string)($actual['usuario_sol'] ?? ''));
        $clientIdActual = trim((string)($actual['client_id_sire'] ?? ''));

        $usuario = $entrada['usuario_sol'] !== '' ? $entrada['usuario_sol'] : $usuarioActual;
        if ($usuario === '') {
            throw new InvalidArgumentException('sol_incompleto');
        }

        $usuarioCambio = $actual !== null && $entrada['usuario_sol'] !== '' && $entrada['usuario_sol'] !== $usuarioActual;
        $claveCambio = $entrada['clave_sol'] !== '';
        $clientIdCambio = $actual !== null && $entrada['client_id_sunat'] !== '' && $entrada['client_id_sunat'] !== $clientIdActual;
        $secretCambio = $entrada['client_secret_sunat'] !== '';

        if ($actual !== null && !$usuarioCambio && !$claveCambio && !$clientIdCambio && !$secretCambio) {
            return;
        }

        if ($entrada['clave_sol'] !== '') {
            $claveSol = $entrada['clave_sol'];
            $claveCifrada = cifrarCredencialSunat($claveSol);
        } else {
            $claveCifrada = (string)($actual['clave_sol_cifrada'] ?? '');
            if ($claveCifrada === '') {
                throw new InvalidArgumentException('sol_incompleto');
            }
            $claveSol = (string)descifrarCredencialSunat($claveCifrada);
        }

        $clientId = $entrada['client_id_sunat'] !== '' ? $entrada['client_id_sunat'] : $clientIdActual;
        if ($clientIdCambio && $entrada['client_secret_sunat'] === '') {
            throw new InvalidArgumentException('sire_incompleto');
        }

        if ($entrada['client_secret_sunat'] !== '') {
            $clientSecret = $entrada['client_secret_sunat'];
            $secretCifrado = cifrarCredencialSunat($clientSecret);
        } else {
            $secretCifrado = (string)($actual['client_secret_sire_cifrado'] ?? '');
            $clientSecret = $secretCifrado !== '' ? (string)descifrarCredencialSunat($secretCifrado) : '';
        }

        if (($clientId === '') !== ($clientSecret === '')) {
            throw new InvalidArgumentException('sire_incompleto');
        }

        $estadoValidacion = 'SIN_CONFIGURAR';
        $fechaValidacion = null;
        $mensaje = 'SIRE no configurado. La consulta RUC/SOL funciona de forma independiente.';

        if ($clientId !== '' && $clientSecret !== '') {
            $credenciales = [
                'ruc' => $ruc,
                'usuario_sol' => $usuario,
                'clave_sol' => $claveSol,
                'client_id_sunat' => $clientId,
                'client_secret_sunat' => $clientSecret,
            ];
                                                                                       
                                                                                              
            $guardarSinValidar = !empty($post['guardar_sin_validar_sire']) && asesconEsPrincipal($this->conexion);
            if ($guardarSinValidar) {
                $estadoValidacion = 'PENDIENTE';
                $fechaValidacion = null;
                $mensaje = 'Credenciales recifradas y guardadas. Falta validación real contra la API SIRE.';
            } else {
                if (!$this->sireValidadaEnSesion($credenciales)) {
                    (new AutenticadorSunat())->generarTokenSire($credenciales);
                }
                $estadoValidacion = 'VALIDA';
                $fechaValidacion = date('Y-m-d H:i:s');
                $mensaje = 'Credenciales SIRE validadas correctamente.';
            }
        }

        $stmt = $this->conexion->prepare(
            'INSERT INTO credenciales_sunat
             (id_cliente,usuario_sol,clave_sol_cifrada,client_id_sire,client_secret_sire_cifrado,estado,estado_validacion,fecha_ultima_validacion,mensaje_ultima_validacion)
             VALUES (?,?,?,?,?,1,?,?,?)
             ON DUPLICATE KEY UPDATE
                usuario_sol=VALUES(usuario_sol),
                clave_sol_cifrada=VALUES(clave_sol_cifrada),
                client_id_sire=VALUES(client_id_sire),
                client_secret_sire_cifrado=VALUES(client_secret_sire_cifrado),
                estado=1,
                estado_validacion=VALUES(estado_validacion),
                fecha_ultima_validacion=VALUES(fecha_ultima_validacion),
                mensaje_ultima_validacion=VALUES(mensaje_ultima_validacion)'
        );
        if (!$stmt) {
            throw new RuntimeException('No se pudieron preparar las credenciales SUNAT.');
        }
        $stmt->bind_param('isssssss', $idCliente, $usuario, $claveCifrada, $clientId, $secretCifrado, $estadoValidacion, $fechaValidacion, $mensaje);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException($error);
        }
        $stmt->close();
    }

    public function guardar(array $post): void
    {
        if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_clientes')) {
            asesconRedirigir('vistas/clientes/gestion_clientes.php', ['error' => 'csrf']);
        }
        $id = (int)($post['id_cliente'] ?? 0);
        $creando = $id <= 0;
        $reactivando = !$creando && isset($post['reactivar']) && (string)$post['reactivar'] === '1';
        asesconExigirPermiso($this->conexion, 'CLIENTES', $creando ? 'crear' : 'editar');
        if (!$creando && !asesconPuedeGestionarCliente($this->conexion, $id)) {
            http_response_code(403);
            exit('No tienes permiso para modificar esta empresa.');
        }
        $model = new Cliente($this->conexion);
        $existente = $creando ? null : $model->obtener($id);
        if (!$creando && !$existente) {
            asesconRedirigir('vistas/clientes/gestion_clientes.php', ['error' => 'no_existe']);
        }

        if ($creando) {
            $rucPre = preg_replace('/\D+/', '', (string)($post['ruc'] ?? '')) ?? '';
            if (preg_match('/^[0-9]{11}$/', $rucPre) && $model->obtenerPorRuc($rucPre)) {
                asesconRedirigir('vistas/clientes/ficha_cliente.php', ['error' => 'ruc_duplicado']);
            }
        } else {
            $rucPre = (string)$existente['ruc'];
            $post['ruc'] = $rucPre;
        }

        $ficha = $this->fichaRucSesion($rucPre);
        if (($creando || $reactivando) && !$ficha) {
            $params = ['id' => $creando ? null : $id,'error' => 'ficha_ruc'];
            if ($reactivando) {
                $params['reactivar'] = 1;
            }
            asesconRedirigir('vistas/clientes/ficha_cliente.php', array_filter($params, fn($v)=>$v !== null));
        }
        if ($ficha) {
            $post = $this->mezclarFichaRucEnFormulario($post, $ficha);
        }
        try {
            $d = $this->datosFormulario($post, $existente);
        } catch (InvalidArgumentException $e) {
            asesconRedirigir('vistas/clientes/ficha_cliente.php', array_filter(['id' => $id ?: null,'error' => $e->getMessage()]));
        }
        $this->conexion->begin_transaction();
        try {
            if ($creando) {
                $id = $model->crear($d);
            } else {
                $model->actualizar($id, $d);
            }

            $this->guardarCredenciales($id, $d['ruc'], $post, !$creando && !$reactivando);

            if ($reactivando) {
                $model->cambiarEstado($id, 'ACTIVO');
                $stmt = $this->conexion->prepare("UPDATE credenciales_sunat SET estado=1 WHERE id_cliente=?");
                if ($stmt) {
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            if ($creando && !asesconEsPrincipal($this->conexion)) {
                $actor = (int)$_SESSION['id_usuario'];
                $stmt = $this->conexion->prepare('INSERT IGNORE INTO usuario_clientes(id_usuario,id_cliente,puede_ver,puede_gestionar,asignado_por) VALUES(?,?,1,1,?)');
                if ($stmt) {
                    $stmt->bind_param('iii', $actor, $id, $actor);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            $this->conexion->commit();
            if ($ficha) {
                unset($_SESSION['ficha_ruc_sunat'][$d['ruc']]);
            }
            asesconRegistrarAuditoria($this->conexion, $creando ? 'CREAR_CLIENTE' : 'EDITAR_CLIENTE', 'CLIENTES', $id, 'clientes', (string)$id, $d['ruc']);
            asesconRedirigir('vistas/clientes/gestion_clientes.php', ['guardado' => 1]);
        } catch (InvalidArgumentException $e) {
            $this->conexion->rollback();
            $params = ['id' => $creando ? null : $id,'error' => $e->getMessage()];
            if ($reactivando) {
                $params['reactivar'] = 1;
            }
            asesconRedirigir('vistas/clientes/ficha_cliente.php', array_filter($params, fn($v)=>$v !== null));
        } catch (ExcepcionSunat $e) {
            $this->conexion->rollback();
            error_log('SUNAT cliente: ' . $e->getMessage());
            $params = ['id' => $creando ? null : $id,'error' => 'sunat'];
            if ($reactivando) {
                $params['reactivar'] = 1;
            }
            asesconRedirigir('vistas/clientes/ficha_cliente.php', array_filter($params, fn($v)=>$v !== null));
        } catch (Throwable $e) {
            $this->conexion->rollback();
            error_log('ControladorCliente: ' . $e->getMessage());
            $params = ['id' => $creando ? null : $id,'error' => 'bd'];
            if ($reactivando) {
                $params['reactivar'] = 1;
            }
            asesconRedirigir('vistas/clientes/ficha_cliente.php', array_filter($params, fn($v)=>$v !== null));
        }
    }

    public function estado(array $post): void
    {
        if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_clientes')) {
            asesconRedirigir('vistas/clientes/gestion_clientes.php', ['error' => 'csrf']);
        }
        asesconExigirPermiso($this->conexion, 'CLIENTES', 'editar');
        $id = (int)($post['id_cliente'] ?? 0);
        if (!asesconPuedeGestionarCliente($this->conexion, $id)) {
            http_response_code(403);
            exit('Empresa no autorizada.');
        }
        $estado = strtoupper((string)($post['estado'] ?? ''));
        if ($estado === 'ACTIVO') {
            asesconRedirigir('vistas/clientes/ficha_cliente.php', ['id' => $id,'reactivar' => 1]);
        }
        (new Cliente($this->conexion))->cambiarEstado($id, 'INACTIVO');
        $stmt = $this->conexion->prepare("UPDATE credenciales_sunat SET estado=0,mensaje_ultima_validacion='Cliente inhabilitado en ASESCON. La configuración SIRE se conserva para una futura reactivación.' WHERE id_cliente=?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        asesconRegistrarAuditoria($this->conexion, 'INHABILITAR_CLIENTE', 'CLIENTES', $id, 'clientes', (string)$id, 'INACTIVO');
        asesconRedirigir('vistas/clientes/gestion_clientes.php', ['estado' => 1]);
    }

    public function eliminar(array $post): void
    {
        if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_clientes')) {
            asesconRedirigir('vistas/clientes/gestion_clientes.php', ['error' => 'csrf']);
        }
        asesconExigirPermiso($this->conexion, 'CLIENTES', 'eliminar');
        $id = (int)($post['id_cliente'] ?? 0);
        if ($id <= 0 || !asesconPuedeGestionarCliente($this->conexion, $id)) {
            http_response_code(403);
            exit('Empresa no autorizada.');
        }
        $model = new Cliente($this->conexion);
        $cliente = $model->obtener($id);
        if (!$cliente) {
            asesconRedirigir('vistas/clientes/gestion_clientes.php', ['error' => 'no_existe']);
        }
        $ruc = (string)$cliente['ruc'];
        $confirmacion = trim((string)($post['confirmacion'] ?? ''));
        if ($confirmacion !== 'ELIMINAR ' . $ruc) {
            asesconRedirigir('vistas/clientes/gestion_clientes.php', ['error' => 'confirmacion']);
        }

        $this->conexion->begin_transaction();
        try {
                                                                               
                                                                            
            foreach (['consultas_declaraciones_sunat','declaraciones_oficiales_sunat',
                      'tareas','buzon_sol_sunat','buzon_sunafil','alertas_sunat','cotizaciones'] as $tabla) {
                $stmt = $this->conexion->prepare("DELETE FROM {$tabla} WHERE id_cliente=?");
                if (!$stmt) {
                    throw new RuntimeException('No se pudo preparar la eliminación en ' . $tabla);
                }
                try {
                    $stmt->bind_param('i', $id);
                    if (!$stmt->execute()) {
                        throw new RuntimeException('No se pudieron borrar los registros de ' . $tabla . ': ' . $stmt->error);
                    }
                } finally {
                    $stmt->close();
                }
            }
            $stmt = $this->conexion->prepare('DELETE FROM clientes WHERE id_cliente=? LIMIT 1');
            if (!$stmt) {
                throw new RuntimeException('No se pudo preparar la eliminación del cliente.');
            }
            $stmt->bind_param('i', $id);
            if (!$stmt->execute()) {
                $e = $stmt->error;
                $stmt->close();
                throw new RuntimeException($e);
            }
            $stmt->close();
            $this->conexion->commit();
            asesconRegistrarAuditoria($this->conexion, 'ELIMINAR_CLIENTE', 'CLIENTES', null, 'clientes', null, 'RUC eliminado: ' . $ruc);
            asesconRedirigir('vistas/clientes/gestion_clientes.php', ['eliminado' => 1]);
        } catch (Throwable $e) {
            $this->conexion->rollback();
            error_log('Eliminar cliente: ' . $e->getMessage());
            asesconRedirigir('vistas/clientes/gestion_clientes.php', ['error' => 'eliminar']);
        }
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    $c = new ControladorCliente($conexion);
    $accion = (string)($_POST['accion'] ?? '');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'guardar') {
        $c->guardar($_POST);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'estado') {
        $c->estado($_POST);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'eliminar') {
        $c->eliminar($_POST);
    }
    asesconRedirigir('vistas/clientes/gestion_clientes.php');
}
