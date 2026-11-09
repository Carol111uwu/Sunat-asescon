<?php
declare(strict_types=1);
require_once __DIR__.'/../../modelos/seguridad/ContactoEquipo.php';
final class EquipoAsignableSol
{
    public function __construct(private mysqli $db){}
    public function listar():array
    {
        $contactos=new ContactoEquipo($this->db);
        $campos="COALESCE(NULLIF(e.nombre,''),u.usuario) nombre,COALESCE(NULLIF(e.correo,''),u.correo,'') correo";
        $join="LEFT JOIN equipo e ON e.id_equipo=(SELECT MAX(e2.id_equipo) FROM equipo e2 WHERE e2.id_usuario=u.id_usuario)";
        $sql="SELECT u.id_usuario,u.usuario,r.nombre rol,{$campos} FROM usuarios u INNER JOIN roles r ON r.id_rol=u.id_rol AND r.estado=1 INNER JOIN modulos m ON m.codigo='BUZON_SOL' AND m.estado=1 LEFT JOIN usuario_modulos um ON um.id_usuario=u.id_usuario AND um.id_modulo=m.id_modulo LEFT JOIN rol_modulos rm ON rm.id_rol=u.id_rol AND rm.id_modulo=m.id_modulo {$join} WHERE u.tipo_cuenta='EQUIPO' AND u.estado=1 AND COALESCE(um.puede_ver,rm.puede_ver,0)=1 ORDER BY nombre";
        $filas=$this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
        if($contactos->disponible())foreach($filas as &$fila){if($fila['correo']==='')$fila['correo']=$contactos->obtener((int)$fila['id_usuario'])['correo'];}
        return $filas;
    }
}
