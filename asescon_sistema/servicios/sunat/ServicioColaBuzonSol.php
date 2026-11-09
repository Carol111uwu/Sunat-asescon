<?php

declare(strict_types=1);
require_once dirname(__DIR__,2).'/configuracion/rutas_privadas.php';

final class ServicioColaBuzonSol
{
    private string $directorioBase;
    private string $directorioSolicitudes;
    private string $directorioEstados;
    private string $directorioTemporales;
    private string $archivoBloqueo;

    public function __construct(?string $directorioBase = null)
    {
        $raizProyecto = dirname(__DIR__, 2);
        $this->directorioBase = $directorioBase
            ?? asesconRutaAlmacenamiento() . DIRECTORY_SEPARATOR . 'sunat' . DIRECTORY_SEPARATOR . 'buzon_sol';
        $this->directorioSolicitudes = $this->directorioBase . DIRECTORY_SEPARATOR . 'solicitudes';
        $this->directorioEstados = $this->directorioBase . DIRECTORY_SEPARATOR . 'estados';
        $this->directorioTemporales = $this->directorioBase . DIRECTORY_SEPARATOR . 'temporales';
        $this->archivoBloqueo = $this->directorioBase . DIRECTORY_SEPARATOR . 'cola.lock';
        $this->prepararDirectorios();
    }

