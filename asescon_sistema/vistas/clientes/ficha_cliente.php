<?php

declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../controladores/clientes/ControladorCliente.php';
$id=(int)($_GET['id']??0); $creando=$id<=0; $reactivando=!$creando && isset($_GET['reactivar']) && (string)$_GET['reactivar']==='1';
asesconExigirPermiso($conexion,'CLIENTES',$creando?'crear':'ver');
$controller=new ControladorCliente($conexion); $cliente=$creando?null:$controller->obtener($id);
if(!$creando&&!$cliente){http_response_code(404);exit('Cliente no encontrado o no autorizado.');}
$puedeEditar=$creando?asesconTienePermiso($conexion,'CLIENTES','crear'):(asesconTienePermiso($conexion,'CLIENTES','editar')&&asesconPuedeGestionarCliente($conexion,$id));
if($reactivando&&!$puedeEditar){http_response_code(403);exit('No tienes permiso para reactivar este cliente.');}
$moduloActivo='CLIENTES'; $csrf=asesconCsrf('csrf_clientes'); $csrfSire=asesconCsrf('csrf_sire'); $csrfFichaRuc=asesconCsrf('csrf_ficha_ruc');
function vcliente(?array $c,string $k):string{return htmlspecialchars((string)($c[$k]??''),ENT_QUOTES,'UTF-8');}
$error=(string)($_GET['error']??'');
$errores=[
 'sunat'=>'No se pudo validar la conexión SUNAT solicitada.',
 'sol_incompleto'=>'Para crear el cliente se requiere Usuario SOL y Clave SOL.',
 'sire_incompleto'=>'Para configurar SIRE debes ingresar juntos Client ID y Client Secret.',
 'ruc'=>'El RUC debe contener exactamente 11 dígitos.',
 'ruc_duplicado'=>'Ese RUC ya está registrado como cliente.',
 'razon_social'=>'Primero consulta los datos del RUC.',
 'correo'=>'El correo ingresado no tiene un formato válido.',
 'ficha_ruc'=>'Antes de guardar o reactivar debes consultar los datos del RUC.',
 'bd'=>'No se pudo guardar la información.'
];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="asescon-csrf-sire" content="<?php echo htmlspecialchars($csrfSire,ENT_QUOTES,'UTF-8');?>"><meta name="asescon-csrf-ficha-ruc" content="<?php echo htmlspecialchars($csrfFichaRuc,ENT_QUOTES,'UTF-8');?>"><title><?php echo $creando?'Crear':($reactivando?'Reactivar':'Ficha');?> cliente - ASESCON</title><link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=20261028_ui3"><link rel="stylesheet" href="../../publico/css/modulos/clientes.css"></head><body><div class="app">
<?php require __DIR__.'/../componentes/menu_lateral.php';?><main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?><section class="content">
<div class="page-header"><div><h1><?php echo $creando?'Crear cliente':($reactivando?'Reactivar cliente':'Ficha del cliente');?></h1><p><?php echo $creando?'Registra los datos tributarios del cliente y sus credenciales SUNAT.':($reactivando?'Vuelve a consultar el RUC antes de reactivar la empresa.':'Datos tributarios, contacto y credenciales SUNAT del cliente.');?></p></div><a class="mini-btn" href="gestion_clientes.php">Volver</a></div>
<?php if($error!==''):?><div class="notice bad"><?php echo htmlspecialchars($errores[$error]??('No se pudo completar la operación: '.$error));?></div><?php endif;?>
<form method="post" action="../../controladores/clientes/ControladorCliente.php" class="client-form" id="clienteForm" data-creando="<?php echo $creando?'1':'0';?>" data-reactivando="<?php echo $reactivando?'1':'0';?>" data-id-cliente="<?php echo $id;?>" data-puede-editar="<?php echo $puedeEditar?'1':'0';?>" data-ficha-ruc-ok="<?php echo (!$creando&&!$reactivando&&!empty($cliente['fecha_consulta_ruc']))?'1':'0';?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf);?>"><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id_cliente" value="<?php echo $id;?>"><?php if($reactivando):?><input type="hidden" name="reactivar" value="1"><?php endif;?>

