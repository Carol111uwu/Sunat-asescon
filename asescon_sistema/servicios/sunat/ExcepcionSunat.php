<?php

declare(strict_types=1);

final class ExcepcionSunat extends RuntimeException
{
    private ?int $httpStatus;
    private ?string $codigoSunat;

    public function __construct(
        string $message,
        ?int $httpStatus = null,
        ?string $codigoSunat = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->httpStatus = $httpStatus;
        $this->codigoSunat = $codigoSunat;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function getCodigoSunat(): ?string
    {
        return $this->codigoSunat;
    }
}
