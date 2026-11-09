<?php

declare(strict_types=1);

require_once __DIR__ . '/../configuracion/sesiones.php';
asesconIniciarSesion();

$token = trim((string)($_GET['token'] ?? ''));
$csrf = asesconCsrf('csrf_restablecer_contrasena');
$mensaje = isset($_SESSION['mensaje_restablecer']) ? (string)$_SESSION['mensaje_restablecer'] : '';
unset($_SESSION['mensaje_restablecer']);
$tokenValido = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva contraseña - ASESCON</title>
    <link rel="stylesheet" href="../publico/css/autenticacion/login.css?v=<?php echo (int)@filemtime(__DIR__ . '/../publico/css/autenticacion/login.css'); ?>">
</head>
<body class="pagina-inicio-sesion">
<main class="acceso-asescon">
    <header class="identidad-asescon">
        <img class="logo-asescon-oficial" src="../publico/img/autenticacion/logo-asescon-oficial.png?v=<?php echo (int)@filemtime(__DIR__ . '/../publico/img/autenticacion/logo-asescon-oficial.png'); ?>" alt="ASESCON - Contadores y Abogados">
    </header>
    <section class="tarjeta-inicio-sesion" aria-labelledby="titulo-restablecer">
        <header class="encabezado-inicio-sesion">
            <h1 id="titulo-restablecer">Nueva contraseña</h1>
            <p>Ingresa y confirma tu nueva contraseña.</p>
        </header>
        <?php if ($mensaje !== ''): ?>
            <div class="mensaje-error-acceso" role="alert"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($tokenValido): ?>
            <form class="formulario-inicio-sesion" action="../controladores/autenticacion/ControladorRestablecimientoContrasena.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="grupo-campo-acceso">
                    <label for="password">Nueva contraseña</label>
                    <div class="control-campo-acceso"><input type="password" id="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required></div>
                </div>
                <div class="grupo-campo-acceso">
                    <label for="password_confirmacion">Confirmar contraseña</label>
                    <div class="control-campo-acceso"><input type="password" id="password_confirmacion" name="password_confirmacion" autocomplete="new-password" minlength="8" maxlength="72" required></div>
                </div>
                <button type="submit" class="boton-iniciar-sesion"><span>Restablecer contraseña</span></button>
                <a href="inicio_sesion.php" class="enlace-recuperar-contrasena">Volver al inicio de sesión</a>
            </form>
        <?php else: ?>
            <div class="mensaje-error-acceso" role="alert">El enlace de recuperación no es válido.</div>
            <a href="recuperar_contrasena.php" class="enlace-recuperar-contrasena">Solicitar un nuevo enlace</a>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
