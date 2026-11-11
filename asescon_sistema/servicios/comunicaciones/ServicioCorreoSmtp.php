<?php
declare(strict_types=1);
require_once __DIR__ . '/../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

final class ServicioCorreoSmtp
{
    public function enviar(string $destinatario, string $asunto, string $mensaje, string $remitente, string $copia = '', array $adjuntos = [], string $alternativo = ''): void
    {
        if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL) || ($copia !== '' && !filter_var($copia, FILTER_VALIDATE_EMAIL))) {
            throw new DomainException('El destinatario no tiene un correo válido.');
        }
        $config = require __DIR__ . '/../../configuracion/correo.php';
        $correo = new PHPMailer(true);
        $correo->isSMTP();
        $correo->Host = (string)$config['host'];
        $correo->SMTPAuth = true;
        $correo->Username = (string)$config['usuario'];
        $correo->Password = (string)$config['password'];
        $correo->Port = (int)$config['puerto'];
        $correo->SMTPSecure = $config['seguridad'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $correo->Timeout = 20;
        $correo->CharSet = 'UTF-8';
        $correo->setFrom((string)$config['usuario'], $remitente);
        $correo->addAddress($destinatario);
        if ($copia !== '' && strcasecmp($copia, $destinatario) !== 0) { $correo->addCC($copia); }
        $correo->Subject = $asunto;
        $correo->isHTML($alternativo !== '');
        $correo->Body = $mensaje;
        if ($alternativo !== '') { $correo->AltBody = $alternativo; }
        foreach ($adjuntos as $adjunto) {
            if (isset($adjunto['bytes'])) {
                $correo->addStringAttachment($adjunto['bytes'], $adjunto['nombre'], 'base64', $adjunto['tipo']);
            } else {
                $correo->addAttachment($adjunto['ruta'], $adjunto['nombre']);
            }
        }
        $correo->send();
    }
}
