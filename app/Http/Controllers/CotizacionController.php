<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Empresa;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\Facturacion\FacturacionManager;
use App\Support\ExportsCsv;
use App\Support\Numerador;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CotizacionController extends Controller
{
    use ExportsCsv;

    /* ============ Punto de cotización ============ */
    public function punto()
    {
        $clientes = Cliente::where('activo', true)->orderBy('nombre')->get();
        $dias = 15;

        return view('cotizaciones.punto', compact('clientes', 'dias'));
    }

    /* ============ Listado ============ */
    public function index(Request $request)
    {
        $q = $request->get('q');
        $estado = $request->get('estado');
        $desde = $request->get('desde');
        $hasta = $request->get('hasta');

        $cotizaciones = Cotizacion::with(['cliente', 'usuario'])
            ->when($q, fn ($x) => $x->where(function ($s) use ($q) {
                $s->where('numero', 'like', "%{$q}%")
                    ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$q}%"));
            }))
            ->when($estado === 'VENCIDA', fn ($x) => $x->where('estado', 'PENDIENTE')->whereDate('valida_hasta', '<', today()))
            ->when($estado && $estado !== 'VENCIDA', fn ($x) => $x->where('estado', $estado))
            ->when($desde, fn ($x) => $x->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($x) => $x->whereDate('fecha', '<=', $hasta))
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        // El resumen mira todas las cotizaciones, no solo la pagina filtrada.
        $vivas = fn () => Cotizacion::where('estado', 'PENDIENTE')->whereDate('valida_hasta', '>=', today());

        $resumen = [
            'pendientes' => $vivas()->count(),
            'monto_pendiente' => $vivas()->sum('total'),
            'vencidas' => Cotizacion::where('estado', 'PENDIENTE')->whereDate('valida_hasta', '<', today())->count(),
            'aceptadas' => Cotizacion::where('estado', 'ACEPTADA')->count(),
        ];

        return view('cotizaciones.index', compact('cotizaciones', 'resumen', 'q', 'estado', 'desde', 'hasta'));
    }

    /* ============ Guardar ============ */
    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'valida_hasta' => ['required', 'date', 'after_or_equal:today'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
        ], [
            'items.required' => 'Agrega al menos un producto a la cotización.',
            'items.min' => 'Agrega al menos un producto a la cotización.',
            'valida_hasta.after_or_equal' => 'La validez no puede ser una fecha pasada.',
        ]);

        $cotizacion = DB::transaction(function () use ($data) {
            $ids = collect($data['items'])->pluck('producto_id');
            $productos = Producto::whereIn('id', $ids)->get()->keyBy('id');

            // Una cotización no reserva ni descuenta inventario: solo propone
            // un precio. El stock se valida al convertirla en venta.
            $subtotal = 0.0;
            $lineas = [];
            foreach ($data['items'] as $item) {
                $prod = $productos[$item['producto_id']];
                $cant = (int) $item['cantidad'];
                $sub = round($cant * (float) $prod->precio_venta, 2);
                $subtotal += $sub;

                $lineas[] = [
                    'producto' => $prod,
                    'cantidad' => $cant,
                    'precio' => $prod->precio_venta,
                    'afectacion' => $prod->tipo_afectacion_igv ?: '10',
                    'unidad_sunat' => $prod->unidadSunat(),
                    'subtotal' => $sub,
                ];
            }

            $descuento = min(round((float) ($data['descuento'] ?? 0), 2), $subtotal);
            $base = round($subtotal - $descuento, 2);

            // Solo lo gravado paga IGV, igual que en el POS.
            $factor = $subtotal > 0 ? $base / $subtotal : 1.0;
            $gravada = 0.0;
            foreach ($lineas as $l) {
                if ($l['afectacion'] === '10') {
                    $gravada += round($l['subtotal'] * $factor, 2);
                }
            }
            $impuesto = round($gravada * Empresa::actual()->tasaIgv(), 2);

            $cot = Cotizacion::create([
                'numero' => $this->siguienteNumero(),
                'cliente_id' => $data['cliente_id'] ?? null,
                'user_id' => Auth::id(),
                'fecha' => today()->toDateString(),
                'valida_hasta' => $data['valida_hasta'],
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'impuesto' => $impuesto,
                'total' => round($base + $impuesto, 2),
                'estado' => 'PENDIENTE',
                'observacion' => $data['observacion'] ?? null,
            ]);

            foreach ($lineas as $l) {
                CotizacionDetalle::create([
                    'cotizacion_id' => $cot->id,
                    'producto_id' => $l['producto']->id,
                    'descripcion' => $l['producto']->nombre,
                    'cantidad' => $l['cantidad'],
                    'precio' => $l['precio'],
                    'tipo_afectacion_igv' => $l['afectacion'],
                    'unidad_sunat' => $l['unidad_sunat'],
                    'subtotal' => $l['subtotal'],
                ]);
            }

            return $cot;
        });

        return response()->json([
            'message' => "Cotización {$cotizacion->numero} registrada.",
            'cotizacion_id' => $cotizacion->id,
            'redirect' => route('cotizaciones.show', $cotizacion),
        ]);
    }

    /* ============ Ver e imprimir ============ */
    public function show(Cotizacion $cotizacion)
    {
        $cotizacion->load(['detalles.producto', 'cliente', 'usuario', 'venta']);

        return view('cotizaciones.show', compact('cotizacion'));
    }

    public function imprimir(Cotizacion $cotizacion)
    {
        $cotizacion->load(['detalles', 'cliente', 'usuario']);

        return view('cotizaciones.imprimir', compact('cotizacion'));
    }

    /** Formato de 80 mm para ticketera termica. */
    public function ticket(Cotizacion $cotizacion)
    {
        $cotizacion->load(['detalles', 'cliente', 'usuario']);

        return view('cotizaciones.ticket', compact('cotizacion'));
    }

    /* ============ Cambiar estado ============ */
    public function estado(Request $request, Cotizacion $cotizacion)
    {
        $data = $request->validate([
            'estado' => ['required', 'in:RECHAZADA,ANULADA,PENDIENTE'],
        ]);

        if ($cotizacion->venta_id) {
            return back()->with('error', 'No se puede cambiar: la cotización ya se convirtió en venta.');
        }

        $cotizacion->update(['estado' => $data['estado']]);

        return back()->with('success', "Cotización {$cotizacion->numero} marcada como " . strtolower($cotizacion->estadoLabel()) . '.');
    }

    /* ============ Convertir en venta ============ */
    public function convertir(Request $request, Cotizacion $cotizacion)
    {
        if (! $cotizacion->convertible()) {
            $motivo = $cotizacion->venta_id
                ? 'ya se convirtió en la venta ' . optional($cotizacion->venta)->numero
                : ($cotizacion->vencida() ? 'está vencida' : 'está ' . strtolower($cotizacion->estadoLabel()));

            return back()->with('error', "No se puede convertir: la cotización {$motivo}.");
        }

        $data = $request->validate([
            'tipo_comprobante' => ['required', 'in:TICKET,BOLETA,FACTURA'],
            'metodo_pago' => ['required', 'in:EFECTIVO,TARJETA,TRANSFERENCIA,YAPE'],
        ]);

        $cotizacion->load('detalles');

        try {
            $venta = DB::transaction(function () use ($cotizacion, $data) {
                // Ahora sí se toca el inventario: se bloquea y se valida stock,
                // porque entre la cotización y la venta pudo venderse todo.
                $ids = $cotizacion->detalles->pluck('producto_id');
                $productos = Producto::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

                foreach ($cotizacion->detalles as $d) {
                    $prod = $productos[$d->producto_id] ?? null;
                    if (! $prod) {
                        throw new \RuntimeException("El producto \"{$d->descripcion}\" ya no existe.");
                    }
                    if ($d->cantidad > $prod->stock) {
                        throw new \RuntimeException("Stock insuficiente de \"{$prod->nombre}\": quedan {$prod->stock} y la cotización pide {$d->cantidad}.");
                    }
                }

                // Se respetan los importes cotizados, no los precios de hoy.
                $venta = Venta::create([
                    'numero' => $this->siguienteNumeroVenta(),
                    'cliente_id' => $cotizacion->cliente_id,
                    'user_id' => Auth::id(),
                    'tipo_comprobante' => $data['tipo_comprobante'],
                    'metodo_pago' => $data['metodo_pago'],
                    'subtotal' => $cotizacion->subtotal,
                    'descuento' => $cotizacion->descuento,
                    'impuesto' => $cotizacion->impuesto,
                    'total' => $cotizacion->total,
                    'estado' => 'COMPLETADA',
                    'observacion' => "Generada desde la cotización {$cotizacion->numero}.",
                ]);

                foreach ($cotizacion->detalles as $d) {
                    $prod = $productos[$d->producto_id];

                    VentaDetalle::create([
                        'venta_id' => $venta->id,
                        'producto_id' => $prod->id,
                        'descripcion' => $d->descripcion,
                        'cantidad' => $d->cantidad,
                        'precio' => $d->precio,
                        'tipo_afectacion_igv' => $d->tipo_afectacion_igv,
                        'unidad_sunat' => $d->unidad_sunat,
                        'subtotal' => $d->subtotal,
                    ]);

                    $antes = $prod->stock;
                    $prod->decrement('stock', $d->cantidad);

                    MovimientoInventario::create([
                        'producto_id' => $prod->id,
                        'user_id' => Auth::id(),
                        'tipo' => 'SALIDA',
                        'motivo' => 'VENTA',
                        'cantidad' => $d->cantidad,
                        'stock_anterior' => $antes,
                        'stock_nuevo' => $antes - $d->cantidad,
                        'referencia_type' => Venta::class,
                        'referencia_id' => $venta->id,
                    ]);
                }

                $cotizacion->update(['estado' => 'ACEPTADA', 'venta_id' => $venta->id]);

                return $venta;
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Fuera de la transacción, igual que en el POS: si SUNAT falla, la
        // venta ya quedó registrada y el comprobante se reintenta luego.
        $fe = app(FacturacionManager::class)->emitirParaVenta($venta);

        return redirect()->route('ventas.show', $venta)
            ->with('success', "Cotización {$cotizacion->numero} convertida en la venta {$venta->numero}. {$fe->mensaje}");
    }

    /* ============ Exportar ============ */
    public function export(Request $request)
    {
        $filas = Cotizacion::with('cliente')->latest('id')->get()->map(fn ($c) => [
            $c->numero,
            $c->fecha?->format('d/m/Y'),
            $c->valida_hasta?->format('d/m/Y'),
            $c->cliente->nombre ?? 'Sin cliente',
            $c->estadoLabel(),
            number_format($c->total, 2, '.', ''),
            $c->venta->numero ?? '',
        ]);

        return $this->descargarCsv('cotizaciones',
            ['Número', 'Fecha', 'Válida hasta', 'Cliente', 'Estado', 'Total', 'Venta generada'],
            $filas);
    }

    /* ============ Helpers ============ */

    private function siguienteNumero(): string
    {
        return Numerador::siguiente(Cotizacion::class, 'COT');
    }

    private function siguienteNumeroVenta(): string
    {
        return Numerador::siguiente(Venta::class, 'V');
    }
}
