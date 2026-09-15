<?php

namespace App\Services\Facturacion;

/**
 * Resultado de un intento de emisión o de una prueba de conexión.
 */
class ResultadoEmision
{
    public function __construct(
        public bool $exito,
        public string $estado,          // ACEPTADO | ENVIADO | PENDIENTE | RECHAZADO | ERROR | NO_APLICA
        public string $mensaje = '',
        public ?string $hash = null,
        public ?string $ticket = null,
        public ?string $xmlRuta = null,
        public ?string $cdrRuta = null,
        public ?string $serie = null,
        public ?int $correlativo = null,
    ) {
    }

    public static function ok(string $estado, string $mensaje = '', array $extra = []): self
    {
        return new self(true, $estado, $mensaje, ...static::extra($extra));
    }

    public static function error(string $mensaje, string $estado = 'ERROR', array $extra = []): self
    {
        return new self(false, $estado, $mensaje, ...static::extra($extra));
    }

    private static function extra(array $extra): array
    {
        return [
            $extra['hash'] ?? null,
            $extra['ticket'] ?? null,
            $extra['xmlRuta'] ?? null,
            $extra['cdrRuta'] ?? null,
            $extra['serie'] ?? null,
            $extra['correlativo'] ?? null,
        ];
    }
}
