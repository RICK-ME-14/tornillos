<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Venta;
use App\Support\ExportsCsv;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    use ExportsCsv;

    /* ============ Reporte de Ventas ============ */
    public function ventas(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);

        $base = Venta::where('estado', 'COMPLETADA')
            ->whereBetween('created_at', [$desde->startOfDay(), $hasta->endOfDay()]);

        $totalVentas = (clone $base)->sum('total');
        $numVentas = (clone $base)->count();
        $ticketPromedio = $numVentas > 0 ? $totalVentas / $numVentas : 0;
        $impuestos = (clone $base)->sum('impuesto');

        // Por día
        $porDia = (clone $base)
            ->select(DB::raw('DATE(created_at) as dia'), DB::raw('SUM(total) as total'))
            ->groupBy('dia')->orderBy('dia')->get()
            ->map(fn ($r) => ['label' => Carbon::parse($r->dia)->format('d/m'), 'total' => (float) $r->total]);

        // Por método de pago
        $porMetodo = (clone $base)
            ->select('metodo_pago', DB::raw('SUM(total) as total'))
            ->groupBy('metodo_pago')->orderByDesc('total')->get()
            ->map(fn ($r) => ['label' => $r->metodo_pago, 'total' => (float) $r->total]);

        // Top productos (por ingreso)
        $topProductos = DB::table('venta_detalles as vd')
            ->when(Tenant::check(), fn ($q) => $q->where('vd.empresa_id', Tenant::id()))
            ->join('ventas as v', 'v.id', '=', 'vd.venta_id')
            ->where('v.estado', 'COMPLETADA')
            ->whereBetween('v.created_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->select('vd.descripcion', DB::raw('SUM(vd.cantidad) as unidades'), DB::raw('SUM(vd.subtotal) as total'))
            ->groupBy('vd.descripcion')->orderByDesc('total')->limit(10)->get();

        return view('reportes.ventas', [
            'desde' => $desde->format('Y-m-d'),
            'hasta' => $hasta->format('Y-m-d'),
            'totalVentas' => $totalVentas,
            'numVentas' => $numVentas,
            'ticketPromedio' => $ticketPromedio,
            'impuestos' => $impuestos,
            'porDia' => $porDia,
            'porMetodo' => $porMetodo,
            'topProductos' => $topProductos,
        ]);
    }

    /* ============ Reporte de Inventario ============ */
    public function inventario(Request $request)
    {
        $valorCompra = Producto::where('activo', true)
            ->select(DB::raw('SUM(stock * precio_compra) as v'))->value('v') ?? 0;
        $valorVenta = Producto::where('activo', true)
            ->select(DB::raw('SUM(stock * precio_venta) as v'))->value('v') ?? 0;
        $totalProductos = Producto::where('activo', true)->count();
        $unidadesTotales = Producto::where('activo', true)->sum('stock');

        $stockBajo = Producto::with('categoria')->where('activo', true)
            ->stockBajo()->orderBy('stock')->get();

        // Stock (unidades) por categoría
        $porCategoria = DB::table('productos as p')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->where('p.activo', true)
            ->select(DB::raw("COALESCE(c.nombre, 'Sin categoria') as label"), DB::raw('SUM(p.stock) as total'))
            ->groupBy('c.nombre')->orderByDesc('total')->get()
            ->map(fn ($r) => ['label' => $r->label, 'total' => (int) $r->total]);

        // Valorización por categoría (a precio de venta)
        $valorCategoria = DB::table('productos as p')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->where('p.activo', true)
            ->select(DB::raw("COALESCE(c.nombre, 'Sin categoria') as label"), DB::raw('SUM(p.stock * p.precio_venta) as total'))
            ->groupBy('c.nombre')->orderByDesc('total')->get()
            ->map(fn ($r) => ['label' => $r->label, 'total' => (float) $r->total]);

        return view('reportes.inventario', compact(
            'valorCompra', 'valorVenta', 'totalProductos', 'unidadesTotales',
            'stockBajo', 'porCategoria', 'valorCategoria'
        ));
    }

    /* ============ Reporte de Ganancias ============ */
    public function ganancias(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);

        // Utilidad por producto: (precio venta - precio compra) * cantidad
        $detalle = DB::table('venta_detalles as vd')
            ->when(Tenant::check(), fn ($q) => $q->where('vd.empresa_id', Tenant::id()))
            ->join('ventas as v', 'v.id', '=', 'vd.venta_id')
            ->join('productos as p', 'p.id', '=', 'vd.producto_id')
            ->where('v.estado', 'COMPLETADA')
            ->whereBetween('v.created_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->select(
                'vd.descripcion',
                DB::raw('SUM(vd.cantidad) as unidades'),
                DB::raw('SUM(vd.subtotal) as ingresos'),
                DB::raw('SUM(vd.cantidad * p.precio_compra) as costo'),
                DB::raw('SUM(vd.subtotal - (vd.cantidad * p.precio_compra)) as utilidad')
            )
            ->groupBy('vd.descripcion')
            ->orderByDesc('utilidad')
            ->get();

        $ingresos = $detalle->sum('ingresos');
        $costo = $detalle->sum('costo');
        $utilidad = $detalle->sum('utilidad');
        $margen = $ingresos > 0 ? ($utilidad / $ingresos) * 100 : 0;

        // Utilidad por día
        $porDia = DB::table('venta_detalles as vd')
            ->when(Tenant::check(), fn ($q) => $q->where('vd.empresa_id', Tenant::id()))
            ->join('ventas as v', 'v.id', '=', 'vd.venta_id')
            ->join('productos as p', 'p.id', '=', 'vd.producto_id')
            ->where('v.estado', 'COMPLETADA')
            ->whereBetween('v.created_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->select(DB::raw('DATE(v.created_at) as dia'),
                DB::raw('SUM(vd.subtotal - (vd.cantidad * p.precio_compra)) as utilidad'))
            ->groupBy('dia')->orderBy('dia')->get()
            ->map(fn ($r) => ['label' => Carbon::parse($r->dia)->format('d/m'), 'total' => (float) $r->utilidad]);

        return view('reportes.ganancias', [
            'desde' => $desde->format('Y-m-d'),
            'hasta' => $hasta->format('Y-m-d'),
            'ingresos' => $ingresos,
            'costo' => $costo,
            'utilidad' => $utilidad,
            'margen' => $margen,
            'detalle' => $detalle,
            'porDia' => $porDia,
        ]);
    }

    /* ============ Exportaciones a Excel (CSV) ============ */
    public function exportVentas(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);

        $filas = DB::table('venta_detalles as vd')
            ->when(Tenant::check(), fn ($q) => $q->where('vd.empresa_id', Tenant::id()))
            ->join('ventas as v', 'v.id', '=', 'vd.venta_id')
            ->where('v.estado', 'COMPLETADA')
            ->whereBetween('v.created_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->select('vd.descripcion', DB::raw('SUM(vd.cantidad) as unidades'), DB::raw('SUM(vd.subtotal) as total'))
            ->groupBy('vd.descripcion')->orderByDesc('total')->get()
            ->map(fn ($r) => [$r->descripcion, (int) $r->unidades, number_format($r->total, 2, '.', '')]);

        return $this->descargarCsv('reporte_ventas', ['Producto', 'Unidades', 'Total'], $filas);
    }

    public function exportInventario(Request $request)
    {
        $filas = Producto::with(['categoria', 'marca'])->where('activo', true)->orderBy('nombre')->get()
            ->map(fn ($p) => [
                $p->codigo, $p->nombre,
                $p->categoria->nombre ?? '', $p->marca->nombre ?? '',
                $p->stock, $p->stock_minimo,
                number_format($p->precio_compra, 2, '.', ''),
                number_format($p->precio_venta, 2, '.', ''),
                number_format($p->stock * $p->precio_compra, 2, '.', ''),
            ]);

        return $this->descargarCsv('reporte_inventario',
            ['Código', 'Producto', 'Categoría', 'Marca', 'Stock', 'Stock mínimo', 'Precio compra', 'Precio venta', 'Valor a costo'],
            $filas);
    }

    public function exportGanancias(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);

        $filas = DB::table('venta_detalles as vd')
            ->when(Tenant::check(), fn ($q) => $q->where('vd.empresa_id', Tenant::id()))
            ->join('ventas as v', 'v.id', '=', 'vd.venta_id')
            ->join('productos as p', 'p.id', '=', 'vd.producto_id')
            ->where('v.estado', 'COMPLETADA')
            ->whereBetween('v.created_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->select('vd.descripcion',
                DB::raw('SUM(vd.cantidad) as unidades'),
                DB::raw('SUM(vd.subtotal) as ingresos'),
                DB::raw('SUM(vd.cantidad * p.precio_compra) as costo'),
                DB::raw('SUM(vd.subtotal - (vd.cantidad * p.precio_compra)) as utilidad'))
            ->groupBy('vd.descripcion')->orderByDesc('utilidad')->get()
            ->map(function ($r) {
                $margen = $r->ingresos > 0 ? ($r->utilidad / $r->ingresos) * 100 : 0;
                return [
                    $r->descripcion, (int) $r->unidades,
                    number_format($r->ingresos, 2, '.', ''),
                    number_format($r->costo, 2, '.', ''),
                    number_format($r->utilidad, 2, '.', ''),
                    number_format($margen, 1, '.', '') . '%',
                ];
            });

        return $this->descargarCsv('reporte_ganancias',
            ['Producto', 'Unidades', 'Ingresos', 'Costo', 'Utilidad', 'Margen'], $filas);
    }

    /* ============ Helper: rango de fechas ============ */
    private function rango(Request $request): array
    {
        $desde = $request->get('desde')
            ? Carbon::parse($request->get('desde'))
            : Carbon::now()->startOfMonth();

        $hasta = $request->get('hasta')
            ? Carbon::parse($request->get('hasta'))
            : Carbon::now();

        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }
}
