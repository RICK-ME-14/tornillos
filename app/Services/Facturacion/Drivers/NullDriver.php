<?php

namespace App\Services\Facturacion\Drivers;

use App\Models\FacturacionConfig;
use App\Models\Venta;
use App\Services\Facturacion\Contracts\FacturadorDriver;
use App\Services\Facturacion\ResultadoEmision;

/**
 * Driver "Ninguno": no envía nada a SUNAT. Deja el comprobante en estado
 * PENDIENTE para que pueda emitirse manualmente más tarde, o NO_APLICA si
 * el tipo de comprobante es un simple ticket interno.
 */
class NullDriver implements FacturadorDriver
{
    public function emitir(Venta $venta, FacturacionConfig $config): ResultadoEmision
    {
        if (! in_array(strtoupper($venta->tipo_comprobante), ['BOLETA', 'FACTURA'], true)) {
            return ResultadoEmision::ok('NO_APLICA', 'Ticket interno: no requiere emisión electrónica.');
        }

        return ResultadoEmision::ok(
            'PENDIENTE',
            'Comprobante registrado como pendiente (no se envía a SUNAT: driver "Ninguno").'
        );
    }

    public function anular(Venta $venta, string $motivo, FacturacionConfig $config): ResultadoEmision
    {
        return ResultadoEmision::error(
            'No hay un motor de emisión seleccionado: la anulación queda pendiente.',
            'PENDIENTE'
        );
    }

    public function probarConexion(FacturacionConfig $config): ResultadoEmision
    {
        return ResultadoEmision::error(
            'No hay un motor de emisión seleccionado. Elige el driver "Greenter (SUNAT)" para emitir electrónicamente.',
            'ERROR'
        );
    }

    public function nombre(): string
    {
        return 'none';
    }
}
