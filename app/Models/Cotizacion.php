<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Propuesta de precio para un cliente. No mueve inventario ni emite
 * comprobante: eso ocurre solo si se convierte en venta.
 */
class Cotizacion extends Model
{
    use BelongsToEmpresa;

    protected $table = 'cotizaciones';

    protected $fillable = [
        'empresa_id', 'numero', 'cliente_id', 'user_id',
        'fecha', 'valida_hasta',
        'subtotal', 'descuento', 'impuesto', 'total',
        'estado', 'venta_id', 'observacion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'valida_hasta' => 'date',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'impuesto' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // ------------------------------------------------------------------
    // Estado
    // ------------------------------------------------------------------

    /** ¿Se pasó de la fecha de validez sin resolverse? */
    public function vencida(): bool
    {
        return $this->estado === 'PENDIENTE'
            && $this->valida_hasta
            && $this->valida_hasta->endOfDay()->isPast();
    }

    /** Estado que se muestra: incorpora el vencimiento, que no se guarda. */
    public function estadoVisible(): string
    {
        return $this->vencida() ? 'VENCIDA' : $this->estado;
    }

    public function estadoLabel(): string
    {
        return match ($this->estadoVisible()) {
            'PENDIENTE' => 'Pendiente',
            'ACEPTADA' => 'Aceptada',
            'RECHAZADA' => 'Rechazada',
            'ANULADA' => 'Anulada',
            'VENCIDA' => 'Vencida',
            default => $this->estado,
        };
    }

    /** Días que le quedan de vigencia (negativo si ya vencio). */
    public function diasDeVigencia(): int
    {
        return $this->valida_hasta
            ? (int) Carbon::today()->diffInDays($this->valida_hasta, false)
            : 0;
    }

    /**
     * ¿Se puede convertir en venta? Solo una cotización viva y no convertida
     * antes: una propuesta vencida o ya rechazada debe rehacerse, no cobrarse.
     */
    public function convertible(): bool
    {
        return $this->estado === 'PENDIENTE' && ! $this->venta_id && ! $this->vencida();
    }

    // ------------------------------------------------------------------

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
        return $this->hasMany(CotizacionDetalle::class);
    }

    /** Venta que nació de esta cotización. */
    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }
}
