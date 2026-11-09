<?php

declare(strict_types=1);

require_once __DIR__ . '/../configuracion/sesiones.php';
asesconIniciarSesion();

if (isset($_SESSION['id_usuario'])) {
    asesconRedirigir('vistas/panel_principal.php');
}

$expiraSms = (int)($_SESSION['recuperacion_sms_expira_unix'] ?? 0);
$telefonoSesion = trim((string)($_SESSION['recuperacion_sms_telefono'] ?? ''));
if ($expiraSms < time() || $telefonoSesion === '') {
    unset($_SESSION['recuperacion_sms_usuario'], $_SESSION['recuperacion_sms_telefono'], $_SESSION['recuperacion_sms_intentos'], $_SESSION['recuperacion_sms_expira_unix'], $_SESSION['telefono_recuperacion_oculto']);
    $_SESSION['mensaje_recuperacion'] = 'El código SMS venció. Solicita uno nuevo.';
    $_SESSION['tipo_mensaje_recuperacion'] = 'error';
    asesconRedirigir('vistas/recuperar_contrasena.php', ['canal' => 'sms']);
}

$csrf = asesconCsrf('csrf_verificar_sms');
$mensaje = (string)($_SESSION['mensaje_codigo_sms'] ?? '');
$telefonoOculto = (string)($_SESSION['telefono_recuperacion_oculto'] ?? 'tu número registrado');
unset($_SESSION['mensaje_codigo_sms']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar SMS - ASESCON</title>
    <link rel="stylesheet" href="../publico/css/autenticacion/login.css?v=<?php echo (int)@filemtime(__DIR__ . '/../publico/css/autenticacion/login.css'); ?>">
    <link rel="stylesheet" href="../publico/css/autenticacion/recuperacion_contrasena.css?v=<?php echo (int)@filemtime(__DIR__ . '/../publico/css/autenticacion/recuperacion_contrasena.css'); ?>">
</head>
<body class="pagina-inicio-sesion">
<main class="acceso-asescon">
    <header class="identidad-asescon">
        <img class="logo-asescon-oficial" src="../publico/img/autenticacion/logo-asescon-oficial.png?v=<?php echo (int)@filemtime(__DIR__ . '/../publico/img/autenticacion/logo-asescon-oficial.png'); ?>" alt="ASESCON - Contadores y Abogados">
    </header>
    <section class="tarjeta-inicio-sesion tarjeta-recuperacion" aria-labelledby="titulo-codigo-sms">
        <header class="encabezado-inicio-sesion">
            <h1 id="titulo-codigo-sms">Verificar código</h1>
            <p>Enviamos un código de 6 dígitos a <?php echo htmlspecialchars($telefonoOculto, ENT_QUOTES, 'UTF-8'); ?>.</p>
        </header>
        <?php if ($mensaje !== ''): ?>
            <div class="mensaje-error-acceso" role="alert"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form class="formulario-inicio-sesion" action="../controladores/autenticacion/ControladorVerificacionSms.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="grupo-campo-acceso">
                <label for="codigo">Código SMS</label>
                <div class="control-campo-acceso">
                    <input class="codigo-sms" type="text" id="codigo" name="codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required>
                </div>
                <small class="ayuda-recuperacion">El código vence en 10 minutos y permite hasta 5 intentos.</small>
            </div>
            <button type="submit" class="boton-iniciar-sesion"><span>Verificar código</span></button>
            <a href="recuperar_contrasena.php?canal=sms" class="enlace-recuperar-contrasena">Solicitar otro código</a>
            <a href="inicio_sesion.php" class="enlace-recuperar-contrasena">Volver al inicio de sesión</a>
        </form>
    </section>
</main>
</body>
</html>
