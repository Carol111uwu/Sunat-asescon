<?php

declare(strict_types=1);


require_once __DIR__ . '/CargadorCredencialesSunat.php';
require_once __DIR__ . '/AutenticadorSunat.php';
require_once __DIR__ . '/ServicioSireSunat.php';
require_once __DIR__ . '/InspectorArchivosSire.php';
require_once __DIR__ . '/MapeadorSunat.php';
require_once __DIR__ . '/PeriodosSire.php';
require_once __DIR__ . '/../../modelos/sunat/RepositorioOperacionesSire.php';

class ServicioOperacionesSire
{
    private RepositorioOperacionesSire $repositorio;
    private array $sesiones=[];

    public function __construct(protected mysqli $conexion)
    {
        $this->repositorio = new RepositorioOperacionesSire($conexion);
    }

    private function cliente(int $idCliente): array
    {
        if ($idCliente <= 0) throw new InvalidArgumentException('Empresa inválida.');
        $this->autorizarCliente($idCliente);
        $cliente = $this->repositorio->obtenerClienteActivo($idCliente);
        if ($cliente === null) {
            throw new RuntimeException('Cliente no disponible.');
        }
        return $cliente;
    }

    private function sesion(array $cliente): ServicioSireSunat
    {
        $credenciales = (new CargadorCredencialesSunat($this->conexion))->obtenerPorRuc((string)$cliente['ruc']);
        $firma=hash('sha256',json_encode($credenciales,JSON_THROW_ON_ERROR));
        $cache=$this->sesiones[$firma]??null;
        if(is_array($cache)&&$cache['vence']>time()+60)return $cache['servicio'];
        $token = (new AutenticadorSunat())->generarTokenSire($credenciales);
        $servicio=(new ServicioSireSunat())->usarToken((string)$token['access_token']);
        $this->sesiones[$firma]=['servicio'=>$servicio,'vence'=>time()+max(1,(int)$token['expires_in'])];
        return $servicio;
    }

    private function libro(string $libro): array
    {
        $nombre = strtoupper(trim($libro));
        return match ($nombre) {
            'RVIE' => ['RVIE', ServicioSireSunat::LIBRO_RVIE],
            'RCE' => ['RCE', ServicioSireSunat::LIBRO_RCE],
            default => throw new InvalidArgumentException('Libro SIRE inválido.'),
        };
    }

    protected function autorizarCliente(int $id):void {}
    protected function exigirEjecucion(string $libro): void {}
    protected function auditar(string $accion,int $id,string $tabla,?string $registro,string $detalle): void {}

    private function validarPeriodo(string $periodo, bool $admitirAnual = false): void
    {
        $patron = $admitirAnual ? '/^(20\d{2})(00|0[1-9]|1[0-2])$/' : '/^20\d{2}(0[1-9]|1[0-2])$/';
        if (!preg_match($patron, $periodo) || !PeriodosSire::permitido($periodo, $admitirAnual)) {
            throw new InvalidArgumentException('El período debe estar entre enero de 2021 y el mes actual (hora de Perú).');
        }
    }

    private function validarTicketLocal(int $idCliente, string $periodo, string $libro, string $ticket): void
    {
        if ($ticket === '' || !$this->repositorio->existeTicket($idCliente, $periodo, $libro, $ticket)) {
            throw new InvalidArgumentException('El ticket SIRE no pertenece a esta empresa, período y libro.');
        }
    }

    public function periodos(int $idCliente, string $libro): array
    {
        [$nombre, $codigo] = $this->libro($libro);
        $this->exigirEjecucion($nombre);
        $cliente = $this->cliente($idCliente);
        $respuesta = $this->sesion($cliente)->consultarPeriodos($codigo);
        $periodos = array_values(array_filter((array)($respuesta['periodos']??[]),
            static fn($fila):bool => is_array($fila) && PeriodosSire::permitido((string)($fila['periodo']??''),false)));
        $this->auditar('CONSULTAR_PERIODOS_SIRE', $idCliente, 'sire', null, $nombre);
        return ['cliente' => $cliente, 'libro' => $nombre, 'periodos' => $periodos];
    }

