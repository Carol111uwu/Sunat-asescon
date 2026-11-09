<?php
declare(strict_types=1);

final class AdjuntosEntregables
{
    public static function validar(array $archivos): array
    {
        $nombres=$archivos['name']??[];
        if (!is_array($nombres) || count($nombres)>5) { throw new DomainException('Adjunta entre uno y cinco archivos.'); }
        $salida=[];$total=0;
        $permitidos=['pdf'=>['application/pdf'],'csv'=>['text/plain','text/csv','application/csv'],
            'xml'=>['text/xml','application/xml','text/plain'],
            'xlsx'=>['application/zip','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'zip'=>['application/zip','application/x-zip-compressed'],
            'png'=>['image/png'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg']];
        $lector=new finfo(FILEINFO_MIME_TYPE);
        foreach ($nombres as $i=>$nombre) {
            $error=$archivos['error'][$i]??UPLOAD_ERR_NO_FILE;
            if ($error===UPLOAD_ERR_NO_FILE) { continue; }
            $ruta=(string)($archivos['tmp_name'][$i]??'');
            if ($error!==UPLOAD_ERR_OK || !is_uploaded_file($ruta)) { throw new DomainException('Un adjunto no terminó de cargarse. Revisa el límite de carga del servidor.'); }
            $nombre=basename(str_replace('\\','/',(string)$nombre));
            if (preg_match('/[\r\n\x00]/',$nombre) || strlen($nombre)>240) { throw new DomainException('Revisa el nombre de los archivos adjuntos.'); }
            $extension=strtolower(pathinfo($nombre,PATHINFO_EXTENSION));$mime=$lector->file($ruta);
            if (!isset($permitidos[$extension]) || !in_array($mime,$permitidos[$extension],true)) { throw new DomainException('Adjunta solamente PDF, CSV, XML, XLSX, ZIP o imágenes PNG/JPG válidas.'); }
            $total+=(int)filesize($ruta);
            if ($total>10*1024*1024) { throw new DomainException('Los adjuntos deben sumar como máximo 10 MB.'); }
            $salida[]=['ruta'=>$ruta,'nombre'=>$nombre];
        }
        if (!$salida) { throw new DomainException('Adjunta los archivos que vas a enviar.'); }
        return $salida;
    }
}
