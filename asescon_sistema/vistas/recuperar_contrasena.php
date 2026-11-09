<?php

declare(strict_types=1);

require_once __DIR__ . '/../configuracion/sesiones.php';
asesconIniciarSesion();

if (isset($_SESSION['id_usuario'])) {
    asesconRedirigir('vistas/panel_principal.php');
}

$csrf = asesconCsrf('csrf_recuperar_contrasena');
$mensaje = isset($_SESSION['mensaje_recuperacion']) ? (string)$_SESSION['mensaje_recuperacion'] : '';
$tipoMensaje = isset($_SESSION['tipo_mensaje_recuperacion']) ? (string)$_SESSION['tipo_mensaje_recuperacion'] : 'info';
$canalInicial = strtolower((string)($_GET['canal'] ?? 'correo')) === 'sms' ? 'sms' : 'correo';
unset($_SESSION['mensaje_recuperacion'], $_SESSION['tipo_mensaje_recuperacion']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña - ASESCON</title>
    <link rel="stylesheet" href="../publico/css/autenticacion/login.css?v=<?php echo (int)@filemtime(__DIR__ . '/../publico/css/autenticacion/login.css'); ?>">
    <link rel="stylesheet" href="../publico/css/autenticacion/recuperacion_contrasena.css?v=<?php echo (int)@filemtime(__DIR__ . '/../publico/css/autenticacion/recuperacion_contrasena.css'); ?>">
</head>
<body class="pagina-inicio-sesion">
<main class="acceso-asescon">
    <header class="identidad-asescon">
        <img class="logo-asescon-oficial" src="../publico/img/autenticacion/logo-asescon-oficial.png?v=<?php echo (int)@filemtime(__DIR__ . '/../publico/img/autenticacion/logo-asescon-oficial.png'); ?>" alt="ASESCON - Contadores y Abogados">
    </header>
    <section class="tarjeta-inicio-sesion tarjeta-recuperacion" aria-labelledby="titulo-recuperacion" data-canal-inicial="<?php echo htmlspecialchars($canalInicial, ENT_QUOTES, 'UTF-8'); ?>">
        <header class="encabezado-inicio-sesion">
            <h1 id="titulo-recuperacion">Recuperar contraseña</h1>
            <p>Elige cómo quieres verificar tu cuenta.</p>
            <small class="ayuda-recuperacion">Si recibiste un acceso de empresa desde «Mi equipo», solicita cualquier cambio de contraseña al cliente administrador de ASESCON.</small>
        </header>

        <?php if ($mensaje !== ''): ?>
            <div class="<?php echo $tipoMensaje === 'error' ? 'mensaje-error-acceso' : 'mensaje-informativo-acceso'; ?>" role="status"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="selector-recuperacion" role="tablist" aria-label="Método de recuperación">
            <button type="button" class="selector-recuperacion-opcion" data-canal-selector="correo" role="tab">Correo electrónico</button>
            <button type="button" class="selector-recuperacion-opcion" data-canal-selector="sms" role="tab">SMS</button>
        </div>

        <form class="formulario-inicio-sesion formulario-recuperacion" action="../controladores/autenticacion/ControladorSolicitudRecuperacionContrasena.php" method="POST" data-formulario-canal="correo">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="canal" value="correo">
            <div class="grupo-campo-acceso">
                <label for="correo">Correo electrónico</label>
                <div class="control-campo-acceso">
                    <input type="email" id="correo" name="correo" autocomplete="email" maxlength="180" placeholder="Ingrese su correo electrónico" required>
                </div>
            </div>
            <button type="submit" class="boton-iniciar-sesion"><span>Enviar enlace</span></button>
        </form>

        <form class="formulario-inicio-sesion formulario-recuperacion" action="../controladores/autenticacion/ControladorSolicitudRecuperacionContrasena.php" method="POST" data-formulario-canal="sms" hidden>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="canal" value="sms">
            <div class="grupo-campo-acceso">
                <label for="telefono">Número de celular</label>
                <div class="control-telefono-peru">
                    <span class="prefijo-telefono" aria-hidden="true">+51</span>
                    <input type="tel" id="telefono" name="telefono" inputmode="numeric" autocomplete="tel-national" maxlength="9" pattern="9[0-9]{8}" placeholder="999999999" aria-label="Número de celular de 9 dígitos" required>
                </div>
                <small class="ayuda-recuperacion">Usa el número registrado en tu perfil.</small>
            </div>
            <button type="submit" class="boton-iniciar-sesion"><span>Enviar código por SMS</span></button>
        </form>

        <a href="inicio_sesion.php" class="enlace-recuperar-contrasena">Volver al inicio de sesión</a>
    </section>
</main>
<script src="../publico/js/autenticacion/recuperacion_contrasena.js?v=<?php echo (int)@filemtime(__DIR__ . '/../publico/js/autenticacion/recuperacion_contrasena.js'); ?>"></script>
</body>
</html>