    public function solicitar(int $idCliente, string $libro, string $periodo): array
    {
        [$nombre, $codigo] = $this->libro($libro);
        $this->exigirEjecucion($nombre);
        $this->validarPeriodo($periodo);
        $cliente = $this->cliente($idCliente);
        $existente = $this->repositorio->obtenerTicketEnProceso($idCliente, $periodo, $nombre);
        if ($existente !== null) {
            return [
                'cliente' => $cliente,
                'libro' => $nombre,
                'periodo' => $periodo,
                'num_ticket' => (string)$existente['numero_ticket'],
                'reutilizado' => true,
                'mensaje' => 'Se reutilizó un ticket SIRE reciente que todavía estaba en proceso.',
            ];
        }
        try {
            $respuesta = $this->sesion($cliente)->solicitarPropuesta($codigo, $periodo);
        } catch (ExcepcionSunat $error) {
            // SUNAT puede devolver el ticket de una exportación ya solicitada por otra instancia.
            $mensaje = $error->getMessage();
            if ($error->getCodigoSunat() === '42209'
                && preg_match('/\bTicket\s*[:#-]\s*([A-Z0-9]{8,24})\b/i', $mensaje, $coincidencia)) {
                $ticketExistente = $coincidencia[1];
                if (!$this->repositorio->existeTicket($idCliente, $periodo, $nombre, $ticketExistente)) {
                    $this->repositorio->registrarTicket($idCliente, $periodo, $nombre, $ticketExistente);
                }
                $this->auditar('RECUPERAR_TICKET_SIRE', $idCliente, 'sire_descargas', $ticketExistente, $nombre . ' ' . $periodo);
                return [
                    'cliente' => $cliente,
                    'libro' => $nombre,
                    'periodo' => $periodo,
                    'num_ticket' => $ticketExistente,
                    'reutilizado' => true,
                    'mensaje' => 'SUNAT ya tenía un proceso de exportación activo. Se recuperó el mismo ticket sin solicitar otro.',
                ];
            }
            throw $error;
        }
        $ticket = trim((string)($respuesta['num_ticket'] ?? ''));
        if ($ticket === '') {
            throw new RuntimeException('SUNAT no devolvió un ticket válido.');
        }
        $this->repositorio->registrarTicket($idCliente, $periodo, $nombre, $ticket);
        $this->auditar('SOLICITAR_SIRE', $idCliente, 'sire_descargas', $ticket, $nombre . ' ' . $periodo);
        return ['cliente' => $cliente, 'libro' => $nombre, 'periodo' => $periodo, 'num_ticket' => $ticket];
    }

                                                                                   
    public function ticketReciente(int $idCliente, string $libro, string $periodo): array
    {
        [$nombre] = $this->libro($libro);
        $this->exigirEjecucion($nombre);
        $this->validarPeriodo($periodo);
        $this->cliente($idCliente);
        $ticket = $this->repositorio->obtenerTicketReciente($idCliente, $periodo, $nombre);
        $procesado = $ticket !== null && (string)$ticket['estado_procesamiento'] === 'PROCESADO';
        $enProceso = $ticket !== null && !$procesado
            ? $this->repositorio->obtenerTicketEnProceso($idCliente, $periodo, $nombre)
            : null;
        return [
            'num_ticket' => (string)($ticket['numero_ticket'] ?? ''),
            'procesado' => $procesado,
            'reutilizable' => $enProceso !== null && (string)$enProceso['numero_ticket'] === (string)$ticket['numero_ticket'],
            'cantidad_registros' => $procesado ? (int)$ticket['cantidad_registros'] : null,
            'sin_comprobantes' => $procesado && (int)$ticket['cantidad_registros'] === 0,
        ];
    }

    public function inconsistencias(int $idCliente, string $libro, string $periodo, int $tipoResumen = 1): array
    {
        [$nombre, $codigo] = $this->libro($libro);
        $this->exigirEjecucion($nombre);
        $this->validarPeriodo($periodo);
        $cliente = $this->cliente($idCliente);
        $respuesta = $this->sesion($cliente)->consultarResumenInconsistencias($codigo, $periodo, $tipoResumen);
        $this->auditar('CONSULTAR_INCONSISTENCIAS_SIRE',
            $idCliente,
            'sire',
            null,
            $nombre . ' ' . $periodo . ' / resumen ' . $tipoResumen
        );
        return ['cliente' => $cliente, 'libro' => $nombre] + $respuesta;
    }

