<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventarioController extends Controller
{
    /* ============ Kardex (movimientos) ============ */
    public function kardex(Request $request)
    {
        $productoId = $request->get('producto');
        $tipo = $request->get('tipo');
        $desde = $request->get('desde');
        $hasta = $request->get('hasta');

        $movimientos = MovimientoInventario::with(['producto', 'usuario'])
            ->when($productoId, fn ($q) => $q->where('producto_id', $productoId))
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->when($desde, fn ($q) => $q->whereDate('created_at', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('created_at', '<=', $hasta))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $productos = Producto::orderBy('nombre')->get(['id', 'nombre', 'codigo']);

        return view('inventario.kardex', compact('movimientos', 'productos', 'productoId', 'tipo', 'desde', 'hasta'));
    }

    /* ============ Ajustes de stock ============ */
    public function ajustes(Request $request)
    {
        $productos = Producto::where('activo', true)->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'stock', 'unidad']);

        $ultimos = MovimientoInventario::with(['producto', 'usuario'])
            ->where('tipo', 'AJUSTE')
            ->latest()
            ->limit(10)
            ->get();

        return view('inventario.ajustes', compact('productos', 'ultimos'));
    }

    public function guardarAjuste(Request $request)
    {
        $data = $request->validate([
            'producto_id' => ['required', 'exists:productos,id'],
            'modo' => ['required', 'in:set,add,sub'],
            'cantidad' => ['required', 'integer', 'min:0'],
            'motivo' => ['required', 'string', 'max:100'],
        ], [
            'producto_id.required' => 'Selecciona un producto.',
            'cantidad.min' => 'La cantidad no puede ser negativa.',
            'motivo.required' => 'Indica el motivo del ajuste.',
        ]);

        $huboCambio = DB::transaction(function () use ($data) {
            $prod = Producto::lockForUpdate()->find($data['producto_id']);
            $anterior = $prod->stock;
            $cant = (int) $data['cantidad'];

            $nuevo = match ($data['modo']) {
                'set' => $cant,
                'add' => $anterior + $cant,
                'sub' => max(0, $anterior - $cant),
            };

            $delta = $nuevo - $anterior;

            // Un ajuste que deja el stock igual no es un movimiento: anotarlo
            // con cantidad 0 solo ensucia el kardex.
            if ($delta === 0) {
                return false;
            }

            $prod->update(['stock' => $nuevo]);

            MovimientoInventario::create([
                'producto_id' => $prod->id,
                'user_id' => Auth::id(),
                'tipo' => 'AJUSTE',
                'motivo' => $data['motivo'],
                'cantidad' => abs($delta),
                'stock_anterior' => $anterior,
                'stock_nuevo' => $nuevo,
            ]);

            return true;
        });

        return $huboCambio
            ? back()->with('success', 'Stock ajustado correctamente.')
            : back()->with('warning', 'El stock ya tenía ese valor: no se registró ningún movimiento.');
    }
}
