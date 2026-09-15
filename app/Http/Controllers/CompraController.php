<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Empresa;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Support\Numerador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');
        $estado = $request->get('estado');

        $compras = Compra::with(['proveedor', 'usuario'])
            ->when($q, fn ($query) => $query->where('numero', 'like', "%{$q}%"))
            ->when($estado, fn ($query) => $query->where('estado', $estado))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('compras.index', compact('compras', 'q', 'estado'));
    }

    public function create()
    {
        return view('compras.create', [
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(),
            'productos' => Producto::where('activo', true)->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'precio_compra', 'stock']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'proveedor_id' => ['required', 'exists:proveedores,id'],
            'fecha' => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.precio' => ['required', 'numeric', 'min:0'],
        ], [
            'proveedor_id.required' => 'Selecciona un proveedor.',
            'items.required' => 'Agrega al menos un producto.',
            'items.min' => 'Agrega al menos un producto.',
        ]);

        $compra = DB::transaction(function () use ($data) {
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $subtotal += round($item['cantidad'] * $item['precio'], 2);
            }
            $impuesto = round($subtotal * Empresa::actual()->tasaIgv(), 2);
            $total = $subtotal + $impuesto;

            $compra = Compra::create([
                'numero' => $this->siguienteNumero(),
                'proveedor_id' => $data['proveedor_id'],
                'user_id' => Auth::id(),
                'fecha' => $data['fecha'],
                'subtotal' => $subtotal,
                'impuesto' => $impuesto,
                'total' => $total,
                'estado' => 'RECIBIDA',
                'observacion' => $data['observacion'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $prod = Producto::lockForUpdate()->find($item['producto_id']);
                $cant = (int) $item['cantidad'];
                $precio = round((float) $item['precio'], 2);

                CompraDetalle::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $prod->id,
                    'cantidad' => $cant,
                    'precio' => $precio,
                    'subtotal' => round($cant * $precio, 2),
                ]);

                $stockAnterior = $prod->stock;
                $prod->increment('stock', $cant);
                // Actualiza el costo de compra al último precio pagado
                $prod->update(['precio_compra' => $precio]);

                MovimientoInventario::create([
                    'producto_id' => $prod->id,
                    'user_id' => Auth::id(),
                    'tipo' => 'ENTRADA',
                    'motivo' => 'COMPRA',
                    'cantidad' => $cant,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockAnterior + $cant,
                    'referencia_type' => Compra::class,
                    'referencia_id' => $compra->id,
                ]);
            }

            return $compra;
        });

        return redirect()->route('compras.show', $compra)
            ->with('success', "Compra {$compra->numero} registrada. Stock actualizado.");
    }

    public function show(Compra $compra)
    {
        $compra->load(['detalles.producto', 'proveedor', 'usuario']);

        return view('compras.show', compact('compra'));
    }

    public function destroy(Compra $compra)
    {
        // Eliminar una compra recibida repondría inconsistencias; se prefiere anular.
        return $this->anular($compra);
    }

    public function anular(Compra $compra)
    {
        if ($compra->estado === 'ANULADA') {
            return back()->with('error', 'La compra ya estaba anulada.');
        }

        DB::transaction(function () use ($compra) {
            foreach ($compra->detalles as $detalle) {
                $prod = Producto::lockForUpdate()->find($detalle->producto_id);
                if (! $prod) {
                    continue;
                }

                $stockAnterior = $prod->stock;
                // Descuenta lo que había entrado (sin dejar negativo)
                $descontar = min($detalle->cantidad, $prod->stock);
                $prod->decrement('stock', $descontar);

                MovimientoInventario::create([
                    'producto_id' => $prod->id,
                    'user_id' => Auth::id(),
                    'tipo' => 'SALIDA',
                    'motivo' => 'ANULACION_COMPRA',
                    'cantidad' => $descontar,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockAnterior - $descontar,
                    'referencia_type' => Compra::class,
                    'referencia_id' => $compra->id,
                ]);
            }

            $compra->update(['estado' => 'ANULADA']);
        });

        return back()->with('success', "Compra {$compra->numero} anulada y stock ajustado.");
    }

    /** Siguiente número interno de compra (C-000001). */
    private function siguienteNumero(): string
    {
        return Numerador::siguiente(Compra::class, 'C');
    }
}
