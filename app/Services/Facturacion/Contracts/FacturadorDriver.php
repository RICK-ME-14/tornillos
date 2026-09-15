<?php

namespace App\Services\Facturacion\Contracts;

use App\Models\FacturacionConfig;
use App\Models\Venta;
use App\Services\Facturacion\ResultadoEmision;

/**
 * Contrato que debe implementar cualquier motor de emisión electrónica
 * (SUNAT vía Greenter, un proveedor externo/OSE, etc.).
 */
interface FacturadorDriver
{
    /** Emite el comprobante electrónico correspondiente a la venta. */
    public function emitir(Venta $venta, FacturacionConfig $config): ResultadoEmision;

    /** Anula un comprobante (comunicación de baja o nota de crédito). */
    public function anular(Venta $venta, string $motivo, FacturacionConfig $config): ResultadoEmision;

    /** Prueba la conexión/credenciales contra el servicio. */
    public function probarConexion(FacturacionConfig $config): ResultadoEmision;

    /** Identificador del driver (none, greenter, ...). */
    public function nombre(): string;
}
