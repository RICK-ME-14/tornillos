<?php

namespace App\Services\Facturacion;

use App\Models\FacturacionConfig;
use App\Models\FacturacionResumen;
use App\Models\Venta;
use App\Services\Facturacion\Contracts\FacturadorDriver;
use App\Services\Facturacion\Drivers\GreenterDriver;
use App\Services\Facturacion\Drivers\NullDriver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Punto de entrada de la Facturación Electrónica.
 * Resuelve el driver adecuado según la configuración de la empresa y
 * coordina la asignación de serie/correlativo y la actualización de la venta.
 */
class FacturacionManager
{
    /** Resuelve la instancia del driver a partir de su nombre. */
    public function driver(string $nombre): FacturadorDriver
    {
        return match ($nombre) {
            'greenter' => new GreenterDriver(),
            default => new NullDriver(),
        };
    }

    /** Prueba la conexión con SUNAT usando la config indicada. */
    public function probarConexion(FacturacionConfig $config): ResultadoEmision
    {
        return $this->driver($config->driver)->probarConexion($config);
    }

    /**
     * Procesa la emisión electrónica de una venta recién registrada.
     * Nunca lanza excepción: cualquier fallo deja la venta en estado ERROR
     * o PENDIENTE para no romper el cierre de la venta en el POS.
     */
    public function emitirParaVenta(Venta $venta, bool $forzar = false): ResultadoEmision
    {
        $config = FacturacionConfig::actual();
        $tipo = strtoupper($venta->tipo_comprobante);

        // Tickets internos nunca van a SUNAT.
        if (! in_array($tipo, ['BOLETA', 'FACTURA'], true)) {
            return $this->aplicar($venta, ResultadoEmision::ok('NO_APLICA', 'Ticket interno.'));
        }

        // FE deshabilitada: se guarda como comprobante interno sin emisión.
        if (! $config->habilitado) {
            return $this->aplicar($venta, ResultadoEmision::ok('NO_APLICA', 'Facturación electrónica deshabilitada.'));
        }

        // Una factura sin RUC válido será rechazada por SUNAT: se corta aquí
        // para no quemar un correlativo en un envío condenado a fallar.
        if ($tipo === 'FACTURA' && ! $this->clienteTieneRucValido($venta)) {
            return $this->aplicar($venta, ResultadoEmision::error(
                'La factura no tiene un cliente con RUC de 11 dígitos. '
                . 'Corrige los datos del cliente y reenvía el comprobante.',
                'ERROR'
            ));
        }

        // Reserva serie y correlativo ANTES de transmitir (idempotente: conserva
        // los existentes en reintentos).
        $serie = $config->serieDe($tipo);
        if ($serie) {
            $this->reservarNumeracion($venta, $config, $serie);
        }

        // Boletas en modo "resumen": no se envían individualmente; se numeran y
        // quedan PENDIENTE para declararse por el Resumen Diario (RC).
        if ($tipo === 'BOLETA' && $config->boletaPorResumen()) {
            return $this->aplicar($venta, ResultadoEmision::ok(
                'PENDIENTE',
                'Boleta numerada. Se declarará por el Resumen Diario de boletas (RC).'
            ));
        }

        // Sin emisión automática (y sin forzar): se numera y queda pendiente.
        if (! $config->emitir_automatico && ! $forzar) {
            return $this->aplicar($venta, ResultadoEmision::ok(
                'PENDIENTE',
                'Comprobante numerado. Emisión automática desactivada: pendiente de envío a SUNAT.'
            ));
        }

        try {
            $resultado = $this->driver($config->driver)->emitir($venta, $config);
        } catch (\Throwable $e) {
            Log::error('Fallo al emitir comprobante electrónico', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);
            $resultado = ResultadoEmision::error('Error interno al emitir: ' . $e->getMessage());
        }

        return $this->aplicar($venta, $resultado);
    }

    /**
     * Reenvía a SUNAT todos los comprobantes de la empresa activa que
     * quedaron en PENDIENTE o ERROR. Devuelve un resumen del proceso.
     */
    public function reintentarPendientes(): array
    {
        $config = FacturacionConfig::actual();

        $ventas = Venta::whereIn('fe_estado', ['PENDIENTE', 'ERROR'])
            ->where('estado', 'COMPLETADA')
            // En modo "resumen" las boletas no se envían individualmente.
            ->when($config->boletaPorResumen(), fn ($q) => $q->where('tipo_comprobante', '!=', 'BOLETA'))
            ->get();

        $ok = 0;
        $fail = 0;
        foreach ($ventas as $venta) {
            $r = $this->emitirParaVenta($venta, true);
            if ($r->exito && in_array($r->estado, ['ENVIADO', 'ACEPTADO'], true)) {
                $ok++;
            } else {
                $fail++;
            }
        }

        return ['total' => $ventas->count(), 'ok' => $ok, 'fail' => $fail];
    }

