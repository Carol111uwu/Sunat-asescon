<?php

declare(strict_types=1);

require_once __DIR__ . '/ServicioConsultaRapidaRuc.php';
require_once __DIR__ . '/ServicioPadronSunat.php';
require_once __DIR__ . '/ConfiguracionSunat.php';
require_once __DIR__ . '/ExcepcionSunat.php';

final class ServicioConsultaRuc
{
    public function __construct(
        private ?ServicioConsultaRapidaRuc $fuenteSecundaria = null,
        private ?ServicioPadronSunat $padronOficial = null
    ) {
        $this->fuenteSecundaria ??= new ServicioConsultaRapidaRuc();
        $this->padronOficial ??= new ServicioPadronSunat();
    }

    public function consultarPorRuc(string $ruc): array
    {
        $ruc = trim($ruc);
        if (!preg_match('/^[0-9]{11}$/', $ruc)) {
            throw new ExcepcionSunat('El RUC debe contener exactamente 11 dígitos.');
        }

        $cacheVigente = $this->padronOficial->cacheVigente();
        $cacheDisponible = $this->padronOficial->cacheDisponible();

        if (!$cacheVigente) {
            try {
                $this->padronOficial->iniciarActualizacionEnSegundoPlano(false);
            } catch (Throwable $error) {
                error_log('ASESCON actualización padrón RUC oficial: ' . $error->getMessage());
            }
        }

        if ($cacheVigente || $cacheDisponible) {
            try {
                $datos = $this->padronOficial->consultarPorRuc($ruc);
                $datos['fuente'] = 'Padrón Reducido del RUC - SUNAT';
                $datos['origen_tecnico'] = 'sunat_padron_oficial';
                $datos['padron_vigente'] = $cacheVigente;
                return $datos;
            } catch (Throwable $error) {
                error_log('ASESCON padrón RUC oficial: ' . $error->getMessage());
            }
        }

        try {
            $datos = $this->fuenteSecundaria->consultarPorRuc($ruc);
            $datos['fuente'] = 'Consulta rápida de datos públicos RUC (fuente secundaria)';
            $datos['origen_tecnico'] = 'proveedor_ruc_secundario';
            return $datos;
        } catch (Throwable $error) {
            error_log('ASESCON fuente secundaria RUC: ' . $error->getMessage());
        }

        throw new ExcepcionSunat(
            'No se pudieron obtener datos reales del RUC desde las fuentes configuradas. Intenta nuevamente más tarde.',
            502
        );
    }

    public function consultarPorRucOpcional(string $ruc): ?array
    {
        try {
            return $this->consultarPorRuc($ruc);
        } catch (Throwable $error) {
            error_log('ASESCON consulta RUC: ' . get_class($error) . ' - ' . $error->getMessage());
            return null;
        }
    }
}