    public function solicitar(int $idCliente, int $idUsuario, ?int $idDocumento = null, bool $soloAdjuntos = false, string $modoDocumento = 'PDF'): array
    {
        if ($idCliente <= 0) {
            throw new InvalidArgumentException('El cliente seleccionado no es válido.');
        }
        if (!in_array($modoDocumento, ['PDF','DETALLE'], true) || ($modoDocumento==='DETALLE' && ($idDocumento??0)<1)) {
            throw new InvalidArgumentException('La operación solicitada para el documento no es válida.');
        }

        $bloqueo = fopen($this->archivoBloqueo, 'c+');
        if ($bloqueo === false) {
            throw new RuntimeException('No se pudo bloquear la cola del Buzón SOL.');
        }

        try {
            if (!flock($bloqueo, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear la cola del Buzón SOL.');
            }

            $estadoActual = $this->leerEstadoCliente($idCliente);
            if ($this->estadoSigueActivo($estadoActual)) {
                if((int)($estadoActual['id_documento']??0)!==(int)($idDocumento??0) || (string)($estadoActual['modo_documento']??'PDF')!==$modoDocumento)throw new DomainException('Este cliente tiene otra consulta en proceso. Espera a que termine antes de solicitar el documento.');
                return $estadoActual + ['reutilizada' => true];
            }

            $this->descartarPendientesAntiguas($idCliente);

            $idTrabajo = date('YmdHis') . '-' . bin2hex(random_bytes(6));
            $ahora = date(DATE_ATOM);
            $solicitud = [
                'id_trabajo' => $idTrabajo,
                'id_cliente' => $idCliente,
                'id_usuario' => max(0, $idUsuario),
                'id_documento' => $idDocumento !== null ? max(0,$idDocumento) : 0,
                'solo_adjuntos' => $soloAdjuntos,
                'modo_documento' => $modoDocumento,
                'precargar_adjuntos' => $soloAdjuntos,
                'solicitado_en' => $ahora,
            ];

            $rutaSolicitud = $this->rutaSolicitudPendiente($idTrabajo);
            $this->escribirJsonAtomico($rutaSolicitud, $solicitud);

            $estado = [
                'id_trabajo' => $idTrabajo,
                'id_cliente' => $idCliente,
                'estado' => 'PENDIENTE',
                'mensaje' => $idDocumento ? ($modoDocumento==='DETALLE'?'Consulta del contenido oficial solicitada.':'Descarga solicitada. El PDF estará disponible cuando termine la consulta.') : 'Consulta pendiente.',
                'solicitado_en' => $ahora,
                'actualizado_en' => $ahora,
                'total' => 0,
                'nuevos' => 0,
                'id_documento' => $idDocumento !== null ? max(0,$idDocumento) : 0,
                'modo_documento' => $modoDocumento,
            ];
            $this->escribirEstadoCliente($idCliente, $estado);

            return $estado + ['reutilizada' => false];
        } finally {
            @flock($bloqueo, LOCK_UN);
            fclose($bloqueo);
        }
    }

    public function obtenerEstadoCliente(int $idCliente): array
    {
        if ($idCliente <= 0) {
            return [];
        }
        $estado=$this->leerEstadoCliente($idCliente);
        if(($estado['estado']??'')==='PROCESANDO'&&!$this->estadoSigueActivo($estado)){
            $this->fallar($idCliente,(string)$estado['id_trabajo'],'El conector se interrumpió. Se puede reintentar sin borrar los datos anteriores.');
            return $this->leerEstadoCliente($idCliente);
        }
        if(($estado['estado']??'')==='PENDIENTE'&&!$this->estadoSigueActivo($estado)){
            return array_replace($estado,[
                'estado'=>'ERROR',
                'mensaje'=>'La consulta lleva demasiado tiempo en cola sin un procesador activo. Revisa Cron y vuelve a pulsar Consultar; los registros anteriores están intactos.',
            ]);
        }
        return $estado;
    }

    public function tomarSiguienteSolicitud(): ?array
    {
        $archivos = glob($this->directorioSolicitudes . DIRECTORY_SEPARATOR . 'pendiente_*.json') ?: [];
        usort($archivos, static fn(string $a, string $b): int => (filemtime($a) ?: 0) <=> (filemtime($b) ?: 0));

        foreach ($archivos as $rutaPendiente) {
            $nombre = basename($rutaPendiente);
            $idTrabajo = preg_replace('/^pendiente_|\.json$/', '', $nombre) ?? '';
            if ($idTrabajo === '') {
                @unlink($rutaPendiente);
                continue;
            }

            $rutaProcesando = $this->rutaSolicitudProcesando($idTrabajo);
            if (!@rename($rutaPendiente, $rutaProcesando)) {
                continue;
            }

            $solicitud = $this->leerJson($rutaProcesando);
            $idCliente = (int)($solicitud['id_cliente'] ?? 0);
            if ($idCliente <= 0 || (string)($solicitud['id_trabajo'] ?? '') !== $idTrabajo) {
                @unlink($rutaProcesando);
                continue;
            }

            $estadoActual = $this->leerEstadoCliente($idCliente);
            if (($estadoActual['id_trabajo'] ?? '') !== $idTrabajo || ($estadoActual['estado'] ?? '') !== 'PENDIENTE') {
                @unlink($rutaProcesando);
                continue;
            }

            $this->actualizarEstado($idCliente, $idTrabajo, 'PROCESANDO', 'Consultando SUNAT…');
            $solicitud['ruta_procesando'] = $rutaProcesando;
            return $solicitud;
        }

        return null;
    }

    public function completar(int $idCliente, string $idTrabajo, int $total, int $nuevos): void
    {
        $this->actualizarEstado(
            $idCliente,
            $idTrabajo,
            'COMPLETADO',
            'Buzón SOL sincronizado correctamente.',
            ['total' => max(0, $total), 'nuevos' => max(0, $nuevos), 'finalizado_en' => date(DATE_ATOM)]
        );
    }

    public function completarAdjuntos(int $idCliente,string $idTrabajo,int $guardados,int $pendientes):void
    {
        $this->actualizarEstado($idCliente,$idTrabajo,$pendientes>0?'ADJUNTOS_PENDIENTES':'COMPLETADO',
            $pendientes>0?'Bandeja guardada. Quedan PDF pendientes de obtener.':'Bandeja y PDF disponibles.',
            ['pdf_guardados'=>$guardados,'pdf_pendientes'=>$pendientes,'finalizado_en'=>date(DATE_ATOM)]);
    }

    public function completarDocumento(int $idCliente,string $idTrabajo):void
    {
        $this->actualizarEstado($idCliente,$idTrabajo,'COMPLETADO','PDF descargado. Ya puedes abrirlo o guardarlo.',['finalizado_en'=>date(DATE_ATOM)]);
    }

    public function completarDetalle(int $idCliente,string $idTrabajo):void
    {
        $this->actualizarEstado($idCliente,$idTrabajo,'COMPLETADO','Contenido oficial consultado. Puedes abrir la notificación para verlo.',['finalizado_en'=>date(DATE_ATOM)]);
    }

    public function fallar(int $idCliente, string $idTrabajo, string $mensaje): void
    {
        $mensaje = trim(preg_replace('/\s+/', ' ', $mensaje) ?? '');
        if ($mensaje === '') {
            $mensaje = 'No se pudo completar la sincronización del Buzón SOL.';
        }
        if (strlen($mensaje) > 240) {
            $mensaje = substr($mensaje, 0, 240) . '…';
        }

        $this->actualizarEstado(
            $idCliente,
            $idTrabajo,
            'ERROR',
            $mensaje,
            ['finalizado_en' => date(DATE_ATOM)]
        );
    }

    public function rutaEntradaTemporal(string $idTrabajo): string
    {
        return $this->directorioTemporales . DIRECTORY_SEPARATOR . 'entrada_' . $this->limpiarId($idTrabajo) . '.json';
    }

    public function rutaProgresoTemporal(string $idTrabajo): string
    {
        return $this->directorioTemporales . DIRECTORY_SEPARATOR . 'progreso_' . $this->limpiarId($idTrabajo) . '.json';
    }

    public function rutaResultadoTemporal(string $idTrabajo): string
    {
        return $this->directorioTemporales . DIRECTORY_SEPARATOR . 'resultado_' . $this->limpiarId($idTrabajo) . '.json';
    }

    public function rutaSolicitudProcesando(string $idTrabajo): string
    {
        return $this->directorioSolicitudes . DIRECTORY_SEPARATOR . 'procesando_' . $this->limpiarId($idTrabajo) . '.json';
    }

    public function limpiarTrabajo(string $idTrabajo): void
    {
        foreach ([
            $this->rutaEntradaTemporal($idTrabajo),
            $this->rutaResultadoTemporal($idTrabajo),
            $this->rutaProgresoTemporal($idTrabajo),
            $this->rutaSolicitudProcesando($idTrabajo),
        ] as $ruta) {
            if (is_file($ruta)) {
                @unlink($ruta);
            }
        }
    }

    public function directorioBase(): string
    {
        return $this->directorioBase;
    }

    private function actualizarEstado(int $idCliente, string $idTrabajo, string $estado, string $mensaje, array $extra = []): void
    {
        $actual = $this->leerEstadoCliente($idCliente);
        if (($actual['id_trabajo'] ?? '') !== $idTrabajo) {
            return;
        }

        $nuevo = array_replace($actual, $extra, [
            'estado' => $estado,
            'mensaje' => $mensaje,
            'actualizado_en' => date(DATE_ATOM),
        ]);
        $this->escribirEstadoCliente($idCliente, $nuevo);
    }

    private function estadoSigueActivo(array $estado): bool
    {
        $nombre = strtoupper((string)($estado['estado'] ?? ''));
        if (!in_array($nombre, ['PENDIENTE', 'PROCESANDO'], true)) {
            return false;
        }

        $marca = strtotime((string)($estado['actualizado_en'] ?? $estado['solicitado_en'] ?? ''));
        if($marca === false)return false;
        if($nombre === 'PROCESANDO')return $marca >= time() - 1200;
        if($marca < time() - 172800)return false;
                                                                       
        if($marca < time() - 3600){
            $ultima=(int)@file_get_contents($this->directorioBase.'/ultima_ejecucion_cron');
            if($ultima < time() - 1800)return false;
        }
        return true;
    }

    private function descartarPendientesAntiguas(int $idCliente): void
    {
        foreach (glob($this->directorioSolicitudes . DIRECTORY_SEPARATOR . 'pendiente_*.json') ?: [] as $ruta) {
            $datos = $this->leerJson($ruta);
            if ((int)($datos['id_cliente'] ?? 0) === $idCliente) {
                @unlink($ruta);
            }
        }
    }

    private function rutaSolicitudPendiente(string $idTrabajo): string
    {
        return $this->directorioSolicitudes . DIRECTORY_SEPARATOR . 'pendiente_' . $this->limpiarId($idTrabajo) . '.json';
    }

    private function rutaEstadoCliente(int $idCliente): string
    {
        return $this->directorioEstados . DIRECTORY_SEPARATOR . 'cliente_' . $idCliente . '.json';
    }

    private function leerEstadoCliente(int $idCliente): array
    {
        $estado = $this->leerJson($this->rutaEstadoCliente($idCliente));
        if (($estado['estado'] ?? '') !== 'PROCESANDO' || empty($estado['id_trabajo'])) {
            return $estado;
        }
        $ruta = $this->rutaProgresoTemporal((string)$estado['id_trabajo']);
        $progreso = $this->leerJson($ruta);
        if (($progreso['id_trabajo'] ?? '') !== $estado['id_trabajo']) {
            return $estado;
        }
        $etapa = (string)($progreso['etapa'] ?? '');
        $mensajes = [
            'acceso' => 'Iniciando sesión en SUNAT SOL…',
            'busqueda' => 'Buscando la notificación oficial…',
            'documento' => 'Abriendo el documento en SUNAT…',
            'descarga' => 'Descargando el PDF oficial…',
            'guardado' => 'Guardando el documento en ASESCON…',
        ];
        if (isset($mensajes[$etapa])) {
            $estado['mensaje'] = $mensajes[$etapa];
            $estado['etapa'] = $etapa;
        }
        $estado['bytes_descargados'] = max(0, (int)($progreso['bytes_descargados'] ?? 0));
        $estado['bytes_totales'] = max(0, (int)($progreso['bytes_totales'] ?? 0));
        $modificado = @filemtime($ruta);
        if ($modificado !== false) {
            $estado['actualizado_en'] = date(DATE_ATOM, $modificado);
        }
        return $estado;
    }

    private function escribirEstadoCliente(int $idCliente, array $estado): void
    {
        $this->escribirJsonAtomico($this->rutaEstadoCliente($idCliente), $estado);
    }

    private function prepararDirectorios(): void
    {
        foreach ([$this->directorioBase, $this->directorioSolicitudes, $this->directorioEstados, $this->directorioTemporales] as $directorio) {
            if (!is_dir($directorio) && !@mkdir($directorio, 0700, true) && !is_dir($directorio)) {
                throw new RuntimeException('No se pudo preparar el almacenamiento del Buzón SOL.');
            }
            @chmod($directorio, 0700);
        }
    }

    private function escribirJsonAtomico(string $ruta, array $datos): void
    {
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('No se pudo preparar un archivo del Buzón SOL.');
        }

        $temporal = $ruta . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($temporal, $json, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir el almacenamiento del Buzón SOL.');
        }
        @chmod($temporal, 0600);
        if (!@rename($temporal, $ruta)) {
            @unlink($temporal);
            throw new RuntimeException('No se pudo finalizar un archivo del Buzón SOL.');
        }
        @chmod($ruta, 0600);
    }

    private function leerJson(string $ruta): array
    {
        if (!is_file($ruta)) {
            return [];
        }
        $contenido = @file_get_contents($ruta);
        if ($contenido === false || trim($contenido) === '') {
            return [];
        }
        $datos = json_decode($contenido, true);
        return is_array($datos) ? $datos : [];
    }

    private function limpiarId(string $idTrabajo): string
    {
        $limpio = preg_replace('/[^A-Za-z0-9_-]/', '', $idTrabajo) ?? '';
        if ($limpio === '') {
            throw new InvalidArgumentException('Identificador de trabajo inválido.');
        }
        return $limpio;
    }
}
