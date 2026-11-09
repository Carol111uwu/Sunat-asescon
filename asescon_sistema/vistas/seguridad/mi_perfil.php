<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../controladores/seguridad/ControladorPerfilCuenta.php';

$datos = (new ControladorPerfilCuenta($conexion))->datosVista();
$perfil = $datos['perfil'];
$limites = $datos['limites'];
$csrfPerfil = $datos['csrfPerfil'];
$maximoCambios = (int)$datos['maximoCambios'];
$sinLimiteCambios = (bool)($datos['sinLimiteCambios'] ?? false);
$tipoCuenta = strtoupper((string)($perfil['tipo_cuenta'] ?? 'EQUIPO'));

$mensajesError = [
    'csrf' => 'La sesión del formulario venció. Actualiza la página e inténtalo nuevamente.',
    'usuario_formato' => 'El nombre de usuario debe tener entre 4 y 50 caracteres y usar letras, números, punto, guion o guion bajo.',
    'usuario_existe' => 'Ese nombre de usuario ya está en uso.',
    'correo_formato' => 'Ingresa un correo electrónico válido.',
    'correo_existe' => 'Ese correo ya está asociado a otra cuenta.',
    'correos_iguales' => 'El correo principal y el secundario deben ser diferentes.',
    'telefono_formato' => 'Ingresa un celular peruano válido de 9 dígitos.',
    'telefono_existe' => 'Ese número de celular ya está asociado a otra cuenta.',
    'password_actual' => 'La contraseña actual no es correcta.',
    'password' => 'La nueva contraseña debe tener entre 8 y 72 caracteres.',
    'password_confirmacion' => 'La nueva contraseña y su confirmación no coinciden.',
    'confirmacion_requerida' => 'Debes confirmar el cambio antes de guardarlo.',
    'sin_cambios' => 'No hay cambios nuevos para guardar.',
    'dato_duplicado' => 'Ese dato ya está siendo utilizado por otra cuenta.',
    'campo_no_valido' => 'Ese dato no está disponible para esta cuenta.',
    'bd' => 'No fue posible actualizar el perfil en este momento.',
];
$error = (string)($_GET['error'] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mi perfil - ASESCON</title>
    <link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/css/base/estructura_panel.css'); ?>">
    <link rel="stylesheet" href="../../publico/css/seguridad/perfil_cuenta.css?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/css/seguridad/perfil_cuenta.css'); ?>">
</head>
<body>
<div class="app">
    <?php require __DIR__ . '/../componentes/menu_lateral.php'; ?>
    <main class="main-content">
        <?php require __DIR__ . '/../componentes/barra_superior.php'; ?>
        <section class="content perfil-cuenta">
            <div class="page-header perfil-cuenta-cabecera">
                <div>
                    <h1>Mi perfil</h1>
                    <p>Datos de acceso y contacto de tu cuenta.</p>
                </div>
            </div>

            <?php if (isset($_GET['actualizado'])): ?><div class="perfil-mensaje exito">Los datos se actualizaron correctamente.</div><?php endif; ?>
            <?php if (isset($_GET['password'])): ?><div class="perfil-mensaje exito">La contraseña se actualizó correctamente.</div><?php endif; ?>
            <?php if ($error === 'limite_24h'): ?><div class="perfil-mensaje error">Este dato ya se cambió 3 veces en las últimas 24 horas. Podrás volver a modificarlo desde <?php echo htmlspecialchars((string)($_GET['desde'] ?? 'más adelante')); ?>.</div><?php elseif ($error !== ''): ?><div class="perfil-mensaje error"><?php echo htmlspecialchars($mensajesError[$error] ?? 'No fue posible realizar el cambio solicitado.'); ?></div><?php endif; ?>

            <section class="perfil-resumen">
                <div class="perfil-avatar-grande"><?php echo htmlspecialchars(strtoupper(substr((string)$perfil['usuario'], 0, 1))); ?></div>
                <div>
                    <strong><?php echo htmlspecialchars((string)$perfil['usuario']); ?></strong>
                    <span><?php echo htmlspecialchars((string)$perfil['rol_nombre']); ?></span>
                    <small><?php echo htmlspecialchars((string)$perfil['tipo_cuenta']); ?></small>
                </div>
            </section>

            <div class="perfil-grid">
                <?php
                $campos = [
                    ['usuario', 'Nombre de usuario', (string)$perfil['usuario'], 'text', false, ''],
                    ['correo', 'Correo principal', (string)($perfil['correo'] ?? ''), 'email', false, ''],
                ];
                if ($tipoCuenta !== 'CLIENTE') {
                    $campos[] = ['correo_secundario', 'Correo secundario', (string)($perfil['correo_secundario'] ?? ''), 'email', true, ''];
                }
                $telefonoGuardado = (string)($perfil['telefono'] ?? '');
                $telefonoNacional = preg_replace('/^\+51/', '', $telefonoGuardado) ?? $telefonoGuardado;
                $campos[] = ['telefono', 'Teléfono', $telefonoNacional, 'tel', true, '999999999'];

                foreach ($campos as [$campo, $titulo, $valor, $tipo, $opcional, $placeholder]):
                    $limite = $limites[$campo];
                ?>
                <form class="perfil-tarjeta perfil-formulario-confirmable" method="post" action="../../controladores/seguridad/ControladorPerfilCuenta.php" data-etiqueta="<?php echo htmlspecialchars($titulo); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfPerfil); ?>">
                    <input type="hidden" name="accion" value="actualizar_dato">
                    <input type="hidden" name="campo" value="<?php echo htmlspecialchars($campo); ?>">
                    <input type="hidden" name="confirmar_actualizacion" value="0" data-confirmacion-campo>
                    <div class="perfil-tarjeta-encabezado">
                        <div>
                            <h2><?php echo htmlspecialchars($titulo); ?></h2>
                            <?php if (!$sinLimiteCambios): ?>
                                <small><?php echo (int)$limite['restantes']; ?> de <?php echo $maximoCambios; ?> cambios disponibles en 24 h</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($campo === 'telefono'): ?>
                        <div class="perfil-control-telefono">
                            <span class="perfil-prefijo-telefono">+51</span>
                            <input class="perfil-input perfil-input-telefono" type="tel" name="valor" value="<?php echo htmlspecialchars($valor); ?>" maxlength="9" inputmode="numeric" pattern="9[0-9]{8}" placeholder="999999999" <?php echo $limite['bloqueado'] ? 'disabled' : ''; ?>>
                        </div>
                    <?php else: ?>
                        <input class="perfil-input" type="<?php echo $tipo; ?>" name="valor" value="<?php echo htmlspecialchars($valor); ?>" maxlength="180" <?php echo $placeholder !== '' ? 'placeholder="'.htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8').'"' : ''; ?> <?php echo !$opcional ? 'required' : ''; ?> <?php echo $limite['bloqueado'] ? 'disabled' : ''; ?>>
                    <?php endif; ?>
                    <?php if ($campo === 'correo'): ?><p class="perfil-ayuda">Este correo se usa para la recuperación de contraseña.</p><?php endif; ?>
                    <?php if ($campo === 'correo_secundario'): ?><p class="perfil-ayuda">Puedes dejarlo vacío. También puede usarse para recuperar tu cuenta.</p><?php endif; ?>
                    <?php if ($campo === 'telefono'): ?><p class="perfil-ayuda">Ingresa solo los 9 dígitos del celular. El sistema guardará el prefijo +51.</p><?php endif; ?>
                    <?php if ($limite['bloqueado']): ?><p class="perfil-bloqueo">Disponible nuevamente desde <?php echo htmlspecialchars((string)$limite['disponible_desde']); ?>.</p><?php else: ?><button class="perfil-boton" type="submit">Guardar cambio</button><?php endif; ?>
                </form>
                <?php endforeach; ?>

                <section class="perfil-tarjeta perfil-tarjeta-rol">
                    <div class="perfil-tarjeta-encabezado"><div><h2>Rol</h2><small>Permisos asignados a la cuenta</small></div></div>
                    <div class="perfil-valor-solo-lectura"><strong><?php echo htmlspecialchars((string)$perfil['rol_nombre']); ?></strong><span><?php echo htmlspecialchars((string)$perfil['rol_codigo']); ?></span></div>
                    <p class="perfil-ayuda">El rol no se modifica desde el perfil para evitar cambios de permisos accidentales.</p>
                </section>
            </div>

            <section class="perfil-password" id="contrasena">
                <div class="perfil-password-titulo"><h2>Contraseña y seguridad</h2><p>Cambia tu contraseña confirmando primero la actual.</p></div>
                <?php $limitePassword = $limites['password']; ?>
                <form class="perfil-formulario-password perfil-formulario-confirmable" method="post" action="../../controladores/seguridad/ControladorPerfilCuenta.php" data-etiqueta="contraseña">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfPerfil); ?>">
                    <input type="hidden" name="accion" value="cambiar_password">
                    <input type="hidden" name="confirmar_actualizacion" value="0" data-confirmacion-campo>
                    <label>Contraseña actual<input type="password" name="password_actual" autocomplete="current-password" required <?php echo $limitePassword['bloqueado'] ? 'disabled' : ''; ?>></label>
                    <label>Contraseña nueva<input type="password" name="password_nueva" autocomplete="new-password" minlength="8" maxlength="72" required <?php echo $limitePassword['bloqueado'] ? 'disabled' : ''; ?>></label>
                    <label>Confirmar contraseña nueva<input type="password" name="password_repetida" autocomplete="new-password" minlength="8" maxlength="72" required <?php echo $limitePassword['bloqueado'] ? 'disabled' : ''; ?>></label>
                    <div class="perfil-password-pie">
                        <?php if (!$sinLimiteCambios): ?><small><?php echo (int)$limitePassword['restantes']; ?> de <?php echo $maximoCambios; ?> cambios disponibles en 24 h</small><?php endif; ?>
                        <?php if ($limitePassword['bloqueado']): ?><span class="perfil-bloqueo">Disponible nuevamente desde <?php echo htmlspecialchars((string)$limitePassword['disponible_desde']); ?>.</span><?php else: ?><button class="perfil-boton" type="submit">Cambiar contraseña</button><?php endif; ?>
                    </div>
                </form>
            </section>

            <?php if (!$sinLimiteCambios): ?>
                <aside class="perfil-advertencia">
                    <strong>Importante</strong>
                    <p>Cada dato editable puede modificarse hasta <?php echo $maximoCambios; ?> veces dentro de un periodo de 24 horas. Si alcanzas el límite, el sistema bloqueará únicamente ese dato hasta que se cumplan 24 horas desde el primer cambio registrado.</p>
                </aside>
            <?php endif; ?>
        </section>
    </main>
</div>

<div class="perfil-modal" id="perfilModalConfirmacion" hidden>
    <div class="perfil-modal-fondo" data-cerrar-modal></div>
    <div class="perfil-modal-contenido" role="dialog" aria-modal="true" aria-labelledby="perfilModalTitulo">
        <h2 id="perfilModalTitulo">Confirmar actualización</h2>
        <p id="perfilModalTexto">¿Deseas guardar este cambio?</p>
        <div class="perfil-modal-acciones"><button type="button" class="perfil-boton secundario" data-cerrar-modal>Cancelar</button><button type="button" class="perfil-boton" id="perfilModalConfirmar">Sí, actualizar</button></div>
    </div>
</div>

<script src="../../publico/js/navegacion/menu_lateral.js"></script>
<script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script>
<script src="../../publico/js/navegacion/menu_movil.js"></script>
<script src="../../publico/js/interfaz/componentes_interfaz.js?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/js/interfaz/componentes_interfaz.js'); ?>"></script>
<script src="../../publico/js/seguridad/perfil_cuenta.js?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/js/seguridad/perfil_cuenta.js'); ?>"></script>
</body>
</html>
