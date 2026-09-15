@extends('layouts.app')

@section('title', 'Reporte de Inventario')

@section('content')
    <div class="page-head no-print">
        <h1>REPORTE DE INVENTARIO</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Reportes <span class="sep">/</span> Inventario
        </div>
    </div>

    <div class="toolbar no-print">
        <div class="spacer"></div>
        <a href="{{ route('reportes.inventario.export') }}" class="btn btn-success btn-sm"><i class="fa-solid fa-file-excel"></i> Exportar Excel</a>
        <button onclick="window.print()" class="btn btn-light btn-sm"><i class="fa-solid fa-print"></i> Imprimir</button>
    </div>

    <div class="cards">
        <div class="stat blue">
            <i class="fa-solid fa-boxes-stacked icon"></i>
            <div><div class="num">S/ {{ number_format($valorCompra, 2) }}</div><div class="label">Valor a costo</div></div>
            <i class="fa-solid fa-boxes-stacked bg-ico"></i>
        </div>
        <div class="stat orange">
            <i class="fa-solid fa-tags icon"></i>
            <div><div class="num">S/ {{ number_format($valorVenta, 2) }}</div><div class="label">Valor a venta</div></div>
            <i class="fa-solid fa-tags bg-ico"></i>
        </div>
        <div class="stat purple">
            <i class="fa-solid fa-box icon"></i>
            <div><div class="num">{{ number_format($totalProductos) }}</div><div class="label">Productos activos</div></div>
            <i class="fa-solid fa-box bg-ico"></i>
        </div>
        <div class="stat lime">
            <i class="fa-solid fa-cubes icon"></i>
            <div><div class="num">{{ number_format($unidadesTotales) }}</div><div class="label">Unidades en stock</div></div>
            <i class="fa-solid fa-cubes bg-ico"></i>
        </div>
    </div>

    <div class="grid-2">
        <div class="panel">
            <h3><span class="dot"></span> Unidades por categoría</h3>
            <div class="chart-box"><canvas id="chartStock"></canvas></div>
        </div>
        <div class="panel">
            <h3><span class="dot"></span> Valorización por categoría (venta)</h3>
            <div class="chart-box"><canvas id="chartValor"></canvas></div>
        </div>
    </div>

    <div class="panel">
        <h3><span class="dot"></span> Productos con stock bajo o agotado</h3>
        <div class="panel-scroll">
        <table class="table">
            <thead><tr><th>Código</th><th>Producto</th><th>Categoría</th><th>Stock</th><th>Mínimo</th><th>Estado</th></tr></thead>
            <tbody>
            @forelse($stockBajo as $p)
                @php $clase = $p->stock <= 0 ? 'out' : 'low'; @endphp
                <tr>
                    <td><span class="badge-soft">{{ $p->codigo }}</span></td>
                    <td>{{ $p->nombre }}</td>
                    <td>{{ $p->categoria->nombre ?? '—' }}</td>
                    <td><span class="badge-stock {{ $clase }}">{{ $p->stock }} {{ $p->unidad }}</span></td>
                    <td>{{ $p->stock_minimo }}</td>
                    <td><span class="pill warn">{{ $p->stock <= 0 ? 'Agotado' : 'Stock bajo' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#1e9e5a;padding:20px"><i class="fa-solid fa-circle-check"></i> Todos los productos tienen stock suficiente.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
const porCat = @json($porCategoria);
const valorCat = @json($valorCategoria);
const money = v => 'S/ ' + Number(v).toLocaleString('es-PE');
const base = {responsive:true, maintainAspectRatio:false};
const paleta = ['#d18a1c','#4b5866','#a87552','#7b8794','#e2a13a','#5c6874','#8a6b4f','#333e49'];

new Chart(document.getElementById('chartStock'), {
    type:'bar',
    data:{labels:porCat.map(c=>c.label),datasets:[{data:porCat.map(c=>c.total),backgroundColor:'#d18a1c',borderRadius:6,maxBarThickness:46}]},
    options:{...base,indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true}}}
});
new Chart(document.getElementById('chartValor'), {
    type:'doughnut',
    data:{labels:valorCat.map(c=>c.label),datasets:[{data:valorCat.map(c=>c.total),backgroundColor:paleta,borderColor:'#fff',borderWidth:2}]},
    options:{...base,cutout:'58%',plugins:{legend:{position:'right'},tooltip:{callbacks:{label:c=>' '+c.label+': '+money(c.parsed)}}}}
});
</script>
@endpush