<section class="panel form-card ficha-ruc-card"><div class="panel-header"><div class="panel-title"><span class="panel-icon">♙</span><h2>Contacto y datos tributarios</h2></div></div><div class="form-body">
<p class="form-note">Ingresa el RUC y las credenciales SOL de la empresa. El RUC se usa para consultar los datos tributarios disponibles; Usuario SOL y Clave SOL quedan guardados para los servicios que los necesiten. Nombre, correo y teléfono se registran manualmente.</p>
<div class="form-grid three">
<label>RUC *<input id="ruc" name="ruc" maxlength="11" inputmode="numeric" required pattern="[0-9]{11}" value="<?php echo vcliente($cliente,'ruc');?>" <?php echo !$creando?'readonly':'';?> placeholder="11 dígitos"></label>
<label>Usuario SOL *<input id="usuario_sol" name="usuario_sol" required value="<?php echo $reactivando?'':htmlspecialchars((string)($cliente['credenciales']['usuario_sol']??''));?>" <?php echo !$puedeEditar?'readonly':'';?> autocomplete="off"></label>
<label>Clave SOL *<input id="clave_sol" name="clave_sol" type="password" <?php echo ($creando||$reactivando)?'required':'';?> autocomplete="new-password" placeholder="<?php echo (!$creando&&!$reactivando)?'Dejar vacío para usar la guardada':'';?>" <?php echo !$puedeEditar?'readonly':'';?>></label>
</div>
<div class="auto-query-row"><div><strong>Datos automáticos del RUC</strong><p class="form-note">Razón social, estado, condición, ubigeo y domicilio fiscal.</p></div><button type="button" id="consultarRuc" class="mini-btn" <?php echo !$puedeEditar?'disabled':'';?>>Consultar RUC</button></div>
<div id="rucEstado" class="ruc-state muted"><?php echo (!$creando&&!empty($cliente['fecha_consulta_ruc']))?'Última consulta RUC: '.htmlspecialchars((string)$cliente['fecha_consulta_ruc']):'Pendiente de consulta.';?></div>
<div id="rucFuente" class="ruc-state muted" style="margin-top:6px">Fuente de datos: <?php echo htmlspecialchars((string)($cliente['fuente_datos_ruc'] ?? 'Pendiente de consulta')); ?>.</div>
<div class="ruc-state muted" style="margin-top:6px">SUNAFIL reutilizará este mismo RUC, Usuario SOL y Clave SOL cuando sincronices su buzón después de guardar el cliente. No se valida durante el alta para no hacerte esperar.</div>

<div class="form-grid three auto-data-grid" style="margin-top:16px">
<label>Razón social *<input id="razon_social" name="razon_social" required value="<?php echo vcliente($cliente,'razon_social');?>" readonly></label>
<label>Estado RUC<input id="estado_ruc" name="estado_ruc" value="<?php echo vcliente($cliente,'estado_ruc');?>" readonly></label>
<label>Condición RUC<input id="condicion_ruc" name="condicion_ruc" value="<?php echo vcliente($cliente,'condicion_ruc');?>" readonly></label>
<label>Ubigeo<input id="ubigeo" name="ubigeo" value="<?php echo vcliente($cliente,'ubigeo');?>" readonly></label>
<label class="span-2">Domicilio fiscal<input id="domicilio_fiscal" name="domicilio_fiscal" value="<?php echo vcliente($cliente,'domicilio_fiscal');?>" readonly></label>
</div>

<div class="tax-config-block" style="margin-top:18px">
<label class="tax-regime-label">Régimen tributario
<select id="regimen_tributario" name="regimen_tributario" <?php echo !$puedeEditar?'disabled':'';?>>
<?php $regActual=(string)($cliente['regimen_tributario']??''); foreach([''=>'Sin definir','NRUS'=>'Nuevo RUS (NRUS)','RER'=>'Régimen Especial de Renta (RER)','MYPE'=>'Régimen MYPE Tributario (RMT)','GENERAL'=>'Régimen General'] as $valor=>$texto): ?>
<option value="<?php echo htmlspecialchars($valor);?>" <?php echo $regActual===$valor?'selected':'';?>><?php echo htmlspecialchars($texto);?></option>
<?php endforeach;?>
</select>
</label>
<div class="tax-options-row">
<label class="tax-option"><input type="checkbox" name="tiene_plame" value="1" <?php echo !empty($cliente['tiene_plame'])?'checked':'';?> <?php echo !$puedeEditar?'disabled':'';?>> <span>Tiene Planilla (PLAME)</span></label>
<label class="tax-option"><input type="checkbox" name="presenta_sire" value="1" <?php echo !empty($cliente['presenta_sire'])?'checked':'';?> <?php echo !$puedeEditar?'disabled':'';?>> <span>Presenta SIRE (RVIE / RCE)</span></label>
<label class="tax-option"><input type="checkbox" name="tiene_afp" value="1" <?php echo !empty($cliente['tiene_afp'])?'checked':'';?> <?php echo !$puedeEditar?'disabled':'';?>> <span>Tiene trabajadores en AFP</span></label>
</div>
</div>

