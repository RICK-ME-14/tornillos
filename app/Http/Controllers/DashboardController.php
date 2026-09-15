<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Venta;
use Carbon\Carbon;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $hoy = Carbon::today();
        $inicioMes = Carbon::now()->startOfMonth();

        // Tarjetas superiores
        $ventasHoy = Venta::where('estado', 'COMPLETADA')
            ->whereDate('created_at', $hoy)
            ->sum('total');

        $ventasMes = Venta::where('estado', 'COMPLETADA')
            ->where('created_at', '>=', $inicioMes)
            ->sum('total');

        $totalProductos = Producto::where('activo', true)->count();
        $totalClientes = Cliente::where('activo', true)->count();
        $productosStockBajo = Producto::stockBajo()->where('activo', true)->count();

        // Grafico 1: ventas de los ultimos 14 dias (lineas)
        $dias = collect(range(13, 0))->map(function ($i) {
            $fecha = Carbon::today()->subDays($i);
            $total = Venta::where('estado', 'COMPLETADA')
                ->whereDate('created_at', $fecha)
                ->sum('total');
            return [
                'label' => $fecha->format('d/m'),
                'total' => (float) $total,
            ];
        });

        // Grafico 2: ventas por mes del anio actual (area)
        $meses = collect(range(1, 12))->map(function ($mes) {
            $total = Venta::where('estado', 'COMPLETADA')
                ->whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', $mes)
                ->sum('total');
            return [
                'label' => Carbon::create()->month($mes)->locale('es')->isoFormat('MMM'),
                'total' => (float) $total,
            ];
        });

        // Grafico 3: ventas por metodo de pago (doughnut)
        $metodosPago = Venta::where('estado', 'COMPLETADA')
            ->select('metodo_pago', DB::raw('SUM(total) as total'))
            ->groupBy('metodo_pago')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['label' => $r->metodo_pago, 'total' => (float) $r->total]);

        // Grafico 4: ventas por categoria (barras horizontales)
        $ventasCategoria = DB::table('venta_detalles as vd')
            ->when(Tenant::check(), fn ($q) => $q->where('vd.empresa_id', Tenant::id()))
            ->join('productos as p', 'p.id', '=', 'vd.producto_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->select(DB::raw("COALESCE(c.nombre, 'Sin categoria') as label"), DB::raw('SUM(vd.subtotal) as total'))
            ->groupBy('c.nombre')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'total' => (float) $r->total]);

        // Productos mas vendidos
        $topProductos = DB::table('venta_detalles')
            ->when(Tenant::check(), fn ($q) => $q->where('empresa_id', Tenant::id()))
            ->select('descripcion', DB::raw('SUM(cantidad) as unidades'), DB::raw('SUM(subtotal) as total'))
            ->groupBy('descripcion')
            ->orderByDesc('unidades')
            ->limit(5)
            ->get();

        // Ultimas ventas
        $ultimasVentas = Venta::with('cliente')
            ->latest()
            ->limit(6)
            ->get();

        return view('dashboard.index', compact(
            'ventasHoy', 'ventasMes', 'totalProductos', 'totalClientes',
            'productosStockBajo', 'dias', 'meses', 'metodosPago', 'ventasCategoria',
            'topProductos', 'ultimasVentas'
        ));
    }
}
