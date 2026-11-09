<?php

declare(strict_types=1);
require_once dirname(__DIR__,2).'/configuracion/rutas_privadas.php';

require_once __DIR__ . '/ConfiguracionSunat.php';
require_once __DIR__ . '/ExcepcionSunat.php';

final class ServicioPadronSunat
{
    private string $storageDir;
    private string $chunksDir;
    private string $manifestPath;
    private string $statusPath;
    private string $lockPath;

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir
            ?? asesconRutaAlmacenamiento() . DIRECTORY_SEPARATOR . 'sunat';
        $this->chunksDir = $this->storageDir . DIRECTORY_SEPARATOR . 'padron_chunks';
        $this->manifestPath = $this->storageDir . DIRECTORY_SEPARATOR . 'padron_manifest.json';
        $this->statusPath = $this->storageDir . DIRECTORY_SEPARATOR . 'padron_status.json';
        $this->lockPath = $this->storageDir . DIRECTORY_SEPARATOR . 'padron_actualizacion.lock';
    }

    public function consultarPorRuc(string $ruc): array
    {
        $ruc = trim($ruc);
        if (!preg_match('/^[0-9]{11}$/', $ruc)) {
            throw new ExcepcionSunat('El RUC no tiene un formato válido para consultar el padrón oficial de SUNAT.');
        }

        if (!$this->cacheUtilizable()) {
            throw new ExcepcionSunat('El catálogo oficial de RUC todavía se está preparando.');
        }

        $manifest = $this->leerManifest();
        $prefix = substr($ruc, 0, 3);
        $chunk = $this->chunksDir . DIRECTORY_SEPARATOR . 'ruc_' . $prefix . '.txt';

        if (!is_file($chunk)) {
            throw new ExcepcionSunat('El RUC no aparece en el Padrón Reducido oficial de SUNAT.');
        }

        $handle = @fopen($chunk, 'rb');
        if ($handle === false) {
            throw new ExcepcionSunat('No se pudo leer el índice local del padrón SUNAT.');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                if (substr($line, 0, 11) !== $ruc) {
                    continue;
                }

                $campos = explode('|', rtrim($line, "\r\n"));
                return $this->mapearFila($ruc, $campos, $manifest);
            }
        } finally {
            fclose($handle);
        }

        throw new ExcepcionSunat('El RUC no aparece en el Padrón Reducido oficial de SUNAT.');
    }

    public function iniciarActualizacionEnSegundoPlano(bool $forzar = false): bool
    {
        $this->crearDirectorio($this->storageDir);

        if (!$forzar && $this->padronVigente()) {
            return false;
        }

        $estado = $this->obtenerEstado();
        if (($estado['estado'] ?? '') === 'actualizando') {
            $inicio = (int) ($estado['iniciado_unix'] ?? 0);
            if ($inicio > 0 && (time() - $inicio) < 1800) {
                return false;
            }
        }

        if (($estado['estado'] ?? '') === 'iniciando') {
            $inicio = (int) ($estado['iniciado_unix'] ?? 0);

            if ($inicio > 0 && (time() - $inicio) < 30) {
                return false;
            }
        }

        if (!$forzar && ($estado['estado'] ?? '') === 'error') {
            $ultimo = (int) ($estado['actualizado_unix'] ?? 0);

            if ($ultimo > 0 && (time() - $ultimo) < 120) {
                return false;
            }
        }

        $this->guardarEstado([
            'estado' => 'iniciando',
            'etapa' => 'Preparando actualización',
            'porcentaje' => 0,
            'mensaje' => 'Preparando el catálogo oficial de RUC en segundo plano.',
            'iniciado_unix' => time(),
            'actualizado_unix' => time(),
        ]);

        $script = __DIR__ . DIRECTORY_SEPARATOR . 'procesos_programados' . DIRECTORY_SEPARATOR . 'actualizar_padron.php';
        if (!is_file($script)) {
            $this->guardarError('No existe el proceso interno de actualización del padrón SUNAT.');
            throw new ExcepcionSunat('No existe el proceso interno de actualización del padrón SUNAT.');
        }

        $php = $this->resolverPhpCli();
        $lanzado = $this->lanzarProcesoSegundoPlano($php, $script, $forzar);

        if (!$lanzado) {
            $this->guardarError('El servidor no pudo iniciar el actualizador del padrón en segundo plano.');
            throw new ExcepcionSunat('No se pudo iniciar el actualizador del padrón SUNAT en segundo plano.');
        }

        return true;
    }

    public function actualizarAhora(bool $forzar = false): void
    {
        $this->crearDirectorio($this->storageDir);

        if (!$forzar && $this->padronVigente()) {
            $this->guardarEstadoListo('El catálogo SUNAT ya estaba actualizado.');
            return;
        }

        $lock = @fopen($this->lockPath, 'c+');
        if ($lock === false) {
            throw new ExcepcionSunat('No se pudo bloquear la actualización local del padrón SUNAT.');
        }

        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                return;
            }

            $this->guardarEstado([
                'estado' => 'actualizando',
                'etapa' => 'Conectando con SUNAT',
                'porcentaje' => 2,
                'mensaje' => 'Descargando el padrón oficial en segundo plano.',
                'iniciado_unix' => time(),
                'actualizado_unix' => time(),
            ]);

            $this->actualizarDesdeSunat();
            $this->guardarEstadoListo('Catálogo oficial de RUC listo para consultas rápidas.');
        } catch (Throwable $e) {
            $this->guardarError($e->getMessage());
            if ($e instanceof ExcepcionSunat) {
                throw $e;
            }
            throw new ExcepcionSunat('No se pudo preparar el Padrón Reducido de SUNAT: ' . $e->getMessage());
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function obtenerEstado(): array
    {
        $estado = [];
        if (is_file($this->statusPath)) {
            $json = @file_get_contents($this->statusPath);
            $data = $json !== false ? json_decode($json, true) : null;
            if (is_array($data)) {
                $estado = $data;
            }
        }

        $estado['cache_disponible'] = $this->cacheUtilizable();
        $estado['cache_vigente'] = $this->padronVigente();

        $manifest = $this->leerManifest();
        if ($manifest !== []) {
            $estado['padron_actualizado_en'] = (string) ($manifest['generated_at'] ?? '');
            $estado['registros_indexados'] = (int) ($manifest['records_indexed'] ?? 0);
        }

        if (($estado['estado'] ?? '') === '' && $estado['cache_disponible']) {
            $estado['estado'] = 'listo';
            $estado['etapa'] = 'Listo';
            $estado['porcentaje'] = 100;
            $estado['mensaje'] = 'Catálogo oficial de RUC disponible.';
        }

        return $estado;
    }

    public function cacheDisponible(): bool
    {
        return $this->cacheUtilizable();
    }

    public function cacheVigente(): bool
    {
        return $this->padronVigente();
    }

    private function padronVigente(): bool
    {
        if (!$this->cacheUtilizable()) {
            return false;
        }

        $manifest = $this->leerManifest();
        $actualizado = (int) ($manifest['generated_at_unix'] ?? 0);
        if ($actualizado <= 0) {
            return false;
        }

        return (time() - $actualizado) < ConfiguracionSunat::PADRON_REFRESH_SECONDS;
    }

    private function cacheUtilizable(): bool
    {
        if (!is_file($this->manifestPath) || !is_dir($this->chunksDir)) {
            return false;
        }

        $archivos = glob($this->chunksDir . DIRECTORY_SEPARATOR . 'ruc_*.txt');
        return is_array($archivos) && count($archivos) > 0;
    }

    private function actualizarDesdeSunat(): void
    {
        if (!extension_loaded('curl')) {
            throw new ExcepcionSunat('La extensión cURL de PHP es necesaria para descargar el padrón oficial de SUNAT.');
        }
        if (!class_exists('ZipArchive')) {
            throw new ExcepcionSunat('La extensión ZIP de PHP es necesaria para procesar el padrón oficial de SUNAT.');
        }

        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $tempBase = $this->storageDir . DIRECTORY_SEPARATOR . 'tmp_' . bin2hex(random_bytes(6));
        $tempZip = $tempBase . '.zip';
        $tempChunks = $tempBase . '_chunks';
        $this->crearDirectorio($tempChunks);

        try {
            $this->descargarArchivo(ConfiguracionSunat::PADRON_RUC_URL, $tempZip);
            [$headerUtf8, $registros] = $this->construirChunksDesdeZip($tempZip, $tempChunks);

            if ($registros < 1) {
                throw new ExcepcionSunat('El archivo oficial del padrón SUNAT no contiene registros utilizables.');
            }

            $manifest = [
                'source' => 'Padrón Reducido del RUC - SUNAT',
                'source_url' => ConfiguracionSunat::PADRON_RUC_URL,
                'generated_at' => date(DATE_ATOM),
                'generated_at_unix' => time(),
                'records_indexed' => $registros,
                'header' => $headerUtf8,
            ];

            $manifestTemp = $tempBase . '_manifest.json';
            if (
                @file_put_contents(
                    $manifestTemp,
                    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    LOCK_EX
                ) === false
            ) {
                throw new ExcepcionSunat('No se pudo crear el manifiesto del padrón SUNAT.');
            }

            $this->actualizarProgreso('Activando índice', 97, 'Activando el índice local optimizado.');
            $this->reemplazarDirectorio($tempChunks, $this->chunksDir);
            if (!@rename($manifestTemp, $this->manifestPath)) {
                if (!@copy($manifestTemp, $this->manifestPath)) {
                    throw new ExcepcionSunat('No se pudo activar el manifiesto actualizado del padrón SUNAT.');
                }
                @unlink($manifestTemp);
            }
        } finally {
            @unlink($tempZip);
            if (is_dir($tempChunks)) {
                $this->eliminarDirectorio($tempChunks);
            }
        }
    }

    private function descargarArchivo(string $url, string $destino): void
    {
        $fp = @fopen($destino, 'wb');
        if ($fp === false) {
            throw new ExcepcionSunat('No se pudo crear el archivo temporal del padrón SUNAT.');
        }

        $ch = curl_init();
        if ($ch === false) {
            fclose($fp);
            throw new ExcepcionSunat('No se pudo iniciar la descarga del padrón SUNAT.');
        }

        $ultimoReporte = 0.0;
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => ConfiguracionSunat::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => ConfiguracionSunat::PADRON_DOWNLOAD_TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'ASESCON/1.1 (+Padron Reducido SUNAT)',
            CURLOPT_HTTPHEADER => ['Accept: application/zip,application/octet-stream,*/*'],
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($resource, float $total, float $ahora, float $uploadTotal, float $uploadAhora) use (&$ultimoReporte): int {
                $tiempo = microtime(true);
                if (($tiempo - $ultimoReporte) < 0.7) {
                    return 0;
                }
                $ultimoReporte = $tiempo;
                if ($total > 0) {
                    $porcentajeDescarga = min(100, max(0, ($ahora / $total) * 100));
                    $porcentajeGeneral = 5 + (int) round($porcentajeDescarga * 0.35);
                    $this->actualizarProgreso(
                        'Descargando padrón SUNAT',
                        $porcentajeGeneral,
                        'Descargando el catálogo oficial de RUC en segundo plano.'
                    );
                }
                return 0;
            },
        ]);

        $ok = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($fp);

        if ($ok === false || $status < 200 || $status >= 300) {
            @unlink($destino);
            $detalle = $status > 0 ? 'HTTP ' . $status : ($error !== '' ? $error : 'sin respuesta HTTP');
            throw new ExcepcionSunat('No se pudo descargar el Padrón Reducido oficial de SUNAT (' . $detalle . ').', $status ?: null);
        }

        if (!is_file($destino) || filesize($destino) < 1024) {
            @unlink($destino);
            throw new ExcepcionSunat('SUNAT devolvió un archivo de padrón vacío o inválido.');
        }

        $this->actualizarProgreso('Descarga completada', 42, 'Descarga completada. Preparando el índice local.');
    }

    private function construirChunksDesdeZip(string $zipPath, string $chunksDir): array
    {
        $zip = new ZipArchive();
        $resultado = $zip->open($zipPath);
        if ($resultado !== true) {
            throw new ExcepcionSunat('No se pudo abrir el ZIP oficial del padrón SUNAT.');
        }

        try {
            $indiceEntrada = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nombre = (string) $zip->getNameIndex($i);
                $base = strtolower(basename(str_replace('\\', '/', $nombre)));
                if (str_ends_with($base, '.txt') && str_contains($base, 'padron_reducido_ruc')) {
                    $indiceEntrada = $i;
                    break;
                }
            }
            if ($indiceEntrada === null) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $nombre = (string) $zip->getNameIndex($i);
                    if (str_ends_with(strtolower($nombre), '.txt')) {
                        $indiceEntrada = $i;
                        break;
                    }
                }
            }
            if ($indiceEntrada === null) {
                throw new ExcepcionSunat('El ZIP de SUNAT no contiene el archivo TXT esperado.');
            }

            $entrada = (string) $zip->getNameIndex($indiceEntrada);
            $stat = $zip->statIndex($indiceEntrada);
            $tamanoDescomprimido = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;
            $stream = $zip->getStream($entrada);
            if ($stream === false) {
                throw new ExcepcionSunat('No se pudo leer el TXT dentro del padrón SUNAT.');
            }

            $handles = [];
            $registros = 0;
            $headerUtf8 = [];
            $ultimoReporte = 0;

            try {
                $primeraLinea = fgets($stream);
                if ($primeraLinea === false) {
                    throw new ExcepcionSunat('El TXT del padrón SUNAT está vacío.');
                }

                $headerUtf8 = array_map(
                    fn (string $v): string => trim($this->aUtf8($v)),
                    explode('|', rtrim($primeraLinea, "\r\n"))
                );

                while (($line = fgets($stream)) !== false) {
                    if (strlen($line) < 12 || $line[11] !== '|' || strspn($line, '0123456789', 0, 11) !== 11) {
                        continue;
                    }

                    $prefix = substr($line, 0, 3);
                    if (!isset($handles[$prefix])) {
                        $path = $chunksDir . DIRECTORY_SEPARATOR . 'ruc_' . $prefix . '.txt';
                        $handles[$prefix] = @fopen($path, 'ab');
                        if ($handles[$prefix] === false) {
                            throw new ExcepcionSunat('No se pudo construir el índice local del padrón SUNAT.');
                        }
                    }

                    fwrite($handles[$prefix], $line);
                    $registros++;

                    if (($registros - $ultimoReporte) >= 50000) {
                        $ultimoReporte = $registros;
                        $pos = @ftell($stream);
                        if ($tamanoDescomprimido > 0 && is_int($pos) && $pos > 0) {
                            $fraccion = min(1.0, $pos / $tamanoDescomprimido);
                            $porcentaje = 45 + (int) round($fraccion * 48);
                        } else {
                            $porcentaje = min(92, 45 + (int) floor($registros / 250000));
                        }
                        $this->actualizarProgreso(
                            'Indexando RUC',
                            $porcentaje,
                            'Preparando búsquedas rápidas. Registros procesados: ' . number_format($registros, 0, ',', '.') . '.'
                        );
                    }
                }
            } finally {
                fclose($stream);
                foreach ($handles as $handle) {
                    if (is_resource($handle)) {
                        fclose($handle);
                    }
                }
            }

            return [$headerUtf8, $registros];
        } finally {
            $zip->close();
        }
    }

    private function resolverPhpCli(): string
    {
        $configurado = trim((string)getenv('ASESCON_PHP_CLI'));
        if ($configurado !== '' && is_file($configurado)) {
            return $configurado;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $candidatos = [
                rtrim((string) PHP_BINDIR, '\\/') . DIRECTORY_SEPARATOR . 'php.exe',
                dirname((string) PHP_BINARY) . DIRECTORY_SEPARATOR . 'php.exe',
                (string) PHP_BINARY,
            ];
            foreach ($candidatos as $candidato) {
                if ($candidato !== '' && is_file($candidato)) {
                    return $candidato;
                }
            }
            return 'php.exe';
        }

        $versionCpanel = '/opt/cpanel/ea-php' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '/root/usr/bin/php';
        $candidatos = [
            $versionCpanel,
            '/usr/local/bin/php',
            '/usr/bin/php',
        ];
        if (PHP_SAPI === 'cli' && PHP_BINARY !== '') {
            array_unshift($candidatos, PHP_BINARY);
        }
        foreach ($candidatos as $candidato) {
            if ($candidato !== '' && is_file($candidato) && is_executable($candidato)) {
                return $candidato;
            }
        }
        return 'php';
    }

    private function lanzarProcesoSegundoPlano(string $php, string $script, bool $forzar): bool
    {
        $arg = $forzar ? ' --force' : '';

        if (PHP_OS_FAMILY === 'Windows') {
            $comando = 'cmd /C start "" /B ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . $arg . ' >NUL 2>&1';
        } else {
            $comando = escapeshellarg($php) . ' ' . escapeshellarg($script) . $arg . ' >/dev/null 2>&1 &';
        }

        if (function_exists('popen')) {
            $handle = @popen($comando, 'r');
            if (is_resource($handle)) {
                @pclose($handle);
                return true;
            }
        }

        if (function_exists('exec')) {
            $salida = [];
            $codigo = 1;
            @exec($comando, $salida, $codigo);
            return $codigo === 0;
        }

        return false;
    }

    private function actualizarProgreso(string $etapa, int $porcentaje, string $mensaje): void
    {
        $estado = $this->obtenerEstadoBase();
        $estado['estado'] = 'actualizando';
        $estado['etapa'] = $etapa;
        $estado['porcentaje'] = max(0, min(100, $porcentaje));
        $estado['mensaje'] = $mensaje;
        $estado['iniciado_unix'] = (int) ($estado['iniciado_unix'] ?? time());
        $estado['actualizado_unix'] = time();
        $this->guardarEstado($estado);
    }

    private function guardarEstadoListo(string $mensaje): void
    {
        $this->guardarEstado([
            'estado' => 'listo',
            'etapa' => 'Listo',
            'porcentaje' => 100,
            'mensaje' => $mensaje,
            'iniciado_unix' => (int) (($this->obtenerEstadoBase()['iniciado_unix'] ?? time())),
            'actualizado_unix' => time(),
        ]);
    }

    private function guardarError(string $mensaje): void
    {
        $this->guardarEstado([
            'estado' => 'error',
            'etapa' => 'Error',
            'porcentaje' => 0,
            'mensaje' => $mensaje,
            'iniciado_unix' => (int) (($this->obtenerEstadoBase()['iniciado_unix'] ?? time())),
            'actualizado_unix' => time(),
        ]);
    }

    private function obtenerEstadoBase(): array
    {
        if (!is_file($this->statusPath)) {
            return [];
        }
        $json = @file_get_contents($this->statusPath);
        $data = $json !== false ? json_decode($json, true) : null;
        return is_array($data) ? $data : [];
    }

    private function guardarEstado(array $estado): void
    {
        $this->crearDirectorio($this->storageDir);
        $tmp = $this->statusPath . '.tmp';
        $json = json_encode($estado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || @file_put_contents($tmp, $json, LOCK_EX) === false) {
            return;
        }
        if (!@rename($tmp, $this->statusPath)) {
            @copy($tmp, $this->statusPath);
            @unlink($tmp);
        }
    }

    private function mapearFila(string $ruc, array $campos, array $manifest): array
    {
        $camposUtf8 = array_map(fn ($v): string => trim($this->aUtf8((string) $v)), $campos);
        $header = is_array($manifest['header'] ?? null) ? $manifest['header'] : [];
        $map = [];

        foreach ($header as $i => $nombre) {
            $map[$this->normalizarCabecera((string) $nombre)] = (int) $i;
        }

        $valor = function (array $nombres, ?int $fallback = null) use ($camposUtf8, $map): string {
            foreach ($nombres as $nombre) {
                $clave = $this->normalizarCabecera($nombre);
                if (isset($map[$clave])) {
                    return $this->limpiarDato($camposUtf8[$map[$clave]] ?? '');
                }
            }
            return $fallback !== null ? $this->limpiarDato($camposUtf8[$fallback] ?? '') : '';
        };

        $razon = $valor(['NOMBRE O RAZÓN SOCIAL', 'NOMBRE O RAZON SOCIAL'], 1);
        $estado = strtoupper($valor(['ESTADO DEL CONTRIBUYENTE'], 2));
        $condicion = strtoupper($valor(['CONDICIÓN DE DOMICILIO', 'CONDICION DE DOMICILIO'], 3));
        $ubigeo = $valor(['UBIGEO'], 4);
        $domicilio = $valor(['DOMICILIO FISCAL']);

        if ($domicilio === '') {
            $domicilio = $this->construirDomicilio($valor);
        }

        if ($razon === '') {
            throw new ExcepcionSunat('SUNAT no devolvió una razón social válida para el RUC indicado.');
        }

        return [
            'ruc' => $ruc,
            'razon_social' => $razon,
            'nombre_comercial' => '',
            'estado_ruc' => $estado,
            'condicion_ruc' => $condicion,
            'ubigeo' => $ubigeo,
            'domicilio_fiscal' => $domicilio,
            'departamento' => '',
            'provincia' => '',
            'distrito' => '',
            'actividad_economica' => '',
            'fuente' => 'Padrón Reducido del RUC - SUNAT',
            'padron_actualizado_en' => (string) ($manifest['generated_at'] ?? ''),
        ];
    }

    private function construirDomicilio(callable $valor): string
    {
        $tipoVia = $valor(['TIPO DE VÍA', 'TIPO DE VIA'], 5);
        $nombreVia = $valor(['NOMBRE DE VÍA', 'NOMBRE DE VIA'], 6);
        $codigoZona = $valor(['CÓDIGO DE ZONA', 'CODIGO DE ZONA'], 7);
        $tipoZona = $valor(['TIPO DE ZONA'], 8);
        $numero = $valor(['NÚMERO', 'NUMERO'], 9);
        $interior = $valor(['INTERIOR'], 10);
        $lote = $valor(['LOTE'], 11);
        $departamento = $valor(['DEPARTAMENTO'], 12);
        $manzana = $valor(['MANZANA'], 13);
        $kilometro = $valor(['KILÓMETRO', 'KILOMETRO'], 14);

        $partes = [];
        $via = trim($tipoVia . ' ' . $nombreVia);
        if ($via !== '') {
            $partes[] = $via;
        }
        $zona = trim($tipoZona . ' ' . $codigoZona);
        if ($zona !== '') {
            $partes[] = $zona;
        }
        if ($numero !== '') {
            $partes[] = 'NRO. ' . $numero;
        }
        if ($interior !== '') {
            $partes[] = 'INT. ' . $interior;
        }
        if ($lote !== '') {
            $partes[] = 'LOTE ' . $lote;
        }
        if ($departamento !== '') {
            $partes[] = 'DPTO. ' . $departamento;
        }
        if ($manzana !== '') {
            $partes[] = 'MZA. ' . $manzana;
        }
        if ($kilometro !== '') {
            $partes[] = 'KM. ' . $kilometro;
        }

        return trim(implode(' ', $partes));
    }

    private function leerManifest(): array
    {
        if (!is_file($this->manifestPath)) {
            return [];
        }
        $json = @file_get_contents($this->manifestPath);
        if ($json === false) {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private function crearDirectorio(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
            throw new ExcepcionSunat('No se pudo crear la carpeta local necesaria para el padrón SUNAT.');
        }
    }

    private function reemplazarDirectorio(string $origen, string $destino): void
    {
        $backup = $destino . '_old_' . bin2hex(random_bytes(3));
        if (is_dir($destino)) {
            if (!@rename($destino, $backup)) {
                $this->eliminarDirectorio($destino);
            }
        }

        if (!@rename($origen, $destino)) {
            if (is_dir($backup) && !is_dir($destino)) {
                @rename($backup, $destino);
            }
            throw new ExcepcionSunat('No se pudo activar el nuevo índice local del padrón SUNAT.');
        }

        if (is_dir($backup)) {
            $this->eliminarDirectorio($backup);
        }
    }

    private function eliminarDirectorio(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->eliminarDirectorio($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function limpiarDato(string $valor): string
    {
        $valor = trim($valor);
        return $valor === '-' ? '' : $valor;
    }

    private function normalizarCabecera(string $texto): string
    {
        $texto = trim($texto);
        $texto = function_exists('mb_strtoupper') ? mb_strtoupper($texto, 'UTF-8') : strtoupper($texto);
        return strtr($texto, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
    }

    private function aUtf8(string $texto): string
    {
        if ($texto === '') {
            return '';
        }
        if (function_exists('mb_check_encoding') && mb_check_encoding($texto, 'UTF-8')) {
            return $texto;
        }
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
        }
        if (function_exists('iconv')) {
            $convertido = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $texto);
            if ($convertido !== false) {
                return $convertido;
            }
        }
        return $texto;
    }
}
