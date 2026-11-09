<?php

declare(strict_types=1);

final class ConfiguracionSunat
{
    public const MANUAL_VENTAS_VERSION='32';
    public const MANUAL_COMPRAS_VERSION='29';
    public const DOCUMENTACION_REVISADA='2026-10-15';

    public const AUTH_BASE_URL = 'https://api-seguridad.sunat.gob.pe/v1/clientessol';
    public const SIRE_BASE_URL = 'https://api-sire.sunat.gob.pe';
    public const SIRE_SCOPE = 'https://api-sire.sunat.gob.pe';

    public const CONNECT_TIMEOUT_SECONDS = 10;
    public const REQUEST_TIMEOUT_SECONDS = 30;

    public const RUC_FAST_PROVIDER_ENABLED = true;
    public const RUC_FAST_PROVIDER_BASE_URL = 'https://openruc.com';
    public const RUC_FAST_CONNECT_TIMEOUT_SECONDS = 5;
    public const RUC_FAST_TIMEOUT_SECONDS = 10;

    public const PADRON_RUC_URL = 'https://www2.sunat.gob.pe/padron_reducido_ruc.zip';
    public const PADRON_REFRESH_SECONDS = 259200;
    public const PADRON_DOWNLOAD_TIMEOUT_SECONDS = 600;

    private function __construct()
    {
    }
}
