<?php

declare(strict_types=1);

require_once __DIR__ . '/rutas.php';

function asesconIniciarSesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
                                                                          
                                                                        
    $divisor = filter_var(ini_get('session.gc_divisor'), FILTER_VALIDATE_INT);
    if ($divisor === false || $divisor < 1) {
        ini_set('session.gc_divisor', '1000');
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('ASESCON_' . substr(hash('sha256', asesconBaseUrl()), 0, 12));
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || filter_var(getenv('ASESCON_FORCE_HTTPS') ?: false, FILTER_VALIDATE_BOOL);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => asesconBaseUrl() . '/',
        'httponly' => true,
        'secure' => $https,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function asesconCerrarSesion(): void
{
    asesconIniciarSesion();
    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_destroy();
}

function asesconExigirLogin(): void
{
    asesconIniciarSesion();
    if (empty($_SESSION['id_usuario'])) {
        asesconRedirigir('vistas/inicio_sesion.php');
    }
}

function asesconCsrf(string $clave = 'csrf_general'): string
{
    asesconIniciarSesion();
    if (empty($_SESSION[$clave]) || !is_string($_SESSION[$clave])) {
        $_SESSION[$clave] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$clave];
}

function asesconValidarCsrf(string $token, string $clave = 'csrf_general'): bool
{
    asesconIniciarSesion();
    $guardado = $_SESSION[$clave] ?? '';
    return is_string($guardado) && $guardado !== '' && hash_equals($guardado, $token);
}
