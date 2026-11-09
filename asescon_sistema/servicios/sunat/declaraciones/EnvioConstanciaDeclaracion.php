<?php
declare(strict_types=1);
require_once __DIR__.'/../../comunicaciones/ServicioCorreoSmtp.php';
require_once __DIR__.'/../../../modelos/comunicaciones/ConfiguracionComunicaciones.php';
require_once __DIR__.'/../../../configuracion/seguridad.php';

final class EnvioConstanciaDeclaracion
{
    public function __construct(private mysqli $db) {}
    public function enviar(string $destinatario,array $archivo,array $declaracion): void
    {
        $modelo=new ConfiguracionComunicaciones($this->db);
        if (!$modelo->disponible()) throw new DomainException('Prepara la configuración de correo de ASESCON antes de enviar constancias.');
        $config=$modelo->obtener();
        $dir=dirname(rutaClaveMaestraAsescon()).'/envios_declaraciones';
        if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new DomainException('No se pudo preparar el adjunto privado.');
        $ruta=tempnam($dir,'constancia_');
        if ($ruta===false) throw new DomainException('No se pudo preparar el adjunto.');
        try {
            chmod($ruta,0600);
            if (file_put_contents($ruta,$archivo['body'],LOCK_EX)!==strlen($archivo['body'])) throw new DomainException('No se pudo guardar el adjunto temporal.');
            try {
                (new ServicioCorreoSmtp())->enviar($destinatario,'Constancia SUNAT '.$declaracion['codFor'].' — '.$declaracion['perTri'],
                    'Se adjunta la constancia oficial de SUNAT correspondiente al RUC '.$declaracion['numRuc'].', formulario '.$declaracion['codFor'].', orden '.$declaracion['numOrd'].'.',
                    (string)$config['nombre_remitente'],(string)$config['correo_copia'],[['ruta'=>$ruta,'nombre'=>$archivo['nombre']]]);
            } catch (Throwable) {throw new DomainException('El servidor SMTP no confirmó el envío. Revisa Configuración de correo antes de reintentar.');}
        } finally {unlink($ruta);}
    }
}
