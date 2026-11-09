<?php

declare(strict_types=1);

require_once __DIR__ . '/ExcepcionSunat.php';
require_once __DIR__ . '/ServicioSireSunat.php';
require_once __DIR__ . '/../../configuracion/archivos_historicos.php';

final class InspectorArchivosSire
{
    private string $baseDir;
    private ?mysqli $conexion;

    public function __construct(?string $baseDir = null, ?mysqli $conexion = null)
    {
        $this->baseDir = $baseDir ?? asesconRutaAlmacenamiento() . '/sunat/sire';
                                                                                               
        $this->conexion = $conexion;
    }

    private function directorioDisponible(string $directorio): bool
    {
        if (!is_dir($directorio) && !@mkdir($directorio, 0700, true) && !is_dir($directorio)) return false;
        return is_writable($directorio);
    }

    public function guardarYExtraer(
        string $ruc,
        string $periodo,
        string $codLibro,
        string $ticket,
        string $binario,
        string $nombreSugerido = ''
    ): array {
        $this->validarDatos($ruc, $periodo, $codLibro, $ticket);
        if ($binario === '') {
            throw new ExcepcionSunat('SUNAT devolvió un archivo vacío.');
        }

        $directorio = $this->directorioOperacion($ruc, $periodo, $codLibro);
        $this->crearDirectorio($directorio);

        $hashZip = hash('sha256', $binario);
        $zipExistente = $this->buscarArchivoPorHash($directorio, $hashZip, ['zip']);
        $nombreZip = $this->sanearNombre($nombreSugerido);
        if ($nombreZip === '' || !str_ends_with(strtolower($nombreZip), '.zip')) {
            $nombreZip = strtolower($codLibro === ServicioSireSunat::LIBRO_RVIE ? 'rvie' : 'rce')
                . '_' . $periodo . '_' . preg_replace('/[^A-Za-z0-9_-]/', '', $ticket) . '.zip';
        }

        if ($zipExistente !== null) {
            $zipPath = $zipExistente;
            $zipReutilizado = true;
        } else {
            $zipPath = $this->nombreDisponible($directorio, $nombreZip);
            if (@file_put_contents($zipPath, $binario, LOCK_EX) === false) {
                throw new ExcepcionSunat('No se pudo guardar el archivo descargado desde SUNAT.');
            }
            $zipReutilizado = false;
        }

        $archivos = [];
        $hashArchivos = [];
        if (!$this->pareceZip($binario) && $this->pareceTexto($binario)) {
            $hash = hash('sha256', $binario);
            $existente = $this->buscarArchivoPorHash($directorio, $hash, ['txt', 'csv']);
            if ($existente !== null) {
                $txtPath = $existente;
            } else {
                $txtPath = $this->nombreDisponible($directorio, 'contenido_' . $periodo . '.txt');
                if (@file_put_contents($txtPath, $binario, LOCK_EX) === false) {
                    throw new ExcepcionSunat('SUNAT devolvió texto, pero ASESCON no pudo guardarlo.');
                }
            }
            $archivos = [$txtPath];
            $hashArchivos = [$hash];
        } else {
            $resultadoExtraccion = class_exists('ZipArchive')
                ? $this->extraerConZipArchive($zipPath, $directorio)
                : $this->extraerConHerramientaSistema($zipPath, $directorio);
            $archivos = $resultadoExtraccion['archivos'];
            $hashArchivos = $resultadoExtraccion['hashes'];
        }

        if ($archivos === []) {
            throw new ExcepcionSunat('El archivo SIRE fue descargado, pero no contiene TXT/CSV que ASESCON pueda visualizar.');
        }

        $metadata = $this->guardarMetadata(
            $directorio,
            $ruc,
            $periodo,
            $codLibro,
            $ticket,
            $zipPath,
            $hashZip,
            $archivos,
            $hashArchivos
        );
        $this->limpiarRetencion();

        return [
            'directorio' => $directorio,
            'zip' => $zipPath,
            'zip_reutilizado' => $zipReutilizado,
            'hash_zip' => $hashZip,
            'archivos' => $archivos,
            'hash_archivos' => $hashArchivos,
            'metadata' => $metadata,
        ];
    }

