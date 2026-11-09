<?php

declare(strict_types=1);
require_once __DIR__ . '/../configuracion/sesiones.php';

asesconIniciarSesion();

if (isset($_SESSION['id_usuario'])) {
    asesconRedirigir('vistas/panel_principal.php');
}

$csrf = asesconCsrf('csrf_login');
$mensajeLogin = isset($_SESSION['mensaje_login']) ? (string)$_SESSION['mensaje_login'] : '';
unset($_SESSION['mensaje_login']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de sesión - ASESCON</title>

    <link rel="stylesheet" href="../publico/css/autenticacion/login.css?v=<?php echo @filemtime(__DIR__ . '/../publico/css/autenticacion/login.css'); ?>">
</head>
<body class="pagina-inicio-sesion">

<main class="acceso-asescon">
    <header class="identidad-asescon">
        <img
            class="logo-asescon-oficial"
            src="../publico/img/autenticacion/logo-asescon-oficial.png?v=<?php echo @filemtime(__DIR__ . '/../publico/img/autenticacion/logo-asescon-oficial.png'); ?>"
            alt="ASESCON - Contadores y Abogados"
        >
    </header>

    <section class="tarjeta-inicio-sesion" aria-labelledby="titulo-inicio-sesion">
        <header class="encabezado-inicio-sesion">
            <h1 id="titulo-inicio-sesion">Bienvenido</h1>
            <p>Acceso al sistema</p>
        </header>

        <?php if ($mensajeLogin !== ''): ?>
            <div class="mensaje-informativo-acceso" role="status">
                <?php echo htmlspecialchars($mensajeLogin, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <?php if ($_GET['error'] === 'bloqueado'): ?>
                <div class="mensaje-error-acceso" role="alert">
                    Cuenta bloqueada temporalmente. Intente nuevamente en 15 minutos.
                </div>
            <?php elseif ($_GET['error'] === 'sesion'): ?>
                <div class="mensaje-error-acceso" role="alert">
                    La sesión ha terminado. Vuelve a iniciar sesión.
                </div>
            <?php else: ?>
                <div class="mensaje-error-acceso" role="alert">
                    Usuario o contraseña incorrectos.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form class="formulario-inicio-sesion" autocomplete="on" action="../controladores/autenticacion/ControladorInicioSesion.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="grupo-campo-acceso">
                <label for="usuario">Usuario</label>
                <div class="control-campo-acceso">
                    <img
                        class="icono-campo-acceso"
                        src="../publico/img/autenticacion/icono-usuario.svg"
                        alt=""
                        aria-hidden="true"
                    >
                    <input
                        type="text"
                        id="usuario"
                        name="usuario"
                        autocomplete="username"
                        maxlength="50"
                        placeholder="Ingrese su usuario"
                        required
                    >
                </div>
            </div>

            <div class="grupo-campo-acceso">
                <label for="password">Contraseña</label>
                <div class="control-campo-acceso">
                    <img
                        class="icono-campo-acceso"
                        src="../publico/img/autenticacion/icono-candado.svg"
                        alt=""
                        aria-hidden="true"
                    >
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        placeholder="Ingrese su contraseña"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="boton-iniciar-sesion">
                <img
                    class="icono-boton-acceso"
                    src="../publico/img/autenticacion/icono-ingreso.svg"
                    alt=""
                    aria-hidden="true"
                >
                <span>Iniciar sesión</span>
            </button>

            <a href="recuperar_contrasena.php" class="enlace-recuperar-contrasena">
                ¿Olvidaste tu contraseña?
            </a>
        </form>
    </section>
</main>

</body>
</html>
