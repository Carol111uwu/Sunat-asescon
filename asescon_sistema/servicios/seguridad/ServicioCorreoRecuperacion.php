<?php

declare(strict_types=1);

require_once __DIR__ . '/../comunicaciones/ServicioCorreoSmtp.php';

final class ServicioCorreoRecuperacion
{
    public function enviar(string $destinatario, string $enlace): void
    {
        $enlaceSeguro = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');
        $mensaje = '<div style="font-family:Arial,sans-serif;line-height:1.6">'
            . '<h2 style="color:#0b2341">Recuperación de contraseña - ASESCON</h2>'
            . '<p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en ASESCON.</p>'
            . '<p>Para crear una nueva contraseña, haz clic en el siguiente botón:</p>'
            . '<p><a href="' . $enlaceSeguro . '" style="display:inline-block;padding:12px 20px;background:#0b2341;color:#ffffff;text-decoration:none;border-radius:6px">Restablecer contraseña</a></p>'
            . '<p>Este enlace será válido durante <strong>30 minutos</strong>.</p>'
            . '<p>Si tú no solicitaste este cambio, puedes ignorar este mensaje.</p>'
            . '<hr>'
            . '<p style="font-size:12px;color:#68778b">ASESCON - Sistema de gestión de obligaciones tributarias</p>'
            . '</div>';
        $alternativo = "Recuperación de contraseña - ASESCON\n\n"
            . "Para restablecer tu contraseña, utiliza este enlace:\n"
            . $enlace . "\n\n"
            . "El enlace será válido durante 30 minutos.";
        (new ServicioCorreoSmtp())->enviar($destinatario, 'Recuperación de contraseña - ASESCON', $mensaje, 'ASESCON', '', [], $alternativo);
    }
}
