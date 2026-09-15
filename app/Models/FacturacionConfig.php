<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Configuración de Facturación Electrónica (SUNAT) de una empresa.
 * Las credenciales sensibles (clave SOL y clave del certificado) se
 * almacenan encriptadas mediante los accessors/mutators.
 */
class FacturacionConfig extends Model
{
    use BelongsToEmpresa;

    protected $table = 'facturacion_configs';

    protected $fillable = [
        'empresa_id',
        'habilitado', 'emitir_automatico', 'modo_boleta', 'driver', 'entorno', 'pais',
        'ruc', 'razon_social', 'nombre_comercial', 'direccion_fiscal',
        'ubigeo', 'departamento', 'provincia', 'distrito',
        'serie_boleta', 'serie_factura', 'serie_nota_credito', 'serie_nc_factura',
        'sol_user', 'sol_pass', 'certificado_ruta', 'certificado_pass',
        'probado_en', 'estado_conexion', 'mensaje_conexion',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
        'emitir_automatico' => 'boolean',
        'probado_en' => 'datetime',
    ];

    /** Cache en memoria durante el request. */
    protected static ?self $cache = null;

    // ------------------------------------------------------------------
    // Encriptado de credenciales sensibles
    // ------------------------------------------------------------------

    public function setSolPassAttribute($value): void
    {
        $this->attributes['sol_pass'] = ($value === null || $value === '')
            ? null
            : Crypt::encryptString($value);
    }

    public function getSolPassAttribute($value): ?string
    {
        return $this->descifrar($value);
    }

    public function setCertificadoPassAttribute($value): void
    {
        $this->attributes['certificado_pass'] = ($value === null || $value === '')
            ? null
            : Crypt::encryptString($value);
    }

    public function getCertificadoPassAttribute($value): ?string
    {
        return $this->descifrar($value);
    }

    private function descifrar(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return null; // valor no encriptado (datos previos) o clave rotada
        }
    }

    // ------------------------------------------------------------------
    // Helpers de estado
    // ------------------------------------------------------------------

    /** ¿La FE está activa y con un driver que realmente emite? */
    public function activa(): bool
    {
        return $this->habilitado && $this->driver !== 'none';
    }

    /** ¿Las boletas se declaran por Resumen Diario (RC) en vez de individualmente? */
    public function boletaPorResumen(): bool
    {
        return $this->modo_boleta === 'resumen';
    }

    /** ¿Existe físicamente el certificado en la ruta indicada? */
    public function certificadoExiste(): bool
    {
        return $this->certificado_ruta
            && is_file($this->certificado_ruta);
    }

    /** ¿Está todo lo mínimo configurado para emitir? */
    public function listaParaEmitir(): bool
    {
        return $this->habilitado
            && $this->driver !== 'none'
            && ! empty($this->ruc)
            && ! empty($this->razon_social);
    }

    /** Serie según el tipo de comprobante. */
    public function serieDe(string $tipoComprobante): ?string
    {
        return match (strtoupper($tipoComprobante)) {
            'FACTURA' => $this->serie_factura,
            'BOLETA' => $this->serie_boleta,
            default => null,
        };
    }

    /** Serie de nota de crédito según el comprobante afectado. */
    public function serieNotaCreditoDe(string $tipoComprobante): string
    {
        return strtoupper($tipoComprobante) === 'FACTURA'
            ? ($this->serie_nc_factura ?: 'FC01')
            : ($this->serie_nota_credito ?: 'BC01');
    }

    // ------------------------------------------------------------------
    // Acceso por tenant
    // ------------------------------------------------------------------

    /**
     * Devuelve (o crea) la configuración de la empresa activa.
     */
    public static function actual(): self
    {
        // Siempre atada a la empresa activa (o a la que resuelve Empresa::actual()
        // cuando no hay tenant en contexto), nunca a una fila arbitraria.
        $empresa = Empresa::actual();
        $empresaId = $empresa->getKey();

        if (static::$cache && static::$cache->empresa_id === $empresaId) {
            return static::$cache;
        }

        $config = static::query()->where('empresa_id', $empresaId)->first();

        if (! $config) {
            $config = static::create([
                'empresa_id' => $empresaId,
                'ruc' => $empresa->ruc,
                'razon_social' => $empresa->nombre,
                'nombre_comercial' => $empresa->nombre,
                'direccion_fiscal' => $empresa->direccion,
            ]);
        }

        return static::$cache = $config;
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::$cache = null);
    }
}
