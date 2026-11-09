<?php

declare(strict_types=1);

require_once __DIR__ . '/ClienteHttpSunat.php';
require_once __DIR__ . '/ConfiguracionSunat.php';
require_once __DIR__ . '/ExcepcionSunat.php';

final class ServicioSireSunat
{
    public const LIBRO_RVIE = '140000';
    public const LIBRO_RCE = '080000';

    private string $accessToken = '';

    public function __construct(private ?ContratoHttpSunat $client = null)
    {
        $this->client ??= new ClienteHttpSunat();
    }

    public function usarToken(string $accessToken): self
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            throw new ExcepcionSunat('No se puede iniciar una sesión SIRE sin access token.');
        }
        $this->accessToken = $accessToken;
        return $this;
    }

    public function sesionLista(): bool
    {
        return $this->accessToken !== '';
    }

    public function consultarPeriodosRvie(): array
    {
        return $this->consultarPeriodos(self::LIBRO_RVIE);
    }

    public function consultarPeriodosRce(): array
    {
        return $this->consultarPeriodos(self::LIBRO_RCE);
    }

    public function consultarResumenInconsistencias(string $codLibro, string $periodo, int $codTipoResumen = 1): array
    {
        $this->exigirToken();
        $this->validarLibro($codLibro);
        $this->validarPeriodo($periodo);
        if ($codTipoResumen < 1 || $codTipoResumen > 6) {
            throw new ExcepcionSunat('El tipo de resumen de inconsistencias SIRE no es válido.');
        }

        $query = http_build_query([
            'codTipoResumen' => $codTipoResumen,
            'codLibro' => $codLibro,
        ], '', '&', PHP_QUERY_RFC3986);
        $url = rtrim(ConfiguracionSunat::SIRE_BASE_URL, '/')
            . '/v1/contribuyente/migeigv/libros/rvierce/resumen/web/resumeninconsistencias/'
            . rawurlencode($periodo) . '?' . $query;

        $respuesta = $this->client->postJson($url, null, $this->headersJson());
        $cantidad = is_array($respuesta['cantidad'] ?? null) ? $respuesta['cantidad'] : [];
        $monto = is_array($respuesta['monto'] ?? null) ? $respuesta['monto'] : [];

        return [
            'ruc' => (string)($respuesta['numRuc'] ?? ''),
            'periodo' => (string)($respuesta['perPeriodoTributario'] ?? $respuesta['perTributario'] ?? $periodo),
            'cod_tipo_resumen' => $codTipoResumen,
            'cantidad' => [
                'relacionadas_credito_fiscal_pct' => (float)($cantidad['porcentajeRelFiscal'] ?? 0),
                'no_relacionadas_credito_fiscal_pct' => (float)($cantidad['porcentajeNoRelFiscal'] ?? 0),
                'sin_inconsistencias_pct' => (float)($cantidad['porcentajeSinValidaciones'] ?? 0),
                'total' => (int)($cantidad['total'] ?? 0),
            ],
            'monto' => [
                'relacionadas_credito_fiscal_pct' => (float)($monto['porcentajeRelFiscal'] ?? 0),
                'no_relacionadas_credito_fiscal_pct' => (float)($monto['porcentajeNoRelFiscal'] ?? 0),
                'sin_inconsistencias_pct' => (float)($monto['porcentajeSinValidaciones'] ?? 0),
                'total' => (float)($monto['total'] ?? 0),
            ],
            'fuente' => 'SUNAT SIRE - resumen oficial de inconsistencias',
        ];
    }

    public function consultarPeriodos(string $codLibro): array
    {
        $this->exigirToken();
        $this->validarLibro($codLibro);

        $url = rtrim(ConfiguracionSunat::SIRE_BASE_URL, '/')
            . '/v1/contribuyente/migeigv/libros/rvierce/padron/web/omisos/'
            . rawurlencode($codLibro)
            . '/periodos';

        $respuesta = $this->client->getJson($url, $this->headersJson());

        return [
            'cod_libro' => $codLibro,
            'periodos' => $this->normalizarPeriodos($respuesta),
            'raw' => $respuesta,
        ];
    }

    public function solicitarPropuestaRvie(string $periodo): array
    {
        $this->exigirToken();
        $this->validarPeriodo($periodo);

        $url = rtrim(ConfiguracionSunat::SIRE_BASE_URL, '/')
            . '/v1/contribuyente/migeigv/libros/rvie/propuesta/web/propuesta/'
            . rawurlencode($periodo)
            . '/exportapropuesta?codTipoArchivo=0';

        $respuesta = $this->client->getJson($url, $this->headersJson());
        $ticket = $this->extraerNumTicket($respuesta);
        if ($ticket === '') {
            throw new ExcepcionSunat('SUNAT no devolvió el número de ticket para la propuesta RVIE.');
        }

        return ['num_ticket' => $ticket, 'periodo' => $periodo, 'cod_libro' => self::LIBRO_RVIE, 'raw' => $respuesta];
    }

    public function solicitarPropuestaRce(string $periodo): array
    {
        $this->exigirToken();
        $this->validarPeriodo($periodo);

        $url = rtrim(ConfiguracionSunat::SIRE_BASE_URL, '/')
            . '/v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/'
            . rawurlencode($periodo)
            . '/exportacioncomprobantepropuesta?codTipoArchivo=0&codOrigenEnvio=2';

        $respuesta = $this->client->getJson($url, $this->headersJson());
        $ticket = $this->extraerNumTicket($respuesta);
        if ($ticket === '') {
            throw new ExcepcionSunat('SUNAT no devolvió el número de ticket para la propuesta RCE.');
        }

        return ['num_ticket' => $ticket, 'periodo' => $periodo, 'cod_libro' => self::LIBRO_RCE, 'raw' => $respuesta];
    }

    public function solicitarPropuesta(string $codLibro, string $periodo): array
    {
        $this->validarLibro($codLibro);
        return $codLibro === self::LIBRO_RVIE
            ? $this->solicitarPropuestaRvie($periodo)
            : $this->solicitarPropuestaRce($periodo);
    }

    public function consultarTicket(string $codLibro, string $periodo, string $numTicket): array
    {
        $this->exigirToken();
        $this->validarLibro($codLibro);
        $this->validarPeriodo($periodo);
        $numTicket = trim($numTicket);
        if ($numTicket === '') {
            throw new ExcepcionSunat('El número de ticket SIRE está vacío.');
        }

        $query = http_build_query([
            'perIni' => $periodo,
            'perFin' => $periodo,
            'page' => 1,
            'perPage' => 20,
            'numTicket' => $numTicket,
            'codLibro' => $codLibro,
            'codOrigenEnvio' => '2',
        ], '', '&', PHP_QUERY_RFC3986);

        $url = rtrim(ConfiguracionSunat::SIRE_BASE_URL, '/')
            . '/v1/contribuyente/migeigv/libros/rvierce/gestionprocesosmasivos/web/masivo/consultaestadotickets?'
            . $query;
        $respuesta = $this->client->getJson($url, $this->headersJson());
        $registro = $this->buscarRegistroTicket($respuesta, $numTicket);
        if ($registro === null) {
            throw new ExcepcionSunat(
                'SUNAT respondió la consulta, pero no devolvió el ticket exacto solicitado.',
                502
            );
        }

        $detalle = isset($registro['detalleTicket']) && is_array($registro['detalleTicket'])
            ? $registro['detalleTicket']
            : [];
        $codigoEstado = (string) ($detalle['codEstadoEnvio'] ?? $registro['codEstadoProceso'] ?? '');
        $estado = trim((string) ($detalle['desEstadoEnvio'] ?? $registro['desEstadoProceso'] ?? ''));
        $estadoTexto=$this->minusculas($estado);
                                                                                                      
        $textoSinExito = preg_replace('/sin\s+errores?/u', '', $estadoTexto);
        $conError = $codigoEstado === '03' || (bool)preg_match('/error|rechaz|fall|cancel|anulad/', $textoSinExito);
        $terminado = !$conError&&($codigoEstado==='06'||($codigoEstado===''&&str_contains($estadoTexto,'termin')));
        $archivos = $this->extraerArchivosReporte($registro);

        return [
            'encontrado' => true,
            'con_error'=>(bool)$conError,
            'terminado' => $terminado,
            'estado' => $estado !== '' ? $estado : ($terminado ? 'Terminado' : 'En proceso'),
            'codigo_estado' => $codigoEstado,
            'num_ticket' => (string) ($detalle['numTicket'] ?? $registro['numTicket'] ?? $numTicket),
            'periodo' => (string) ($registro['perTributario'] ?? $periodo),
            'cod_proceso' => (string) ($registro['codProceso'] ?? ''),
            'archivos' => $archivos,
            'raw' => $registro,
        ];
    }

    public function descargarArchivoTicket(string $codLibro, array $ticketEstado, ?array $archivo = null): array
    {
        $this->exigirToken();
        $this->validarLibro($codLibro);
        if (!($ticketEstado['terminado'] ?? false)) {
            throw new ExcepcionSunat('El ticket SIRE todavía no está terminado.');
        }

        $archivo ??= $ticketEstado['archivos'][0] ?? null;
        if (!is_array($archivo)) {
            throw new ExcepcionSunat('SUNAT marcó el ticket como terminado pero no informó el archivo generado.');
        }

        $nombre = trim((string) ($archivo['nombre_reporte'] ?? ''));
        $tipo = trim((string) ($archivo['tipo_archivo'] ?? ''));
        if ($nombre === '') {
            throw new ExcepcionSunat('SUNAT no informó el nombre del archivo del ticket.');
        }

        $base = rtrim(ConfiguracionSunat::SIRE_BASE_URL, '/')
            . '/v1/contribuyente/migeigv/libros/rvierce/gestionprocesosmasivos/web/masivo/archivoreporte';

        $periodo = trim((string)($ticketEstado['periodo'] ?? ''));
        $codProceso = trim((string)($ticketEstado['cod_proceso'] ?? ''));
        $numTicket = trim((string)($ticketEstado['num_ticket'] ?? ''));
        if ($periodo === '' || $codProceso === '' || $numTicket === '') {
            throw new ExcepcionSunat('SUNAT no devolvió todos los datos necesarios para descargar el archivo del ticket.');
        }

        $parametros = [
            'nomArchivoReporte' => $nombre,
            'codTipoArchivoReporte' => $tipo !== '' ? $tipo : 'null',
            'perTributario' => $periodo,
            'codProceso' => $codProceso,
            'numTicket' => $numTicket,
            'codLibro' => $codLibro,
        ];

        return $this->client->getBinary(
            $base . '?' . http_build_query($parametros, '', '&', PHP_QUERY_RFC3986),
            $this->headersBinarios()
        );
    }

    private function exigirToken(): void
    {
        if ($this->accessToken === '') {
            throw new ExcepcionSunat('Primero debes autenticar la sesión SIRE.');
        }
    }

    private function validarLibro(string $codLibro): void
    {
        if (!in_array($codLibro, [self::LIBRO_RVIE, self::LIBRO_RCE], true)) {
            throw new ExcepcionSunat('Código de libro SIRE no permitido.');
        }
    }

    private function validarPeriodo(string $periodo): void
    {
        if (!preg_match('/^\d{4}(0[1-9]|1[0-2])$/', $periodo)) {
            throw new ExcepcionSunat('El período SIRE debe tener formato AAAAMM.');
        }
    }

    private function headersJson(): array
    {
        return [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
        ];
    }

    private function headersBinarios(): array
    {
        return ['Authorization: Bearer ' . $this->accessToken];
    }

    private function extraerNumTicket(array $respuesta): string
    {
        $buscar = function ($nodo) use (&$buscar): string {
            if (!is_array($nodo)) {
                return '';
            }
            foreach (['numTicket', 'num_ticket', 'ticket', '_value'] as $clave) {
                if (isset($nodo[$clave]) && is_scalar($nodo[$clave])) {
                    $valor = trim((string) $nodo[$clave]);
                    if ($valor !== '') {
                        return $valor;
                    }
                }
            }
            foreach ($nodo as $valor) {
                if (is_array($valor)) {
                    $hallado = $buscar($valor);
                    if ($hallado !== '') {
                        return $hallado;
                    }
                }
            }
            return '';
        };
        return $buscar($respuesta);
    }

    private function buscarRegistroTicket(array $respuesta, string $ticket): ?array
    {
        $candidatos = [];
        $recorrer = function ($nodo) use (&$recorrer, &$candidatos): void {
            if (!is_array($nodo)) {
                return;
            }
            if (isset($nodo['numTicket']) || isset($nodo['detalleTicket'])) {
                $candidatos[] = $nodo;
            }
            foreach ($nodo as $valor) {
                if (is_array($valor)) {
                    $recorrer($valor);
                }
            }
        };
        $recorrer($respuesta);

        foreach ($candidatos as $registro) {
            $detalle = is_array($registro['detalleTicket'] ?? null) ? $registro['detalleTicket'] : [];
            $ticketCandidato = trim((string) (
                $detalle['numTicket']
                ?? $registro['numTicket']
                ?? $registro['numeroTicket']
                ?? $registro['ticket']
                ?? ''
            ));
            if ($ticketCandidato !== '' && hash_equals($ticket, $ticketCandidato)) {
                return $registro;
            }
        }
        return null;
    }

    private function extraerArchivosReporte(array $registro): array
    {
        $salida = [];
        $array = isset($registro['archivoReporte']) && is_array($registro['archivoReporte'])
            ? $registro['archivoReporte']
            : [];

        foreach ($array as $archivo) {
            if (!is_array($archivo)) {
                continue;
            }
            $nombre = trim((string) ($archivo['nomArchivoReporte'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $salida[] = [
                'nombre_reporte' => $nombre,
                'tipo_archivo' => trim((string) ($archivo['codTipoAchivoReporte'] ?? $archivo['codTipoArchivoReporte'] ?? '')),
                'nombre_contenido' => trim((string) ($archivo['nomArchivoContenido'] ?? '')),
            ];
        }

        if ($salida === []) {
            $detalle = is_array($registro['detalleTicket'] ?? null) ? $registro['detalleTicket'] : [];
            $nombre = trim((string) ($detalle['nomArchivoReporte'] ?? ''));
            if ($nombre !== '') {
                $salida[] = ['nombre_reporte' => $nombre, 'tipo_archivo' => '', 'nombre_contenido' => ''];
            }
        }
        return $salida;
    }

    private function normalizarPeriodos(array $respuesta): array
    {
        $salida = [];
        $vistos = [];

        $recorrer = function ($nodo, ?string $ejercicio = null) use (&$recorrer, &$salida, &$vistos): void {
            if (!is_array($nodo)) {
                return;
            }

            $ejercicioActual = $ejercicio;
            if (isset($nodo['numEjercicio']) && is_scalar($nodo['numEjercicio'])) {
                $ejercicioActual = (string) $nodo['numEjercicio'];
            }

            if (isset($nodo['perTributario']) && is_scalar($nodo['perTributario'])) {
                $periodo = preg_replace('/\D+/', '', (string) $nodo['perTributario']);
                if (strlen($periodo) === 6 && !isset($vistos[$periodo])) {
                    $vistos[$periodo] = true;
                    $salida[] = [
                        'periodo' => $periodo,
                        'ejercicio' => $ejercicioActual ?: substr($periodo, 0, 4),
                        'codigo_estado' => isset($nodo['codEstado']) ? (string) $nodo['codEstado'] : '',
                        'estado' => isset($nodo['desEstado']) ? (string) $nodo['desEstado'] : '',
                    ];
                }
            }

            foreach ($nodo as $valor) {
                if (is_array($valor)) {
                    $recorrer($valor, $ejercicioActual);
                }
            }
        };

        $recorrer($respuesta);
        usort($salida, static fn (array $a, array $b): int => strcmp($b['periodo'], $a['periodo']));
        return $salida;
    }

    private function minusculas(string $texto): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
    }
}