    /**
     * Genera y envía a SUNAT el resumen diario de boletas de una fecha
     * (por defecto, el día anterior). Marca las boletas incluidas.
     */
    public function enviarResumenDiario(?Carbon $fecha = null): array
    {
        $config = FacturacionConfig::actual();

        if (! $config->activa()) {
            return ['ok' => false, 'mensaje' => 'Facturación electrónica deshabilitada o sin driver.', 'cantidad' => 0];
        }
        if ($config->driver !== 'greenter') {
            return ['ok' => false, 'mensaje' => 'El resumen de boletas requiere el driver Greenter.', 'cantidad' => 0];
        }

        $fecha = $fecha ? $fecha->copy()->startOfDay() : Carbon::yesterday();

        // Boletas numeradas (PENDIENTE), completadas, aún no incluidas en un RC.
        $boletas = Venta::where('tipo_comprobante', 'BOLETA')
            ->where('fe_estado', 'PENDIENTE')
            ->where('estado', 'COMPLETADA')
            ->whereNull('fe_resumen_id')
            ->whereNotNull('fe_correlativo')
            ->whereDate('created_at', $fecha->toDateString())
            ->with(['cliente', 'detalles'])
            ->get();

        if ($boletas->isEmpty()) {
            return ['ok' => true, 'mensaje' => "No hay boletas por resumir del {$fecha->format('d/m/Y')}.", 'cantidad' => 0];
        }

        // SUNAT identifica el resumen como RC-{fecha de generación}-{correlativo},
        // así que el correlativo se lleva por el día en que se envía —no por el
        // día resumido— o dos RC emitidos hoy para días distintos colisionan.
        // Los intentos con ERROR no consumen numeración.
        $generacion = now();

        [$correlativo, $resumen] = DB::transaction(function () use ($config, $fecha, $generacion, $boletas) {
            DB::table('facturacion_configs')
                ->where('id', $config->getKey())
                ->lockForUpdate()
                ->first();

            $correlativo = FacturacionResumen::whereDate('fecha_generacion', $generacion->toDateString())
                ->where('estado', '!=', 'ERROR')
                ->count() + 1;

            $resumen = FacturacionResumen::create([
                'fecha_referencia' => $fecha->toDateString(),
                'fecha_generacion' => $generacion->toDateString(),
                'identificador' => 'RC-' . $generacion->format('Ymd') . '-' . $correlativo,
                'cantidad' => $boletas->count(),
                'estado' => 'PENDIENTE',
            ]);

            return [$correlativo, $resumen];
        });

        $driver = new GreenterDriver();

        try {
            $r = $driver->enviarResumenBoletas($boletas, $fecha, $generacion, $correlativo, $config);
        } catch (\Throwable $e) {
            Log::error('Fallo al enviar resumen de boletas', ['error' => $e->getMessage()]);
            $r = ResultadoEmision::error('Error interno al enviar el resumen: ' . $e->getMessage());
        }

        $resumen->fill([
            'estado' => $r->estado,
            'ticket' => $r->ticket,
            'mensaje' => $r->mensaje,
            'xml_ruta' => $r->xmlRuta,
        ])->save();

        if ($r->exito) {
            foreach ($boletas as $b) {
                $b->forceFill(['fe_resumen_id' => $resumen->id, 'fe_resumen_estado' => $r->estado])->save();
            }

            // SUNAT procesa el resumen de forma asíncrona: el ticket se consulta
            // más tarde con facturacion:consultar-resumenes, no aquí.
            $this->consultarResumen($resumen, $config);
        }

        return [
            'ok' => $r->exito,
            'mensaje' => $r->mensaje,
            'cantidad' => $boletas->count(),
            'estado' => $resumen->fresh()->estado,
        ];
    }

    /**
     * Consulta en SUNAT los resúmenes que quedaron esperando respuesta y
     * aplica el resultado. Pensado para ejecutarse periódicamente: SUNAT tarda
     * minutos en procesar un RC, así que la consulta del momento del envío casi
     * siempre responde "en proceso".
     */
    public function consultarResumenesPendientes(): array
    {
        $config = FacturacionConfig::actual();

        if (! $config->activa() || $config->driver !== 'greenter') {
            return ['total' => 0, 'resueltos' => 0, 'en_proceso' => 0];
        }

        $resumenes = FacturacionResumen::where('estado', 'ENVIADO')
            ->whereNotNull('ticket')
            ->get();

        $resueltos = 0;
        foreach ($resumenes as $resumen) {
            if ($this->consultarResumen($resumen, $config)) {
                $resueltos++;
            }
        }

        return [
            'total' => $resumenes->count(),
            'resueltos' => $resueltos,
            'en_proceso' => $resumenes->count() - $resueltos,
        ];
    }

