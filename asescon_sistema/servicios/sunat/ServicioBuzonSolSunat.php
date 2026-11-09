<?php

declare(strict_types=1);

require_once __DIR__ . '/ExcepcionSunat.php';
require_once __DIR__ . '/DiagnosticoBuzonSol.php';

final class ServicioBuzonSolSunat
{
    public function listarBandeja(array $credenciales): array
    {
        $ruc = preg_replace('/\D+/', '', (string)($credenciales['ruc'] ?? '')) ?? '';
        $usuario = trim((string)($credenciales['usuario_sol'] ?? ''));
        $clave = (string)($credenciales['clave_sol'] ?? '');

        if (!preg_match('/^[0-9]{11}$/', $ruc)) {
            throw new ExcepcionSunat('El RUC debe contener exactamente 11 dígitos.');
        }
        if ($usuario === '' || $clave === '') {
            throw new ExcepcionSunat('Usuario SOL y Clave SOL son obligatorios para consultar el Buzón SOL.');
        }

        $root = dirname(__DIR__, 2);
        $script = $root . DIRECTORY_SEPARATOR . 'python' . DIRECTORY_SEPARATOR . 'sunat' . DIRECTORY_SEPARATOR . 'consultar_buzon_sol.py';
        if (!is_file($script)) {
            throw new ExcepcionSunat('No se encontró el componente de servidor del Buzón SOL.');
        }

        $payload = json_encode([
            'ruc' => $ruc,
            'usuario_sol' => $usuario,
            'clave_sol' => $clave,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new ExcepcionSunat('No se pudo preparar la consulta del Buzón SOL.');
        }

        $entorno = (new DiagnosticoBuzonSol())->comprobar();
        if (!($entorno['ok'] ?? false)) {
            throw new ExcepcionSunat(
                (string)($entorno['mensaje'] ?? 'El servidor no puede ejecutar el conector del Buzón SOL.'),
                null,
                (string)($entorno['codigo'] ?? 'BUZON_SOL_ENTORNO_NO_DISPONIBLE')
            );
        }

        $base = is_array($entorno['comando'] ?? null) ? $entorno['comando'] : [];
        if ($base === []) {
            throw new ExcepcionSunat('No se encontró un intérprete Python utilizable para el Buzón SOL.', null, 'PYTHON_NO_DISPONIBLE');
        }

        $resultado = $this->ejecutarWorker(array_merge($base, [$script]), $payload, $root);
        if ($resultado['json'] === null) {
            $detalle = trim((string)($resultado['diagnostico'] ?? ''));
            throw new ExcepcionSunat(
                'El conector del Buzón SOL terminó sin una respuesta válida.' . ($detalle !== '' ? ' Detalle: ' . $detalle : ''),
                null,
                'BUZON_SOL_RESPUESTA_INVALIDA'
            );
        }

        $json = $resultado['json'];
        if (!($json['ok'] ?? false)) {
            $mensaje = trim((string)($json['mensaje'] ?? 'No se pudo acceder al Buzón SOL.'));
            $codigo = trim((string)($json['codigo'] ?? 'BUZON_SOL_ERROR'));
            $mensaje = $this->ocultarCredenciales($mensaje, $usuario, $clave);
            throw new ExcepcionSunat($mensaje, null, $codigo);
        }

        $data = $json['data'] ?? null;
        if (!is_array($data)) {
            throw new ExcepcionSunat('El Buzón SOL devolvió un formato inesperado.');
        }
        if (trim((string)($data['ruc'] ?? '')) !== $ruc) {
            throw new ExcepcionSunat('La sesión SOL corresponde a un RUC distinto al solicitado.');
        }
        return $data;
    }

    private function ejecutarWorker(array $command, string $payload, string $cwd): array
    {
        $spec = [0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']];
        try {
            $p = @proc_open($command, $spec, $pipes, $cwd, null, ['bypass_shell' => true]);
        } catch (Throwable $e) {
            return ['json' => null,'diagnostico' => $e->getMessage()];
        }
        if (!is_resource($p)) {
            return ['json' => null,'diagnostico' => 'No se pudo iniciar Python.'];
        }
        fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $o = '';
        $e = '';
        $t = microtime(true);
        while (true) {
            $o .= (string)stream_get_contents($pipes[1]);
            $e .= (string)stream_get_contents($pipes[2]);
            $s = proc_get_status($p);
            if (!$s['running']) {
                break;
            }
if (microtime(true) - $t > 55.0) {
                proc_terminate($p);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($p);
                throw new ExcepcionSunat('SUNAT SOL no respondió dentro de 55 segundos. La sincronización fue cancelada para no bloquear ASESCON.');
            }usleep(100000);
        } $o .= (string)stream_get_contents($pipes[1]);
        $e .= (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($p);
        $j = $this->decodificarJson($o);
        if ($j !== null) {
            return ['json' => $j,'diagnostico' => ''];
        }
$d = trim(preg_replace('/\s+/', ' ', $e !== '' ? $e : $o) ?? '');
        if (strlen($d) > 300) {
            $d = substr($d, 0, 300) . '…';
        }
return ['json' => null,'diagnostico' => $d];
    }

    private function decodificarJson(string $stdout): ?array
    {
        $t = trim(ltrim($stdout, "\xEF\xBB\xBF"));
        if ($t === '') {
            return null;
        }
$j = json_decode($t, true);
        if (is_array($j)) {
            return $j;
        }
$lines = preg_split('/\R/', $t) ?: [];for ($i = count($lines) - 1; $i >= 0; $i--) {
            $l = trim($lines[$i]);
            if ($l === '' || !str_starts_with($l, '{')) {
                continue;
            }
$j = json_decode($l, true);
            if (is_array($j)) {
                return $j;
            }
        }
return null;
    }

    private function ocultarCredenciales(string $m, string $u, string $c): string
    {
        if (strlen($c) >= 4) {
            $m = str_replace($c, '[OCULTO]', $m);
        }
if (strlen($u) >= 3) {
            $m = str_replace($u, '[USUARIO SOL]', $m);
        }
return $m;
    }
}