    public function estado(int $idCliente, string $libro, string $periodo, string $ticket): array
    {
        [$nombre, $codigo] = $this->libro($libro);
        $this->exigirEjecucion($nombre);
        $this->validarPeriodo($periodo);
        $cliente = $this->cliente($idCliente);
        $this->validarTicketLocal($idCliente, $periodo, $nombre, $ticket);
        $respuesta = $this->sesion($cliente)->consultarTicket($codigo, $periodo, $ticket);
        $this->repositorio->actualizarEstado($idCliente, $periodo, $nombre, $ticket, (string)$respuesta['estado']);
        if (!empty($respuesta['con_error'])) {
            $this->repositorio->marcarTicketError($idCliente, $periodo, $nombre, $ticket, (string)$respuesta['estado']);
            throw new ExcepcionSunat('SUNAT terminó el ticket con error: ' . (string)$respuesta['estado']);
        }
        return ['cliente' => $cliente, 'libro' => $nombre] + $respuesta;
    }

    public function descargar(int $idCliente, string $libro, string $periodo, string $ticket): array
    {
        [$nombre, $codigo] = $this->libro($libro);
        $this->exigirEjecucion($nombre);
        $this->validarPeriodo($periodo);
        $cliente = $this->cliente($idCliente);
        $this->validarTicketLocal($idCliente, $periodo, $nombre, $ticket);

        $servicio = $this->sesion($cliente);
        $estado = $servicio->consultarTicket($codigo, $periodo, $ticket);
        $this->repositorio->actualizarEstado($idCliente, $periodo, $nombre, $ticket, (string)$estado['estado']);
        if (!empty($estado['con_error'])) {
            $this->repositorio->marcarTicketError($idCliente, $periodo, $nombre, $ticket, (string)$estado['estado']);
            throw new ExcepcionSunat('SUNAT terminó el ticket con error: ' . (string)$estado['estado']);
        }
        if (!($estado['terminado'] ?? false)) {
            return ['descargado' => false, 'estado' => $estado['estado'], 'ticket' => $ticket];
        }

        $archivosReporte = $estado['archivos'] ?? [];
        if (!is_array($archivosReporte) || $archivosReporte === []) {
            throw new ExcepcionSunat('SUNAT no informó archivos para el ticket terminado.');
        }
        if (count($archivosReporte) > 100) {
            throw new ExcepcionSunat('SUNAT informó demasiadas partes. Revisa el ticket antes de descargar.');
        }
        $inspector = new InspectorArchivosSire(null, $this->conexion);
        $partes = [];
        foreach ($archivosReporte as $numero => $archivoReporte) {
            if (!is_array($archivoReporte)) {
                throw new ExcepcionSunat('SUNAT devolvió información incompleta sobre las partes del ticket.');
            }
            $binario = $servicio->descargarArchivoTicket($codigo, $estado, $archivoReporte);
            $partes[] = $inspector->guardarYExtraer(
                (string)$cliente['ruc'], $periodo, $codigo, $ticket,
                (string)$binario['body'], (string)($binario['filename'] ?? '')
            );
        }
        $guardado = $inspector->registrarConjuntoTicket(
            (string)$cliente['ruc'], $periodo, $codigo, $ticket, $partes
        );
        $mapeo = (new MapeadorSunat($this->conexion))->importarArchivos(
            $idCliente,
            $periodo,
            $codigo,
            $guardado['archivos'] ?? [],
            [
                'tipo' => 'TICKET_SIRE_TERMINADO',
                'ruc' => (string)$cliente['ruc'],
                'periodo' => $periodo,
                'libro' => $codigo,
                'ticket' => $ticket,
                'hash_zip' => (string)($guardado['hash_zip'] ?? ''),
                'hash_archivos' => $guardado['hash_archivos'] ?? [],
            ]
        );
        $cantidad = (int)$mapeo['cantidad_registros'];
        $this->repositorio->marcarDescarga($idCliente, $periodo, $nombre, $ticket, $guardado, $cantidad, $mapeo);
        $this->auditar('DESCARGAR_SIRE',
            $idCliente,
            'sire_descargas',
            $ticket,
            $nombre . ' ' . $periodo . ' / ' . $cantidad . ' comprobantes procesados'
        );

        return [
            'descargado' => true,
            'procesado' => true,
            'cliente' => $cliente,
            'libro' => $nombre,
            'periodo' => $periodo,
            'ticket' => $ticket,
            'cantidad_registros' => $cantidad,
            'sin_comprobantes' => (bool)($mapeo['sin_comprobantes'] ?? false),
            'base_imponible' => $mapeo['base_imponible'],
            'igv' => $mapeo['igv'],
            'total' => $mapeo['total'],
            'filas_omitidas' => $mapeo['omitidas'],
            'archivo_datos' => isset($guardado['archivos'][0]) ? basename((string)$guardado['archivos'][0]) : null,
            'hash_zip' => $guardado['hash_zip'] ?? null,
            'hash_datos' => $guardado['hash_archivos'][0] ?? null,
            'cantidad_partes' => (int)($guardado['cantidad_partes'] ?? 1),
            'mensaje' => $cantidad === 0
                ? 'Propuesta SIRE descargada y comprobada: cero comprobantes. Esto no equivale a una presentación oficial.'
                : 'Partes oficiales descargadas e importadas a ASESCON, sin excluir archivos.',
        ];
    }

