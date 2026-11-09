<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/tareas/Tarea.php';
require_once __DIR__ . '/../../modelos/seguridad/Usuario.php';
require_once __DIR__.'/../../servicios/textos/ArchivoTextos.php';
final class ControladorTarea
{
    public function __construct(private mysqli $conexion)
    {
    }
    public function datosVista(): array
    {
        asesconExigirPermiso($this->conexion, 'TAREAS', 'ver');
        $actor = asesconUsuarioActual($this->conexion);

        $f = asesconFiltroClientesSql($this->conexion, 'c');
        $tareas = (new Tarea($this->conexion))->listar($f, (int)$actor['id_usuario'], asesconEsPrincipal($this->conexion));
        $clientes = [];
        $r = asesconClientesVisibles($this->conexion, true);
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $clientes[] = $x;
            }
        }
        $responsables = [];
        if (in_array($actor['rol_codigo'], ['MAIN','ADMIN_SECUNDARIO'], true)) {
            $model = new Usuario($this->conexion);
            $responsables = $model->listarGestionables((int)$actor['id_usuario'], asesconEsPrincipal($this->conexion));
        } else {
            $responsables = [['id_usuario' => $actor['id_usuario'],'usuario' => $actor['usuario'],'rol_nombre' => $actor['rol_nombre']]];
        }
        $seguimientosSol=[];
        if(asesconTienePermiso($this->conexion,'BUZON_SOL','ver')){
            require_once __DIR__.'/../../modelos/buzones/SeguimientoNotificacionesSol.php';
            try {
                $seguimientosSol=(new SeguimientoNotificacionesSol($this->conexion))->listarCasosVisibles(
                    $f,(int)$actor['id_usuario'],asesconEsPrincipal($this->conexion)
                );
            } catch(Throwable $e) { error_log('Tareas: seguimiento SOL no disponible: '.get_class($e)); }
        }
        return compact('actor', 'tareas', 'clientes', 'responsables','seguimientosSol');
    }
    public function procesar(array $post): void
    {
        if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_tareas')) {
            asesconRedirigir('vistas/tareas/tareas.php', ['error' => 'csrf']);
        }
