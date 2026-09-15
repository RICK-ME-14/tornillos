<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\FacturacionConfig;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\Facturacion\FacturacionManager;
use App\Support\Numerador;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{

    /* ============ Pantalla POS ============ */
    public function pos()
    {
        $clientes = Cliente::where('activo', true)->orderBy('nombre')->get();

        return view('ventas.pos', compact('clientes'));
    }

    /* ============ Búsqueda de productos (JSON para el POS) ============ */
    public function buscarProductos(Request $request)
    {
        $q = trim($request->get('q', ''));

        $productos = Producto::where('activo', true)
            ->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%");
            })
            ->orderBy('nombre')
            ->limit(24)
            ->get(['id', 'codigo', 'nombre', 'precio_venta', 'stock', 'stock_minimo', 'unidad', 'tipo_afectacion_igv']);

        return response()->json($productos);
    }

    /* ============ Registrar venta ============ */
    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'tipo_comprobante' => ['required', 'in:TICKET,BOLETA,FACTURA'],
            'metodo_pago' => ['required', 'in:EFECTIVO,TARJETA,TRANSFERENCIA,YAPE'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'efectivo_recibido' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
        ], [
            'items.required' => 'Agrega al menos un producto al carrito.',
            'items.min' => 'Agrega al menos un producto al carrito.',
        ]);

        // Una factura electrónica exige un cliente con RUC de 11 dígitos; sin él
        // SUNAT la rechaza. Se avisa en caja antes de cobrar, no después.
        if (($error = $this->validarClienteParaFactura($data)) !== null) {
            return response()->json(['message' => $error], 422);
        }

        try {
            $venta = DB::transaction(function () use ($data) {
                // Bloquea los productos involucrados para validar stock de forma segura
                $ids = collect($data['items'])->pluck('producto_id');
                $productos = Producto::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

                $subtotal = 0;
                $lineas = [];

                foreach ($data['items'] as $item) {
                    $prod = $productos[$item['producto_id']];
                    $cant = (int) $item['cantidad'];

                    if ($cant > $prod->stock) {
                        throw new \RuntimeException("Stock insuficiente de \"{$prod->nombre}\" (disponible: {$prod->stock}).");
                    }

                    $lineaSub = round($cant * $prod->precio_venta, 2);
                    $subtotal += $lineaSub;

                    $lineas[] = [
                        'producto' => $prod,
                        'cantidad' => $cant,
                        'precio' => $prod->precio_venta,
                        'afectacion' => $prod->tipo_afectacion_igv ?: '10',
                        'unidad_sunat' => $prod->unidadSunat(),
                        'subtotal' => $lineaSub,
                    ];
                }

                $descuento = round((float) ($data['descuento'] ?? 0), 2);
                $descuento = min($descuento, $subtotal);
                $baseImponible = round($subtotal - $descuento, 2);

                // El descuento global se prorratea sobre todas las líneas, pero
                // solo las gravadas generan IGV (exonerado e inafecto no pagan).
                $factor = $subtotal > 0 ? $baseImponible / $subtotal : 1.0;
                $baseGravada = 0.0;
                foreach ($lineas as $linea) {
                    if ($linea['afectacion'] === '10') {
                        $baseGravada += round($linea['subtotal'] * $factor, 2);
                    }
                }

                $impuesto = round($baseGravada * Empresa::actual()->tasaIgv(), 2);
                $total = round($baseImponible + $impuesto, 2);

                // Efectivo recibido y vuelto (solo para pago en efectivo).
                $efectivoRecibido = null;
                $vuelto = null;
                if ($data['metodo_pago'] === 'EFECTIVO' && isset($data['efectivo_recibido'])) {
                    $efectivoRecibido = round((float) $data['efectivo_recibido'], 2);
                    if ($efectivoRecibido < $total) {
                        throw new \RuntimeException('El efectivo recibido es menor que el total a pagar.');
                    }
                    $vuelto = round($efectivoRecibido - $total, 2);
                }

                $venta = Venta::create([
                    'numero' => $this->siguienteNumero(),
                    'cliente_id' => $data['cliente_id'] ?? null,
                    'user_id' => Auth::id(),
                    'tipo_comprobante' => $data['tipo_comprobante'],
                    'metodo_pago' => $data['metodo_pago'],
                    'subtotal' => $subtotal,
                    'descuento' => $descuento,
                    'impuesto' => $impuesto,
                    'total' => $total,
                    'efectivo_recibido' => $efectivoRecibido,
                    'vuelto' => $vuelto,
                    'estado' => 'COMPLETADA',
                ]);

                foreach ($lineas as $linea) {
                    $prod = $linea['producto'];

                    VentaDetalle::create([
                        'venta_id' => $venta->id,
                        'producto_id' => $prod->id,
                        'descripcion' => $prod->nombre,
                        'cantidad' => $linea['cantidad'],
                        'precio' => $linea['precio'],
                        // Se congela la afectación vigente al momento de vender.
                        'tipo_afectacion_igv' => $linea['afectacion'],
                        'unidad_sunat' => $linea['unidad_sunat'],
                        'subtotal' => $linea['subtotal'],
                    ]);

                    $stockAnterior = $prod->stock;
                    $prod->decrement('stock', $linea['cantidad']);

                    MovimientoInventario::create([
                        'producto_id' => $prod->id,
                        'user_id' => Auth::id(),
                        'tipo' => 'SALIDA',
                        'motivo' => 'VENTA',
                        'cantidad' => $linea['cantidad'],
                        'stock_anterior' => $stockAnterior,
                        'stock_nuevo' => $stockAnterior - $linea['cantidad'],
                        'referencia_type' => Venta::class,
                        'referencia_id' => $venta->id,
                    ]);
                }

                return $venta;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // ---- Facturación electrónica (SUNAT) ----
        // Se ejecuta fuera de la transacción para no retener bloqueos durante el
        // envío. Nunca lanza excepción: en caso de fallo la venta queda registrada
        // y el comprobante en estado PENDIENTE/ERROR para reintentar luego.
        $feResultado = app(FacturacionManager::class)->emitirParaVenta($venta);

        return response()->json([
            'message' => 'Venta registrada correctamente.',
            'venta_id' => $venta->id,
            'fe_estado' => $venta->fe_estado,
            'fe_mensaje' => $feResultado->mensaje,
            'redirect' => route('ventas.show', $venta),
        ]);
    }

    /* ============ Historial de ventas ============ */
    public function index(Request $request)
    {
        $desde = $request->get('desde');
        $hasta = $request->get('hasta');
        $estado = $request->get('estado');
        $q = $request->get('q');

        $ventas = Venta::with(['cliente', 'usuario'])
            ->when($q, fn ($query) => $query->where('numero', 'like', "%{$q}%"))
            ->when($desde, fn ($query) => $query->whereDate('created_at', '>=', $desde))
            ->when($hasta, fn ($query) => $query->whereDate('created_at', '<=', $hasta))
            ->when($estado, fn ($query) => $query->where('estado', $estado))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('ventas.index', compact('ventas', 'desde', 'hasta', 'estado', 'q'));
    }

    /* ============ Ver / Ticket ============ */
    public function show(Venta $venta)
    {
        $venta->load(['detalles', 'cliente', 'usuario']);

        return view('ventas.show', compact('venta'));
    }

    /* ============ Anular venta (repone stock) ============ */
    public function anular(Request $request, Venta $venta)
    {
        if ($venta->estado === 'ANULADA') {
            return back()->with('error', 'La venta ya estaba anulada.');
        }

        $motivo = trim((string) $request->input('motivo', 'ANULACION DE LA OPERACION'));

        DB::transaction(function () use ($venta) {
            foreach ($venta->detalles as $detalle) {
                $prod = Producto::find($detalle->producto_id);
                if (! $prod) {
                    continue;
                }

                $stockAnterior = $prod->stock;
                $prod->increment('stock', $detalle->cantidad);

                MovimientoInventario::create([
                    'producto_id' => $prod->id,
                    'user_id' => Auth::id(),
                    'tipo' => 'ENTRADA',
                    'motivo' => 'ANULACION_VENTA',
                    'cantidad' => $detalle->cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockAnterior + $detalle->cantidad,
                    'referencia_type' => Venta::class,
                    'referencia_id' => $venta->id,
                ]);
            }

            $venta->update(['estado' => 'ANULADA']);
        });

        // Anulación electrónica ante SUNAT (baja / nota de crédito) si el
        // comprobante fue emitido. No interrumpe el flujo si falla.
        $mensaje = "Venta {$venta->numero} anulada y stock repuesto.";
        if ($venta->feEmitido()) {
            $r = app(FacturacionManager::class)->anularVenta($venta, $motivo);
            $mensaje .= ' ' . $r->mensaje;
        }

        return back()->with('success', $mensaje);
    }

    /* ============ Helpers ============ */

    /**
     * Comprueba que una FACTURA lleve un cliente con RUC válido. Solo se exige
     * cuando la facturación electrónica está activa: con la FE apagada la
     * factura es un documento interno y no viaja a SUNAT.
     *
     * @return string|null Mensaje de error, o null si todo está en orden.
     */
    private function validarClienteParaFactura(array $data): ?string
    {
        if (strtoupper($data['tipo_comprobante']) !== 'FACTURA') {
            return null;
        }

        if (! FacturacionConfig::actual()->activa()) {
            return null;
        }

        $cliente = ! empty($data['cliente_id']) ? Cliente::find($data['cliente_id']) : null;

        if (! $cliente) {
            return 'Para emitir una factura electrónica debes seleccionar un cliente con RUC.';
        }

        $ruc = preg_replace('/\D/', '', (string) $cliente->numero_documento);

        if (strlen($ruc) !== 11) {
            return "El cliente \"{$cliente->nombre}\" no tiene un RUC válido de 11 dígitos. "
                . 'Corrige sus datos o emite una boleta.';
        }

        return null;
    }

    /** Siguiente número interno de venta (V-000001). */
    private function siguienteNumero(): string
    {
        return Numerador::siguiente(Venta::class, 'V');
    }
}