<div class="form-grid three" style="margin-top:18px">
<label>Nombre<input id="nombre_contacto" name="nombre_contacto" value="<?php echo vcliente($cliente,'nombre_contacto');?>" <?php echo !$puedeEditar?'readonly':'';?> placeholder="Nombre de contacto o referencia"></label>
<label>Correo<input id="correo" name="correo" type="email" value="<?php echo vcliente($cliente,'correo');?>" <?php echo !$puedeEditar?'readonly':'';?> placeholder="correo@empresa.com"></label>
<label>Teléfono<input id="celular" name="celular" value="<?php echo vcliente($cliente,'celular');?>" <?php echo !$puedeEditar?'readonly':'';?> placeholder="+51 999 999 999"></label>
</div>
<input type="hidden" id="nombre_comercial" name="nombre_comercial" value="<?php echo vcliente($cliente,'nombre_comercial');?>">
<input type="hidden" id="tipo_contribuyente" name="tipo_contribuyente" value="<?php echo vcliente($cliente,'tipo_contribuyente');?>">
<input type="hidden" id="actividad_economica" name="actividad_economica" value="<?php echo vcliente($cliente,'actividad_economica');?>">
<input type="hidden" id="fecha_inscripcion" name="fecha_inscripcion" value="<?php echo vcliente($cliente,'fecha_inscripcion');?>">
<input type="hidden" id="fecha_inicio_actividades" name="fecha_inicio_actividades" value="<?php echo vcliente($cliente,'fecha_inicio_actividades');?>">
<input type="hidden" id="fuente_datos_ruc" name="fuente_datos_ruc" value="<?php echo vcliente($cliente,'fuente_datos_ruc');?>">
<input type="hidden" id="fecha_consulta_ruc" name="fecha_consulta_ruc" value="<?php echo vcliente($cliente,'fecha_consulta_ruc');?>">
<input type="hidden" id="fecha_consulta_sol" name="fecha_consulta_sol" value="<?php echo vcliente($cliente,'fecha_consulta_sol');?>">
</div></section>

<section class="panel form-card sire-card"><div class="panel-header"><div class="panel-title"><span class="panel-icon">◉</span><h2>Credenciales API SUNAT · SIRE</h2></div></div><div class="form-body">
<p class="form-note">Opcional. Client ID y Client Secret se usan únicamente para SIRE (RVIE/RCE). No se usan para consultar los datos básicos del RUC.</p>
<div class="form-grid two">
<label>Client ID SIRE<input id="client_id_sire" name="client_id_sire" value="<?php echo $reactivando?'':htmlspecialchars((string)($cliente['credenciales']['client_id_sire']??''));?>" <?php echo !$puedeEditar?'readonly':'';?> autocomplete="off"></label>
<label>Client Secret SIRE<input id="client_secret_sire" name="client_secret_sire" type="password" autocomplete="new-password" placeholder="<?php echo (!$creando&&!$reactivando)?'Dejar vacío para no reemplazar':'';?>" <?php echo !$puedeEditar?'readonly':'';?>></label>
<div class="sire-connect-box"><button type="button" id="conectarSire" class="primary-btn" <?php echo !$puedeEditar?'disabled':'';?>>Validar SIRE</button><span id="sireEstado" class="connection-state muted"><?php echo (!$creando&&!$reactivando&&($cliente['credenciales']['estado_validacion']??'')==='VALIDA')?'SIRE guardado y validado.':'SIRE sin validar o no configurado.';?></span></div>
<?php if($puedeEditar&&asesconEsPrincipal($conexion)):?>
<label class="form-note" style="grid-column:1/-1"><input type="checkbox" name="guardar_sin_validar_sire" value="1"> Guardar las claves cifradas aunque SUNAT no permita validarlas ahora (quedarán marcadas como PENDIENTE, no como válidas). Para sustituir claves de una instalación anterior, introduce tanto Clave SOL como Client Secret en esta misma operación.</label>
<?php endif;?>
</div></div></section>

<?php if($puedeEditar):?><div class="form-actions"><a class="mini-btn" href="gestion_clientes.php">Cancelar</a><button class="primary-btn" type="submit"><?php echo $reactivando?'Reactivar cliente':'Guardar cliente';?></button></div><?php endif;?>
</form><footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer></section></main></div>
<script src="../../publico/js/clientes/respuesta_api.js?v=20260919"></script><script src="../../publico/js/navegacion/menu_lateral.js"></script><script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script><script src="../../publico/js/navegacion/menu_movil.js"></script><script src="../../publico/js/clientes/ficha_cliente.js"></script></body></html>