$accion = (string)($post['accion'] ?? '');
        $model = new Tarea($this->conexion);
        if ($accion === 'crear') {
            asesconExigirPermiso($this->conexion, 'TAREAS', 'crear');
            $titulo = trim((string)($post['titulo'] ?? ''));
            if ($titulo === '') {
                asesconRedirigir('vistas/tareas/tareas.php', ['error' => 'titulo']);
            }
$idC = (int)($post['id_cliente'] ?? 0);
            $idU = (int)($post['id_usuario'] ?? 0);
            if ($idC > 0 && !asesconPuedeVerCliente($this->conexion, $idC)) {
                http_response_code(403);
                exit('Cliente no autorizado.');
            }
$actor = asesconUsuarioActual($this->conexion);
            if ($idU <= 0) {
                $idU = (int)$actor['id_usuario'];
            }
if ($idU !== (int)$actor['id_usuario'] && !asesconPuedeAdministrarUsuario($this->conexion, $idU)) {
                http_response_code(403);
                exit('Responsable no autorizado.');
            }
$id = $model->crear($idC ?: null, $idU ?: null, (int)$actor['id_usuario'], $titulo, trim((string)($post['descripcion'] ?? '')) ?: null, strtoupper((string)($post['prioridad'] ?? 'MEDIA')), trim((string)($post['fecha_inicio'] ?? '')) ?: null, trim((string)($post['fecha_vencimiento'] ?? '')) ?: null);
            asesconRegistrarAuditoria($this->conexion, 'CREAR_TAREA', 'TAREAS', $idC ?: null, 'tareas', (string)$id, $titulo);
            asesconRedirigir('vistas/tareas/tareas.php', ['guardado' => 1]);
        }
        if ($accion==='editar') {
            asesconExigirPermiso($this->conexion,'TAREAS','editar');
            $id=(int)($post['id_tarea']??0);
            $titulo=trim((string)($post['titulo']??''));
            $descripcion=trim((string)($post['descripcion']??''));
            $prioridad=strtoupper(trim((string)($post['prioridad']??'MEDIA')));
            $inicio=trim((string)($post['fecha_inicio']??''));
            $vence=trim((string)($post['fecha_vencimiento']??''));
            $largoTitulo=function_exists('mb_strlen')?mb_strlen($titulo,'UTF-8'):preg_match_all('/./us',$titulo);
            $largoDescripcion=function_exists('mb_strlen')?mb_strlen($descripcion,'UTF-8'):preg_match_all('/./us',$descripcion);
            if($id<1||$titulo===''||$largoTitulo===false||$largoDescripcion===false||$largoTitulo>180||$largoDescripcion>10000||!in_array($prioridad,['BAJA','MEDIA','ALTA'],true)){
                throw new DomainException('Los datos de la tarea no son válidos.');
            }
            foreach([$inicio,$vence] as $fecha){
                if($fecha==='')continue;
                $valida=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
                if(!$valida||$valida->format('Y-m-d')!==$fecha)throw new DomainException('La fecha de la tarea es inválida.');
            }
            $actor=asesconUsuarioActual($this->conexion);
            $model->editar($id,(int)$actor['id_usuario'],asesconEsPrincipal($this->conexion),asesconFiltroClientesSql($this->conexion,'c'),$titulo,$descripcion,$prioridad,$inicio,$vence);
            asesconRegistrarAuditoria($this->conexion,'EDITAR_TAREA','TAREAS',null,'tareas',(string)$id,'Corrección de tarea interna');
            asesconRedirigir('vistas/tareas/tareas.php',['editada'=>1]);
        }
        if (in_array($accion,['completar','reabrir'],true)) {
            asesconExigirPermiso($this->conexion, 'TAREAS', 'editar');
            $id = (int)($post['id_tarea'] ?? 0);
            if($id<1)throw new DomainException('Tarea inválida.');
            $actor = asesconUsuarioActual($this->conexion);
            $filtro=asesconFiltroClientesSql($this->conexion,'c');
            if($accion==='completar')$model->completar($id,(int)$actor['id_usuario'],asesconEsPrincipal($this->conexion),$filtro);
            else $model->reabrir($id,(int)$actor['id_usuario'],asesconEsPrincipal($this->conexion),$filtro);
            asesconRegistrarAuditoria($this->conexion,$accion==='completar'?'COMPLETAR_TAREA':'REABRIR_TAREA','TAREAS',null,'tareas',(string)$id,'Cambio de estado manual');
            asesconRedirigir('vistas/tareas/tareas.php', ['completada' => 1]);
        }
        if($accion==='eliminar'){
            asesconExigirPermiso($this->conexion,'TAREAS','eliminar');
            $id=(int)($post['id_tarea']??0);if($id<1)throw new DomainException('Tarea inválida.');
            $actor=asesconUsuarioActual($this->conexion);
            $model->eliminar($id,(int)$actor['id_usuario'],asesconEsPrincipal($this->conexion),asesconFiltroClientesSql($this->conexion,'c'));
            asesconRegistrarAuditoria($this->conexion,'ELIMINAR_TAREA','TAREAS',null,'tareas',(string)$id,'Tarea interna eliminada.');
            asesconRedirigir('vistas/tareas/tareas.php',['eliminada'=>1]);
        }
        if(in_array($accion,['archivar','restaurar'],true)){
            asesconExigirPermiso($this->conexion,'TAREAS','editar');
            $id=(int)($post['id_tarea']??0);$actor=asesconUsuarioActual($this->conexion);
            if($id<1||!$model->existeVisible($id,(int)$actor['id_usuario'],asesconEsPrincipal($this->conexion),asesconFiltroClientesSql($this->conexion,'c')))throw new DomainException('Tarea no disponible para archivar.');
            (new ArchivoTextos((int)$actor['id_usuario']))->cambiar('tarea',$id,$accion==='archivar');
            asesconRedirigir('vistas/tareas/tareas.php',['vista'=>$accion==='archivar'?'activas':'archivadas','archivo'=>1]);
        }
        asesconRedirigir('vistas/tareas/tareas.php', ['error' => 'accion']);
    }
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    try {
        (new ControladorTarea($conexion))->procesar($_POST);
    } catch (DomainException|InvalidArgumentException $e) {
        $_SESSION['tareas_error']=$e->getMessage();
        asesconRedirigir('vistas/tareas/tareas.php',['error'=>'gestion']);
    } catch (Throwable $e) {
        error_log('Gestión de tareas: '.get_class($e));
        $_SESSION['tareas_error']='No se pudo completar la operación. Revisa los permisos o el registro de errores.';
        asesconRedirigir('vistas/tareas/tareas.php',['error'=>'servidor']);
    }
}