    /**
     * Consulta en SUNAT las Comunicaciones de Baja (RA) que quedaron esperando
     * respuesta. Igual que el resumen, la baja devuelve un ticket y se procesa
     * de forma asíncrona: sin esta consulta el comprobante se quedaría en
     * "Enviado" para siempre y nadie sabría si SUNAT aceptó la anulación.
     */
    public function consultarBajasPendientes(): array
    {
        $config = FacturacionConfig::actual();

        if (! $config->activa() || $config->driver !== 'greenter') {
            return ['total' => 0, 'resueltos' => 0, 'en_proceso' => 0];
        }

        $ventas = Venta::whereNotNull('fe_baja_ticket')
            ->where('fe_baja_estado', 'ENVIADO')
            ->get();

        $driver = new GreenterDriver();
        $resueltos = 0;

        foreach ($ventas as $venta) {
            try {
                // El CDR de una baja va a su propia carpeta, no junto a los resúmenes.
                $c = $driver->consultarTicket($venta->fe_baja_ticket, $config, 'baja/cdr');
            } catch (\Throwable $e) {
                Log::error('Fallo al consultar el ticket de la baja', [
                    'venta_id' => $venta->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if (! $c->exito) {
                continue; // Sigue en proceso en SUNAT.
            }

            $venta->forceFill([
                'fe_baja_estado' => $c->estado,
                'fe_baja_cdr_ruta' => $c->cdrRuta ?: $venta->fe_baja_cdr_ruta,
            ])->save();

            $resueltos++;
        }

        return [
            'total' => $ventas->count(),
            'resueltos' => $resueltos,
            'en_proceso' => $ventas->count() - $resueltos,
        ];
    }

    /**
     * Consulta el ticket de un resumen y propaga el resultado a sus boletas.
     *
     * @return bool true si SUNAT ya dio una respuesta definitiva.
     */
    protected function consultarResumen(FacturacionResumen $resumen, FacturacionConfig $config): bool
    {
        if (! $resumen->ticket) {
            return false;
        }

        try {
            $c = (new GreenterDriver())->consultarTicket($resumen->ticket, $config);
        } catch (\Throwable $e) {
            Log::error('Fallo al consultar el ticket del resumen', [
                'resumen_id' => $resumen->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $c->exito) {
            return false; // Sigue en proceso en SUNAT.
        }

        $resumen->fill([
            'estado' => $c->estado,
            'cdr_ruta' => $c->cdrRuta,
            'mensaje' => $c->mensaje,
        ])->save();

        $boletas = Venta::where('fe_resumen_id', $resumen->id)->get();

        foreach ($boletas as $b) {
            if ($c->estado === 'ACEPTADO') {
                // Aceptado el RC, la boleta queda formalmente declarada.
                $b->forceFill([
                    'fe_estado' => 'ACEPTADO',
                    'fe_resumen_estado' => 'ACEPTADO',
                ])->save();

                continue;
            }

            // Rechazado: se sueltan del resumen para que vuelvan a la cola y se
            // incluyan en el siguiente RC. Sin esto quedaban PENDIENTE para
            // siempre, marcadas con un resumen que nadie volvía a mirar.
            $b->forceFill([
                'fe_estado' => 'PENDIENTE',
                'fe_resumen_id' => null,
                'fe_resumen_estado' => $c->estado,
                'fe_observacion' => 'Resumen ' . $resumen->identificador . ' rechazado: ' . $c->mensaje,
            ])->save();
        }

        return true;
    }

    /**
     * Anula electrónicamente el comprobante de una venta ya emitida.
     * No lanza excepción: cualquier fallo deja constancia en la venta.
     */
    public function anularVenta(Venta $venta, string $motivo = ''): ResultadoEmision
    {
        $config = FacturacionConfig::actual();
        $tipo = strtoupper($venta->tipo_comprobante);
        $esFactura = $tipo === 'FACTURA';

        // Solo tiene sentido anular comprobantes que llegaron a SUNAT.
        if (! in_array($venta->fe_estado, ['ENVIADO', 'ACEPTADO'], true)) {
            return new ResultadoEmision(true, $venta->fe_estado ?? 'NO_APLICA',
                'El comprobante no fue aceptado por SUNAT; no requiere anulación electrónica.');
        }

        try {
            $r = $this->driver($config->driver)->anular($venta, $motivo, $config);
        } catch (\Throwable $e) {
            Log::error('Fallo al anular comprobante electrónico', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);
            $r = ResultadoEmision::error('Error interno al anular: ' . $e->getMessage());
        }

        return $this->aplicarAnulacion($venta, $r, $esFactura, $motivo);
    }

    /** Persiste el resultado de la anulación en los campos de baja o NC. */
    protected function aplicarAnulacion(Venta $venta, ResultadoEmision $r, bool $esFactura, string $motivo): ResultadoEmision
    {
        if ($esFactura) {
            $venta->fe_baja_estado = $r->estado;
            $venta->fe_baja_motivo = $motivo ?: 'ANULACION DE LA OPERACION';
            if ($r->ticket) {
                $venta->fe_baja_ticket = $r->ticket;
            }
        } else {
            $venta->fe_nc_estado = $r->estado;
            $venta->fe_nc_motivo = $motivo ?: 'ANULACION DE LA OPERACION';
            if ($r->serie) {
                $venta->fe_nc_serie = $r->serie;
            }
            if ($r->correlativo) {
                $venta->fe_nc_correlativo = $r->correlativo;
            }
            if ($r->hash) {
                $venta->fe_nc_hash = $r->hash;
            }
            if ($r->xmlRuta) {
                $venta->fe_nc_xml_ruta = $r->xmlRuta;
            }
            if ($r->cdrRuta) {
                $venta->fe_nc_cdr_ruta = $r->cdrRuta;
            }
        }

        if (in_array($r->estado, ['ENVIADO', 'ACEPTADO'], true)) {
            $venta->fe_anulado_en = now();
        }
        $venta->save();

        return $r;
    }

    /** Vuelca el resultado sobre la venta y lo persiste. */
    protected function aplicar(Venta $venta, ResultadoEmision $r): ResultadoEmision
    {
        $venta->fe_estado = $r->estado;
        $venta->fe_observacion = $r->mensaje ?: $venta->fe_observacion;
        if ($r->hash) {
            $venta->fe_hash = $r->hash;
        }
        if ($r->ticket) {
            $venta->fe_ticket = $r->ticket;
        }
        if ($r->xmlRuta) {
            $venta->fe_xml_ruta = $r->xmlRuta;
        }
        if ($r->cdrRuta) {
            $venta->fe_cdr_ruta = $r->cdrRuta;
        }
        if ($r->serie) {
            $venta->fe_serie = $r->serie;
        }
        if ($r->correlativo) {
            $venta->fe_correlativo = $r->correlativo;
        }
        if (in_array($r->estado, ['ENVIADO', 'ACEPTADO', 'RECHAZADO'], true)) {
            $venta->fe_enviado_en = now();
        }
        $venta->save();

        return $r;
    }

    /**
     * Reserva serie y correlativo y los persiste antes de cualquier envío.
     *
     * El número debe quedar grabado ANTES de hablar con SUNAT: si el proceso
     * muere durante la transmisión, el comprobante conserva su numeración y el
     * reintento lo reenvía con el mismo número, en vez de reutilizarlo en otra
     * venta. El bloqueo sobre la fila de configuración serializa la numeración
     * entre cajas concurrentes de la misma empresa.
     */
    protected function reservarNumeracion(Venta $venta, FacturacionConfig $config, string $serie): void
    {
        if ($venta->fe_serie && $venta->fe_correlativo) {
            return; // Ya numerado (reintento): se respeta el número original.
        }

        DB::transaction(function () use ($venta, $config, $serie) {
            DB::table('facturacion_configs')
                ->where('id', $config->getKey())
                ->lockForUpdate()
                ->first();

            $venta->fe_serie = $venta->fe_serie ?: $serie;

            if (empty($venta->fe_correlativo)) {
                $venta->fe_correlativo = $this->siguienteCorrelativo($config, $venta->fe_serie);
            }

            $venta->fe_estado = 'PENDIENTE';
            $venta->save();
        });
    }

    /** Siguiente correlativo disponible para una serie dentro de la empresa. */
    protected function siguienteCorrelativo(FacturacionConfig $config, string $serie): int
    {
        $ultimo = Venta::where('empresa_id', $config->empresa_id)
            ->where('fe_serie', $serie)
            ->max('fe_correlativo');

        return ((int) $ultimo) + 1;
    }

    /** ¿La venta tiene un cliente con RUC de 11 dígitos (exigible en facturas)? */
    protected function clienteTieneRucValido(Venta $venta): bool
    {
        $doc = preg_replace('/\D/', '', (string) ($venta->cliente->numero_documento ?? ''));

        return strlen($doc) === 11;
    }
}
