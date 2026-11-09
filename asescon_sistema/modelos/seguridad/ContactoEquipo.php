<?php
declare(strict_types=1);
final class ContactoEquipo
{
    public function __construct(private mysqli $db){}
    public function disponible(): bool {return $this->db->query("SHOW TABLES LIKE 'contactos_equipo_asescon'")->num_rows>0;}
    public function obtener(int $id): array
    {
        $s=$this->db->prepare("SELECT COALESCE(NULLIF(e.nombre,''),u.usuario) nombre,COALESCE(NULLIF(e.correo,''),u.correo,'') correo FROM usuarios u LEFT JOIN equipo e ON e.id_equipo=(SELECT MAX(e2.id_equipo) FROM equipo e2 WHERE e2.id_usuario=u.id_usuario) WHERE u.id_usuario=?");
        $s->bind_param('i',$id);$s->execute();$actual=$s->get_result()->fetch_assoc()?:['nombre'=>'','correo'=>''];
        if($this->disponible()) {
            $s=$this->db->prepare('SELECT nombre,correo FROM contactos_equipo_asescon WHERE id_usuario=?');$s->bind_param('i',$id);$s->execute();$previo=$s->get_result()->fetch_assoc();
            if($previo){if($actual['correo']==='')$actual['correo']=$previo['correo'];if($actual['nombre']==='')$actual['nombre']=$previo['nombre'];}
        }
        return $actual;
    }
    public function guardar(int $id,string $nombre,string $correo): void
    {
        $nombre=trim($nombre);$correo=trim($correo);
        if(mb_strlen($nombre)>120||strlen($correo)>120||($correo!==''&&!filter_var($correo,FILTER_VALIDATE_EMAIL)))throw new DomainException('Revisa el nombre y el correo del integrante (máximo 120 caracteres).');
        $this->db->begin_transaction();
        try {
            $s=$this->db->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario=? AND tipo_cuenta='EQUIPO' FOR UPDATE");$s->bind_param('i',$id);$s->execute();if(!$s->get_result()->fetch_assoc())throw new DomainException('El integrante no está disponible.');
            $s=$this->db->prepare('UPDATE usuarios SET correo=? WHERE id_usuario=?');$s->bind_param('si',$correo,$id);$s->execute();
            $s=$this->db->prepare('SELECT id_equipo FROM equipo WHERE id_usuario=? ORDER BY id_equipo DESC LIMIT 1');$s->bind_param('i',$id);$s->execute();$equipo=$s->get_result()->fetch_assoc();
            if($equipo){$eid=(int)$equipo['id_equipo'];$s=$this->db->prepare('UPDATE equipo SET nombre=?,correo=? WHERE id_equipo=?');$s->bind_param('ssi',$nombre,$correo,$eid);}
            else {$s=$this->db->prepare("INSERT INTO equipo(id_usuario,nombre,correo,estado) VALUES(?,?,?,'ACTIVO')");$s->bind_param('iss',$id,$nombre,$correo);}
            $s->execute();
            if($this->disponible()){$s=$this->db->prepare('UPDATE contactos_equipo_asescon SET nombre=?,correo=? WHERE id_usuario=?');$s->bind_param('ssi',$nombre,$correo,$id);$s->execute();}
            $this->db->commit();
        }catch(Throwable $e){$this->db->rollback();throw $e;}
    }
}