    public function registrarConjuntoTicket(string $ruc, string $periodo, string $codLibro, string $ticket, array $partes): array
    {
        if ($partes === []) {
            throw new ExcepcionSunat('SUNAT no devolvió ninguna parte descargable del ticket.');
        }
        $guardado = $partes[0];
        $rutas = [];
        $hashes = [];
        $partesZip = [];
        foreach ($partes as $parte) {
            $partesZip[] = ['zip' => basename((string)$parte['zip']), 'sha256' => (string)$parte['hash_zip']];
            foreach ($parte['archivos'] as $i => $ruta) {
                $hash = (string)($parte['hash_archivos'][$i] ?? '');
                if ($hash === '' || !is_file((string)$ruta)) {
                    throw new ExcepcionSunat('Una parte del ticket no pudo comprobarse. No se importaron datos parciales.');
                }
                if (in_array($hash, $hashes, true)) { continue; }
                $rutas[] = (string)$ruta;
                $hashes[] = $hash;
            }
        }
        if ($rutas === []) {
            throw new ExcepcionSunat('Ninguna parte del ticket contenía TXT/CSV verificables.');
        }
        $guardado['archivos'] = $rutas;
        $guardado['hash_archivos'] = $hashes;
        $guardado['cantidad_partes'] = count($partes);
        $directorio = $this->directorioOperacion($ruc, $periodo, $codLibro);
        $rutaMetadata = $this->guardarMetadata($directorio, $ruc, $periodo, $codLibro, $ticket,
            (string)$guardado['zip'], (string)$guardado['hash_zip'], $rutas, $hashes);
        $metadata = json_decode((string)file_get_contents($rutaMetadata), true);
        $metadata['partes_zip'] = $partesZip;
        if (file_put_contents($rutaMetadata, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new ExcepcionSunat('No se pudo guardar la lista completa de partes del ticket.');
        }
        $guardado['metadata'] = $rutaMetadata;
        return $guardado;
    }

    private function extraerConZipArchive(string $zipPath, string $directorio): array
    {
        $zip = new ZipArchive();
        $ok = $zip->open($zipPath);
        if ($ok !== true) {
            throw new ExcepcionSunat('El archivo recibido de SUNAT no pudo abrirse como ZIP.');
        }

        $archivos = [];
        $hashes = [];
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nombreInterno = (string)$zip->getNameIndex($i);
                if ($nombreInterno === '' || str_ends_with($nombreInterno, '/')) {
                    continue;
                }
                $extension = strtolower(pathinfo($nombreInterno, PATHINFO_EXTENSION));
                if (!in_array($extension, ['txt', 'csv'], true)) {
                    continue;
                }
                $contenido = $zip->getFromIndex($i);
                if (!is_string($contenido)) {
                    continue;
                }
                $hash = hash('sha256', $contenido);
                $existente = $this->buscarArchivoPorHash($directorio, $hash, ['txt', 'csv']);
                if ($existente !== null) {
                    $archivos[] = $existente;
                    $hashes[] = $hash;
                    continue;
                }

                $nombreSeguro = $this->sanearNombre(basename(str_replace('\\', '/', $nombreInterno)));
                if ($nombreSeguro === '') {
                    $nombreSeguro = 'sire_' . ($i + 1) . '.' . $extension;
                }
                $destino = $this->nombreDisponible($directorio, $nombreSeguro);
                if (@file_put_contents($destino, $contenido, LOCK_EX) === false) {
                    throw new ExcepcionSunat('No se pudo guardar un archivo de datos SIRE.');
                }
                $archivos[] = $destino;
                $hashes[] = $hash;
            }
        } finally {
            $zip->close();
        }
        return ['archivos' => $archivos, 'hashes' => $hashes];
    }

    private function extraerConHerramientaSistema(string $zipPath, string $directorio): array
    {
        if (!function_exists('exec')) {
            throw new ExcepcionSunat('ASESCON descargó el ZIP SIRE, pero este servidor no permite ejecutar el descompresor automático.');
        }

        $temp = $directorio . DIRECTORY_SEPARATOR . '_extraido_' . bin2hex(random_bytes(5));
        $this->crearDirectorio($temp);

        try {
            $ok = false;
            $detalle = '';

            if (PHP_OS_FAMILY === 'Windows') {
                $zipPs = str_replace("'", "''", $zipPath);
                $tempPs = str_replace("'", "''", $temp);
                $comando = 'powershell.exe -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command '
                    . escapeshellarg("Expand-Archive -LiteralPath '$zipPs' -DestinationPath '$tempPs' -Force");
                $ok = $this->ejecutarComando($comando, $detalle);

                if (!$ok) {
                    $ok = $this->ejecutarComando(
                        'tar.exe -xf ' . escapeshellarg($zipPath) . ' -C ' . escapeshellarg($temp),
                        $detalle
                    );
                }

                if (!$ok) {
                    $ok = $this->ejecutarComando(
                        'py -3 -m zipfile -e ' . escapeshellarg($zipPath) . ' ' . escapeshellarg($temp),
                        $detalle
                    );
                }
            } else {
                $ok = $this->ejecutarComando(
                    'unzip -o ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($temp),
                    $detalle
                );
                if (!$ok) {
                    $ok = $this->ejecutarComando(
                        'python3 -m zipfile -e ' . escapeshellarg($zipPath) . ' ' . escapeshellarg($temp),
                        $detalle
                    );
                }
                if (!$ok) {
                    $ok = $this->ejecutarComando(
                        'tar -xf ' . escapeshellarg($zipPath) . ' -C ' . escapeshellarg($temp),
                        $detalle
                    );
                }
            }

            if (!$ok) {
                $herramientas = PHP_OS_FAMILY === 'Windows'
                    ? 'PowerShell, tar.exe o Python'
                    : 'unzip, Python o tar';
                throw new ExcepcionSunat(
                    'ASESCON descargó el ZIP SIRE, pero no pudo descomprimirlo automáticamente. '
                    . 'No es necesario volver a generar el ticket; revisa la disponibilidad de ' . $herramientas . '.'
                );
            }

            $archivos = [];
            $hashes = [];
            $iterador = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterador as $archivo) {
                if (!$archivo instanceof SplFileInfo || !$archivo->isFile()) {
                    continue;
                }
                $extension = strtolower($archivo->getExtension());
                if (!in_array($extension, ['txt', 'csv'], true)) {
                    continue;
                }

                $nombreSeguro = $this->sanearNombre($archivo->getBasename());
                if ($nombreSeguro === '') {
                    $nombreSeguro = 'sire_' . (count($archivos) + 1) . '.' . $extension;
                }
                $hash = hash_file('sha256', $archivo->getPathname());
                if (!is_string($hash)) {
                    continue;
                }
                $existente = $this->buscarArchivoPorHash($directorio, $hash, ['txt', 'csv']);
                if ($existente !== null) {
                    $archivos[] = $existente;
                    $hashes[] = $hash;
                    continue;
                }
                $destino = $this->nombreDisponible($directorio, $nombreSeguro);
                if (@copy($archivo->getPathname(), $destino)) {
                    $archivos[] = $destino;
                    $hashes[] = $hash;
                }
            }

            return ['archivos' => $archivos, 'hashes' => $hashes];
        } finally {
            $this->eliminarDirectorio($temp);
        }
    }

    private function ejecutarComando(string $comando, string &$detalle = ''): bool
    {
        $salida = [];
        $codigo = 1;
        @exec($comando . ' 2>&1', $salida, $codigo);
        $detalle = trim(implode("\n", $salida));
        return $codigo === 0;
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

    public function obtenerArchivoDatosMasReciente(string $ruc, string $periodo, string $codLibro): ?string
    {
        $this->validarDatos($ruc, $periodo, $codLibro, 'LOCAL');
        $directorio = $this->directorioOperacion($ruc, $periodo, $codLibro);
        if (!is_dir($directorio)) {
            return null;
        }

        $candidatos = [];
        foreach (['*.txt','*.csv','*.TXT','*.CSV'] as $patron) {
            foreach (glob($directorio . DIRECTORY_SEPARATOR . $patron) ?: [] as $archivo) {
                if (is_file($archivo)) {
                    $candidatos[] = $archivo;
                }
            }
        }
        if ($candidatos === []) {
            return null;
        }

        usort($candidatos, static function (string $a, string $b): int {
            $ma = @filemtime($a) ?: 0;
            $mb = @filemtime($b) ?: 0;
            if ($ma === $mb) {
                return strcmp($b, $a);
            }
            return $mb <=> $ma;
        });
        return $candidatos[0] ?? null;
    }

    public function obtenerCache(string $ruc, string $periodo, string $codLibro): ?array
    {
        $directorio = $this->directorioOperacion($ruc, $periodo, $codLibro);
        $metadataPath = $directorio . DIRECTORY_SEPARATOR . 'metadata.json';
        if (!is_file($metadataPath)) {
            return null;
        }
        $json = @file_get_contents($metadataPath);
        $data = $json !== false ? json_decode($json, true) : null;
        if (!is_array($data)) {
            return null;
        }

        $archivos = [];
        foreach (($data['archivos'] ?? []) as $entrada) {
            $nombre = is_array($entrada) ? (string)($entrada['nombre'] ?? '') : (string)$entrada;
            if ($nombre === '') {
                continue;
            }
            $path = $directorio . DIRECTORY_SEPARATOR . basename($nombre);
            if (is_file($path)) {
                $archivos[] = $path;
            }
        }
        if ($archivos === []) {
            return null;
        }
        $data['archivos_paths'] = $archivos;
        $zip = isset($data['zip']) ? $directorio . DIRECTORY_SEPARATOR . basename((string) $data['zip']) : '';
        $data['zip_path'] = is_file($zip) ? $zip : '';
        return $data;
    }

    public function previsualizar(string $archivo, int $maxFilas = 100): array
    {
        $archivo = asesconResolverArchivoHistorico($archivo);
        if (!is_file($archivo)) {
            throw new ExcepcionSunat('El archivo SIRE que se intenta visualizar ya no existe.');
        }

        $handle = @fopen($archivo, 'rb');
        if ($handle === false) {
            throw new ExcepcionSunat('No se pudo abrir el archivo SIRE descargado.');
        }

        $lineas = [];
        try {
            while (count($lineas) < $maxFilas && ($linea = fgets($handle)) !== false) {
                $linea = rtrim($linea, "\r\n");
                if ($linea === '') {
                    continue;
                }
                $lineas[] = $this->aUtf8($linea);
            }
        } finally {
            fclose($handle);
        }

        if ($lineas === []) {
            return [
                'archivo' => basename($archivo),'delimitador' => '','columnas' => [],'filas' => [],
                'total_mostrado' => 0,'total_columnas' => 0,'tamano' => (int) filesize($archivo),
            ];
        }

        $delimitador = $this->detectarDelimitador($lineas[0]);
        $filas = [];
        $maxCols = 0;
        foreach ($lineas as $linea) {
            $campos = $delimitador !== '' ? str_getcsv($linea, $delimitador) : [$linea];
            $campos = array_map(static fn($v): string => trim((string) $v), $campos);
            $maxCols = max($maxCols, count($campos));
            $filas[] = $campos;
        }

        $columnas = [];
        for ($i = 1; $i <= $maxCols; $i++) {
            $columnas[] = 'Campo ' . $i;
        }

        return [
            'archivo' => basename($archivo),
            'delimitador' => $this->nombreDelimitador($delimitador),
            'columnas' => $columnas,
            'filas' => $filas,
            'total_mostrado' => count($filas),
            'total_columnas' => $maxCols,
            'tamano' => (int) filesize($archivo),
        ];
    }

    public function perteneceACache(string $archivo, string $ruc, string $periodo, string $codLibro): bool
    {
        $archivo = asesconResolverArchivoHistorico($archivo);
        $real = realpath($archivo);
        $base = realpath($this->directorioOperacion($ruc, $periodo, $codLibro));
        return $real !== false && $base !== false && str_starts_with($real, $base . DIRECTORY_SEPARATOR);
    }

    private function directorioOperacion(string $ruc, string $periodo, string $codLibro): string
    {
        $this->validarDatos($ruc, $periodo, $codLibro, 'cache');
        $tipo = $codLibro === ServicioSireSunat::LIBRO_RVIE ? 'rvie' : 'rce';
        return $this->baseDir . DIRECTORY_SEPARATOR . $ruc . DIRECTORY_SEPARATOR . $periodo . DIRECTORY_SEPARATOR . $tipo;
    }

    private function validarDatos(string $ruc, string $periodo, string $codLibro, string $ticket): void
    {
        if (!preg_match('/^\d{11}$/', $ruc)) {
            throw new ExcepcionSunat('RUC inválido para almacenar SIRE.');
        }
        if (!preg_match('/^\d{6}$/', $periodo)) {
            throw new ExcepcionSunat('Período inválido para almacenar SIRE.');
        }
        if (!in_array($codLibro, [ServicioSireSunat::LIBRO_RVIE, ServicioSireSunat::LIBRO_RCE], true)) {
            throw new ExcepcionSunat('Libro SIRE inválido.');
        }
        if ($ticket === '') {
            throw new ExcepcionSunat('Ticket SIRE vacío.');
        }
    }

    private function crearDirectorio(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
            error_log('SIRE: fallo al crear directorio de operaciones; revisar permisos y cuota del hosting.');
            throw new ExcepcionSunat('El servidor no puede crear el directorio SIRE. Revisa permisos/cuota de almacenamiento.');
        }
        if (!is_writable($path)) {
            error_log('SIRE: directorio de operación sin permisos de escritura.');
            throw new ExcepcionSunat('El directorio SIRE no tiene permiso de escritura. Revisa propietario y permisos.');
        }
    }

    private function sanearNombre(string $nombre): string
    {
        $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($nombre)) ?? '';
        return trim($nombre, '._');
    }

    private function buscarArchivoPorHash(string $directorio, string $hash, array $extensiones): ?string
    {
        foreach ($extensiones as $extension) {
            foreach (glob($directorio . DIRECTORY_SEPARATOR . '*.' . $extension) ?: [] as $archivo) {
                if (!is_file($archivo)) {
                    continue;
                }
                $hashExistente = hash_file('sha256', $archivo);
                if (is_string($hashExistente) && hash_equals($hash, $hashExistente)) {
                    return $archivo;
                }
            }
        }
        return null;
    }

    private function nombreDisponible(string $directorio, string $nombre): string
    {
        $base = pathinfo($nombre, PATHINFO_FILENAME);
        $ext = pathinfo($nombre, PATHINFO_EXTENSION);
        $destino = $directorio . DIRECTORY_SEPARATOR . $nombre;
        $n = 2;
        while (is_file($destino)) {
            $destino = $directorio . DIRECTORY_SEPARATOR . $base . '_' . $n . ($ext !== '' ? '.' . $ext : '');
            $n++;
        }
        return $destino;
    }

    private function guardarMetadata(
        string $dir,
        string $ruc,
        string $periodo,
        string $libro,
        string $ticket,
        string $zip,
        string $hashZip,
        array $archivos,
        array $hashArchivos
    ): string {
        $path = $dir . DIRECTORY_SEPARATOR . 'metadata.json';
        $detalleArchivos = [];
        foreach ($archivos as $i => $archivo) {
            $detalleArchivos[] = [
                'nombre' => basename((string)$archivo),
                'sha256' => (string)($hashArchivos[$i] ?? ''),
            ];
        }
        $data = [
            'ruc' => $ruc,
            'periodo' => $periodo,
            'cod_libro' => $libro,
            'tipo' => $libro === ServicioSireSunat::LIBRO_RVIE ? 'RVIE' : 'RCE',
            'num_ticket' => $ticket,
            'descargado_en' => date(DATE_ATOM),
            'zip' => basename($zip),
            'hash_zip' => $hashZip,
            'archivos' => $detalleArchivos,
        ];
        if (@file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        ) === false) {
            throw new ExcepcionSunat('No se pudo guardar la metadata SIRE.');
        }
        return $path;
    }

    private function limpiarRetencion(): void
    {
        if ($this->conexion === null || !is_dir($this->baseDir)) {
            return;
        }
        $dias = (int)(getenv('ASESCON_SIRE_RETENCION_DIAS') ?: 30);
        $dias = max(7, $dias);
        $limite = time() - ($dias * 86400);
        $protegidas = [];
        try {
            $res = $this->conexion->query(
                "SELECT ruta_zip,ruta_datos FROM sire_descargas WHERE ruta_zip IS NOT NULL OR ruta_datos IS NOT NULL"
            );
            if ($res) {
                while ($fila = $res->fetch_assoc()) {
                    foreach (['ruta_zip', 'ruta_datos'] as $campo) {
                        $ruta = realpath(asesconResolverArchivoHistorico((string)($fila[$campo] ?? '')));
                        if ($ruta !== false) {
                            $protegidas[$ruta] = true;
                        }
                    }
                }
            }
        } catch (Throwable) {
            return;
        }

        $iterador = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->baseDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterador as $archivo) {
            if (!$archivo instanceof SplFileInfo || !$archivo->isFile()) {
                continue;
            }
            $ext = strtolower($archivo->getExtension());
            if (!in_array($ext, ['zip', 'txt', 'csv'], true) || $archivo->getMTime() >= $limite) {
                continue;
            }
            $real = $archivo->getRealPath();
            if ($real !== false && !isset($protegidas[$real])) {
                @unlink($real);
            }
        }
    }

    private function detectarDelimitador(string $linea): string
    {
        $candidatos = ["|", "\t", ";", ","];
        $mejor = '';
        $max = 0;
        foreach ($candidatos as $d) {
            $n = substr_count($linea, $d);
            if ($n > $max) {
                $max = $n;
                $mejor = $d;
            }
        }
        return $max > 0 ? $mejor : '';
    }

    private function nombreDelimitador(string $d): string
    {
        return match ($d) {
            '|' => 'barra vertical |',
            "\t" => 'tabulación',
            ';' => 'punto y coma ;',
            ',' => 'coma ,',
            default => 'sin delimitador detectado',
        };
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
            return mb_convert_encoding($texto, 'UTF-8', ['Windows-1252','ISO-8859-1']);
        }
        if (function_exists('iconv')) {
            $r = @iconv('Windows-1252', 'UTF-8//IGNORE', $texto);
            if ($r !== false) {
                return $r;
            }
        }
        return $texto;
    }

    private function pareceZip(string $body): bool
    {
        if (strlen($body) < 4) {
            return false;
        }
        $firma = substr($body, 0, 4);
        return in_array($firma, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true);
    }

    private function pareceTexto(string $body): bool
    {
        $muestra = substr($body, 0, 2048);
        if ($muestra === '') {
            return false;
        }
        return strpos($muestra, "\0") === false;
    }
}
