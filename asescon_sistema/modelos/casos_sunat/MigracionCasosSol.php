<?php
declare(strict_types=1);
final class MigracionCasosSol
{
    public static function importar(mysqli $db): void
    {
        if($db->query("SELECT clave FROM sunat_casos_migraciones WHERE clave='seguimiento_anterior_v1'")->fetch_assoc()) return;
        $casos=$db->query("SHOW TABLES LIKE 'buzon_sol_casos'")->fetch_assoc()!==null;
        $notas=$db->query("SHOW TABLES LIKE 'buzon_sol_avances'")->fetch_assoc()!==null;
        $db->begin_transaction();
        try {
            $db->query("INSERT IGNORE INTO sunat_casos_migraciones(clave) VALUES('seguimiento_anterior_v1')");
            if($db->affected_rows===0){$db->commit();return;}
            if($casos) $db->query("INSERT IGNORE INTO sunat_casos_buzon(id_documento,id_cliente,id_responsable,responsable,cliente,ruc,asunto,prioridad,fecha_limite,estado,creado_por,modificado_por,creado_en,actualizado_en)
                SELECT a.id_documento,a.id_cliente,a.id_responsable,COALESCE(u.usuario,''),c.razon_social,c.ruc,LEFT(b.asunto,255),COALESCE(b.prioridad,'NORMAL'),NULL,a.estado,a.creado_por,a.modificado_por,a.creado_en,a.actualizado_en
                FROM buzon_sol_casos a INNER JOIN buzon_sol_sunat b ON b.id_documento=a.id_documento AND b.id_cliente=a.id_cliente INNER JOIN clientes c ON c.id_cliente=a.id_cliente LEFT JOIN usuarios u ON u.id_usuario=a.id_responsable");
            if($casos) $db->query("INSERT INTO sunat_casos_historial(id_documento,id_actor,actor,accion,detalle,creado_en)
                SELECT a.id_documento,a.creado_por,COALESCE(u.usuario,'Usuario anterior'),'IMPORTACION_ASIGNACION',JSON_OBJECT('id_responsable',a.id_responsable,'estado',a.estado),a.creado_en
                FROM buzon_sol_casos a INNER JOIN sunat_casos_buzon b ON b.id_documento=a.id_documento AND b.id_cliente=a.id_cliente LEFT JOIN usuarios u ON u.id_usuario=a.creado_por");
            if($notas) $db->query("INSERT IGNORE INTO sunat_casos_notas(id_documento,id_usuario,autor,contenido,clave,creado_en)
                SELECT a.id_documento,a.id_usuario,COALESCE(u.usuario,'Usuario anterior'),a.contenido,CONCAT('avance_anterior_',a.id_avance),a.creado_en
                FROM buzon_sol_avances a INNER JOIN buzon_sol_sunat b ON b.id_documento=a.id_documento AND b.id_cliente=a.id_cliente LEFT JOIN usuarios u ON u.id_usuario=a.id_usuario");
            $db->commit();
        } catch(Throwable $e) { $db->rollback();throw $e; }
    }
}