    public function reprocesarLocal(int $idCliente, string $libro, string $periodo): array
    {
        [$nombre, $codigo] = $this->libro($libro);
        $this->exigirEjecucion($nombre);
        $this->validarPeriodo($periodo, true);
        $cliente = $this->cliente($idCliente);

        $inspector = new InspectorArchivosSire(null, $this->conexion);
        $mapper = new MapeadorSunat($this->conexion);
        $periodos = [];
        if (substr($periodo, 4, 2) === '00') {
            $anio = substr($periodo, 0, 4);
            for ($mes = 1; $mes <= 12; $mes++) {
                $p = $anio . str_pad((string)$mes, 2, '0', STR_PAD_LEFT);
                if (PeriodosSire::permitido($p, false)) $periodos[] = $p;
            }
        } else {
            $periodos[] = $periodo;
        }

        $procesados = [];
        $totales = ['archivos_leidos'=>0,'registros_locales'=>0,'filas_corregidas'=>0,'fechas_recuperadas'=>0,
                    'nombres_recuperados'=>0,'documentos_recuperados'=>0,'filas_omitidas_fuente'=>0];
        foreach ($periodos as $periodoMes) {
            $cache = $inspector->obtenerCache((string)$cliente['ruc'], $periodoMes, $codigo);
            $archivos = $cache['archivos_paths'] ?? [];
            if ($cache !== null && count($archivos) !== count($cache['archivos'] ?? [])) {
                throw new ExcepcionSunat('Faltan partes del archivo SIRE guardado de ' . $periodoMes . '. No se sustituyeron comprobantes.');
            }
            if ($archivos === []) {
                $archivo = $inspector->obtenerArchivoDatosMasReciente((string)$cliente['ruc'], $periodoMes, $codigo);
                if ($archivo) $archivos = [$archivo];
            }
            if ($archivos === []) continue;
                                                                                               
                                                                      
            $resultado = $mapper->recuperarCamposFaltantes($idCliente, $periodoMes, $codigo, $archivos, (string)$cliente['ruc']);
            $procesados[] = $periodoMes;
            foreach ($totales as $campo => &$numero) $numero += (int)($resultado[$campo] ?? 0);
            unset($numero);
        }
        if ($procesados === []) {
            throw new ExcepcionSunat('No hay un archivo SIRE local guardado para reconstruir este período. Sincroniza primero con SUNAT.');
        }
        $this->auditar('REPROCESAR_SIRE_LOCAL', $idCliente, 'sire', null, $nombre . ' ' . implode(',', $procesados));
        return [
            'cliente' => $cliente,
            'libro' => $nombre,
            'periodos' => $procesados,
            ...$totales,
            'mensaje' => $totales['filas_corregidas'] > 0
                ? 'Campos faltantes recuperados desde el archivo SIRE local. No se cambiaron los importes ni se descargó nuevamente desde SUNAT.'
                : 'Se revisó el archivo SIRE local, pero no había datos recuperables para los campos vacíos o no hubo coincidencias inequívocas. Ningún comprobante fue modificado.',
        ];
    }
}
