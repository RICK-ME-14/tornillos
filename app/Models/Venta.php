<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use BelongsToEmpresa;

    protected $table = 'ventas';

    protected $fillable = [
        'empresa_id',
        'numero', 'cliente_id', 'user_id', 'tipo_comprobante', 'metodo_pago',
        'subtotal', 'impuesto', 'descuento', 'total', 'efectivo_recibido', 'vuelto', 'estado', 'observacion',
        // Facturación electrónica (SUNAT)
        'fe_serie', 'fe_correlativo', 'fe_estado', 'fe_hash', 'fe_ticket',
        'fe_observacion', 'fe_xml_ruta', 'fe_cdr_ruta', 'fe_enviado_en',
        // Anulación electrónica
        'fe_baja_ticket', 'fe_baja_estado', 'fe_baja_motivo',
        'fe_baja_correlativo', 'fe_baja_fecha', 'fe_baja_cdr_ruta',
        'fe_nc_serie', 'fe_nc_correlativo', 'fe_nc_estado', 'fe_nc_hash',
        'fe_nc_motivo', 'fe_nc_xml_ruta', 'fe_nc_cdr_ruta', 'fe_anulado_en',
        // Correo y resumen diario
        'fe_email_enviado_en', 'fe_resumen_estado', 'fe_resumen_id',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'impuesto' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'efectivo_recibido' => 'decimal:2',
        'vuelto' => 'decimal:2',
        'fe_enviado_en' => 'datetime',
        'fe_anulado_en' => 'datetime',
        'fe_baja_fecha' => 'date',
        'fe_email_enviado_en' => 'datetime',
    ];

    /**
     * Reparte la venta entre las afectaciones al IGV (catálogo 07 de SUNAT),
     * prorrateando el descuento global sobre todas las líneas.
     *
     * Es la única fuente de este cálculo: la usan tanto el XML que se envía a
     * SUNAT como la representación impresa, que deben coincidir siempre.
     *
     * @return array{lineas: array, gravadas: float, exoneradas: float, inafectas: float, base: float}
     */
    public function desgloseAfectacion(): array
    {
        $lineas = [];
        $bruto = 0.0;
        foreach ($this->detalles as $d) {
            $b = round((float) $d->precio * (float) $d->cantidad, 2);
            $bruto = round($bruto + $b, 2);
            $lineas[] = ['detalle' => $d, 'bruto' => $b, 'afectacion' => $d->tipo_afectacion_igv ?: '10'];
        }

        $base = round($bruto - (float) $this->descuento, 2);
        $factor = $bruto > 0 ? $base / $bruto : 1.0;

        $buckets = ['10' => 0.0, '20' => 0.0, '30' => 0.0];
        $acumulado = 0.0;
        $n = count($lineas);

        foreach ($lineas as $i => &$l) {
            // La última línea absorbe el residuo del prorrateo.
            $l['base'] = $i === $n - 1
                ? round($base - $acumulado, 2)
                : round($l['bruto'] * $factor, 2);

            $acumulado = round($acumulado + $l['base'], 2);
            $buckets[$l['afectacion']] = round($buckets[$l['afectacion']] + $l['base'], 2);
        }
        unset($l);

        return [
            'lineas' => $lineas,
            'gravadas' => $buckets['10'],
            'exoneradas' => $buckets['20'],
            'inafectas' => $buckets['30'],
            'base' => $base,
        ];
    }

    /** Número del comprobante electrónico: B001-00000001 */
    public function comprobanteElectronico(): ?string
    {
        if (! $this->fe_serie || ! $this->fe_correlativo) {
            return null;
        }
        return $this->fe_serie . '-' . str_pad((string) $this->fe_correlativo, 8, '0', STR_PAD_LEFT);
    }

    /** Etiqueta legible del estado SUNAT. */
    public function feEstadoLabel(): string
    {
        return match ($this->fe_estado) {
            'ACEPTADO' => 'Aceptado por SUNAT',
            'ENVIADO' => 'Enviado a SUNAT',
            'PENDIENTE' => 'Pendiente de envío',
            'RECHAZADO' => 'Rechazado por SUNAT',
            'ERROR' => 'Error de emisión',
            default => 'No aplica',
        };
    }

    /** ¿El comprobante fue realmente emitido a SUNAT (aceptado o enviado)? */
    public function feEmitido(): bool
    {
        return in_array($this->fe_estado, ['ENVIADO', 'ACEPTADO'], true);
    }

    /** Número de la nota de crédito: BC01-00000001 */
    public function notaCreditoNumero(): ?string
    {
        if (! $this->fe_nc_serie || ! $this->fe_nc_correlativo) {
            return null;
        }
        return $this->fe_nc_serie . '-' . str_pad((string) $this->fe_nc_correlativo, 8, '0', STR_PAD_LEFT);
    }

    /** Estado de la anulación electrónica (baja o nota de crédito). */
    public function anulacionEstado(): ?string
    {
        return $this->fe_baja_estado ?? $this->fe_nc_estado;
    }

    /** Descripción del tipo de comprobante para la representación impresa. */
    public function comprobanteTitulo(): string
    {
        return match (strtoupper($this->tipo_comprobante)) {
            'FACTURA' => 'FACTURA ELECTRÓNICA',
            'BOLETA' => 'BOLETA DE VENTA ELECTRÓNICA',
            default => 'COMPROBANTE',
        };
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class);
    }
}
