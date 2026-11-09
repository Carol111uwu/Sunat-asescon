<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelos/panel/RepositorioPanelPrincipal.php';
require_once __DIR__ . '/../../servicios/seguridad/ServicioRecordatorioContrasena.php';

final class ControladorPanelPrincipal
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function preparar(): array
    {
        asesconExigirLogin();
        asesconExigirPermiso($this->conexion, 'DASHBOARD', 'ver');
        $filtro = asesconFiltroClientesSql($this->conexion, 'c');
        $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
        $repo = new RepositorioPanelPrincipal($this->conexion);
        $recordatorioContrasena = (new ServicioRecordatorioContrasena($this->conexion))->obtenerParaUsuarioActual();
        $resumen = $repo->resumen($filtro, $idUsuario);
        $hoy = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
        $dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        $meses = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $fechaTexto = $dias[(int)$hoy->format('w')] . ', ' . $hoy->format('d') . ' de ' . $meses[(int)$hoy->format('n')] . ' ' . $hoy->format('Y');

        $riesgoData = ['fecha_analisis'=>null,'clientes'=>[]];
        $rutaRiesgo = asesconRutaAlmacenamiento() . '/riesgo_tributario.json';
        if (is_file($rutaRiesgo)) {
            $datos = json_decode((string)file_get_contents($rutaRiesgo), true);
            if (is_array($datos)) $riesgoData = $datos;
        }
        $permitidos = $repo->idsClientesActivos($filtro);
        $clientesRiesgo = array_values(array_filter($riesgoData['clientes'] ?? [], static function(array $fila) use ($permitidos): bool {
            $id = (int)($fila['id_cliente'] ?? 0);
            return $id > 0 && isset($permitidos[$id]);
        }));
        $conteo = ['ALTO'=>0,'MEDIO'=>0,'BAJO'=>0];
        foreach ($clientesRiesgo as $fila) {
            $nivel = strtoupper((string)($fila['nivel'] ?? ''));
            if (isset($conteo[$nivel])) $conteo[$nivel]++;
        }
        return $resumen + [
            'moduloActivo'=>'DASHBOARD',
            'proximos'=>$repo->proximosVencimientos($filtro),
            'alertas'=>$repo->alertas($filtro),
            'prioridadesHoy'=>$repo->prioridadesHoy($filtro, $idUsuario),
            'fechaTexto'=>$fechaTexto,
            'riesgoData'=>$riesgoData,
            'clientesRiesgo'=>$clientesRiesgo,
            'totalRiesgoAlto'=>$conteo['ALTO'],
            'totalRiesgoMedio'=>$conteo['MEDIO'],
            'totalRiesgoBajo'=>$conteo['BAJO'],
            'recordatorioContrasena'=>$recordatorioContrasena,
            'csrfRecordatorioContrasena'=>asesconCsrf('csrf_recordatorio_contrasena'),
        ];
    }
}
