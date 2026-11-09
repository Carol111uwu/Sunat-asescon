<?php

declare(strict_types=1);

interface ProveedorSms
{
    public function enviar(string $destino, string $mensaje): void;
}
