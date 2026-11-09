<?php
declare(strict_types=1);

require_once __DIR__ . '/../comunicaciones/ServicioCorreoSmtp.php';

final class ServicioCorreoBuzonSol
{
    public function enviar(string $destinatario, string $asunto, string $mensaje, array $notificacion, ?string $adjunto = null): void
    {
        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#182b45">'
            . '<h2 style="color:#0b2341">Notificación del Buzón SOL · ASESCON</h2>'
            . '<p>' . nl2br($esc($mensaje)) . '</p><hr>'
            . '<p><strong>Cliente:</strong> ' . $esc((string)($notificacion['razon_social'] ?? 'Sin cliente')) . '</p>'
            . '<p><strong>RUC:</strong> ' . $esc((string)($notificacion['ruc'] ?? '')) . '</p>'
            . '<p><strong>Asunto:</strong> ' . $esc((string)($notificacion['asunto'] ?? '')) . '</p>'
            . '<p><strong>Tipo:</strong> ' . $esc((string)($notificacion['tipo_documento'] ?? '')) . '</p>'
            . '<p><strong>Fecha:</strong> ' . $esc((string)($notificacion['fecha_recepcion'] ?? $notificacion['fecha_documento'] ?? '')) . '</p>'
            . '<p><strong>Contenido recuperado de la bandeja:</strong><br>' . nl2br($esc((string)($notificacion['observaciones'] ?? ''))) . '</p>'
            . '<p style="font-size:12px;color:#68778b">El contenido incluido corresponde a los datos recuperados por ASESCON. Revise el Buzón SOL oficial para consultar el documento íntegro si no se adjunta.</p></div>';
        $alternativo = $mensaje . "\n\nCliente: " . (string)($notificacion['razon_social'] ?? '')
            . "\nRUC: " . (string)($notificacion['ruc'] ?? '') . "\nAsunto: " . (string)($notificacion['asunto'] ?? '')
            . "\nContenido recuperado: " . (string)($notificacion['observaciones'] ?? '');
        $adjuntos = $adjunto !== null ? [['ruta'=>$adjunto,'nombre'=>basename($adjunto)]] : [];
        (new ServicioCorreoSmtp())->enviar($destinatario,$asunto,$html,'ASESCON','',$adjuntos,$alternativo);
    }
}
