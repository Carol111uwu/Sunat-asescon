<?php

declare(strict_types=1);
require_once dirname(__DIR__,2).'/configuracion/rutas_privadas.php';

final class ServicioColaBuzonSunafil
{
    private string $directorioBase;
    private string $directorioSolicitudes;
    private string $directorioEstados;
    private string $directorioTemporales;
    private string $archivoBloqueo;
    private bool $tomandoConBloqueo = false;

    public function __construct(?string $directorioBase = null)
    {
        $raizProyecto = dirname(__DIR__, 2);
        $this->directorioBase = $directorioBase
            ?? asesconRutaAlmacenamiento() . DIRECTORY_SEPARATOR . 'sunafil' . DIRECTORY_SEPARATOR . 'buzon_sunafil';
        $this->directorioSolicitudes = $this->directorioBase . DIRECTORY_SEPARATOR . 'solicitudes';
        $this->directorioEstados = $this->directorioBase . DIRECTORY_SEPARATOR . 'estados';
        $this->directorioTemporales = $this->directorioBase . DIRECTORY_SEPARATOR . 'temporales';
        $this->archivoBloqueo = $this->directorioBase . DIRECTORY_SEPARATOR . 'cola.lock';
        $this->prepararDirectorios();
    }

    public function solicitar(int $idCliente, int $idUsuario): array
    {
        if ($idCliente <= 0) {
            throw new InvalidArgumentException('El cliente seleccionado no es válido.');
        }

        $bloqueo = fopen($this->archivoBloqueo, 'c+');
        if ($bloqueo === false) {
            throw new RuntimeException('No se pudo bloquear la cola del Buzón SUNAFIL.');
        }

        try {
            if (!flock($bloqueo, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear la cola del Buzón SUNAFIL.');
            }

            $estadoActual = $this->leerEstadoCliente($idCliente);
            if ($this->estadoSigueActivo($estadoActual)) {
                return $estadoActual + ['reutilizada' => true];
            }

            if (!empty($estadoActual['id_trabajo'])) {
                $this->limpiarTrabajo((string)$estadoActual['id_trabajo']);
            }
            $this->descartarPendientesAntiguas($idCliente);

            $idTrabajo = date('YmdHis') . '-' . bin2hex(random_bytes(6));
            $ahora = date(DATE_ATOM);
            $solicitud = [
                'id_trabajo' => $idTrabajo,
                'id_cliente' => $idCliente,
                'id_usuario' => max(0, $idUsuario),
                'solicitado_en' => $ahora,
            ];

            $rutaSolicitud = $this->rutaSolicitudPendiente($idTrabajo);
            $this->escribirJsonAtomico($rutaSolicitud, $solicitud);

            $estado = [
                'id_trabajo' => $idTrabajo,
                'id_cliente' => $idCliente,
                'estado' => 'PENDIENTE',
                'mensaje' => 'La sincronización quedó en cola y será procesada automáticamente.',
                'solicitado_en' => $ahora,
                'actualizado_en' => $ahora,
                'total' => 0,
                'nuevos' => 0,
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
        $estado = $this->leerEstadoCliente($idCliente);
        if (($estado['estado'] ?? '') === 'PROCESANDO' && !$this->estadoSigueActivo($estado)) {
            $this->fallar($idCliente, (string)$estado['id_trabajo'], 'El proceso se interrumpió. Puedes volver a sincronizar.');
            return $this->leerEstadoCliente($idCliente);
        }
        if (($estado['estado'] ?? '') === 'PENDIENTE' && !$this->estadoSigueActivo($estado)) {
            return array_replace($estado, [
                'estado'=>'ERROR',
                'mensaje'=>'La consulta quedó en cola sin un procesador activo. Revisa Cron y vuelve a pulsar Consultar; se conservarán los requerimientos anteriores.',
            ]);
        }
        return $estado;
    }

    public function tomarSiguienteSolicitud(): ?array
    {
        $bloqueo = fopen($this->archivoBloqueo, 'c+');
        if ($bloqueo === false || !flock($bloqueo, LOCK_EX)) {
            throw new RuntimeException('No se pudo bloquear la cola SUNAFIL.');
        }
        try {
            $this->tomandoConBloqueo = true;
            return $this->tomarSolicitudBloqueada();
        } finally {
            $this->tomandoConBloqueo = false;
            flock($bloqueo, LOCK_UN);
            fclose($bloqueo);
        }
    }

    private function tomarSolicitudBloqueada(): ?array
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

            $this->actualizarEstado($idCliente, $idTrabajo, 'PROCESANDO', 'ASESCON está consultando el Buzón SUNAFIL en segundo plano.');
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
            'Buzón SUNAFIL sincronizado correctamente.',
            ['total' => max(0, $total), 'nuevos' => max(0, $nuevos), 'finalizado_en' => date(DATE_ATOM)]
        );
    }

    public function fallar(int $idCliente, string $idTrabajo, string $mensaje): void
    {
        $mensaje = trim(preg_replace('/\s+/', ' ', $mensaje) ?? '');
        if ($mensaje === '') {
            $mensaje = 'No se pudo completar la sincronización del Buzón SUNAFIL.';
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
        if ($this->tomandoConBloqueo) {
            $this->actualizarEstadoBloqueado($idCliente, $idTrabajo, $estado, $mensaje, $extra);
            return;
        }
        $bloqueo = fopen($this->archivoBloqueo, 'c+');
        if ($bloqueo === false || !flock($bloqueo, LOCK_EX)) {
            throw new RuntimeException('No se pudo actualizar la cola SUNAFIL.');
        }
        try {
            $this->actualizarEstadoBloqueado($idCliente, $idTrabajo, $estado, $mensaje, $extra);
        } finally {
            flock($bloqueo, LOCK_UN);
            fclose($bloqueo);
        }
    }

    private function actualizarEstadoBloqueado(int $idCliente, string $idTrabajo, string $estado, string $mensaje, array $extra = []): void
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
        if ($marca === false) return false;
        if ($nombre === 'PROCESANDO') return $marca >= time() - 300;
        if ($marca < time() - 172800) return false;
        if ($marca < time() - 3600) {
            $ultima=(int)@file_get_contents($this->directorioBase.'/ultima_ejecucion_cron');
            if ($ultima < time() - 1800) return false;
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
        return $this->leerJson($this->rutaEstadoCliente($idCliente));
    }

    private function escribirEstadoCliente(int $idCliente, array $estado): void
    {
        $this->escribirJsonAtomico($this->rutaEstadoCliente($idCliente), $estado);
    }

    private function prepararDirectorios(): void
    {
        foreach ([$this->directorioBase, $this->directorioSolicitudes, $this->directorioEstados, $this->directorioTemporales] as $directorio) {
            if (!is_dir($directorio) && !@mkdir($directorio, 0700, true) && !is_dir($directorio)) {
                throw new RuntimeException('No se pudo crear un directorio privado del Buzón SUNAFIL; revisa propietario y permisos de asescon_datos_privados/almacenamiento/sunafil.');
            }
            @chmod($directorio, 0700);
            clearstatcache(true, $directorio);
            if (!is_writable($directorio)) {
                throw new RuntimeException('El Buzón SUNAFIL no tiene permiso de escritura en su almacenamiento privado; verifica el propietario y los permisos en cPanel.');
            }
        }
    }

    private function escribirJsonAtomico(string $ruta, array $datos): void
    {
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('No se pudo preparar un archivo del Buzón SUNAFIL.');
        }

        $temporal = $ruta . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($temporal, $json, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir el almacenamiento del Buzón SUNAFIL.');
        }
        @chmod($temporal, 0600);
        if (!@rename($temporal, $ruta)) {
            @unlink($temporal);
            throw new RuntimeException('No se pudo finalizar un archivo del Buzón SUNAFIL.');
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
