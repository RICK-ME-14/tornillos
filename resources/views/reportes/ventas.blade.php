@extends('layouts.app')

@section('title', 'Reporte de Ventas')

@section('content')
    <div class="page-head no-print">
        <h1>REPORTE DE VENTAS</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Reportes <span class="sep">/</span> Ventas
        </div>
    </div>

    <div class="toolbar no-print">
        <form method="GET" action="{{ route('reportes.ventas') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div class="search-box no-ico"><label style="font-size:11px;color:#8a929b">Desde</label><br><input type="date" name="desde" value="{{ $desde }}"></div>
            <div class="search-box no-ico"><label style="font-size:11px;color:#8a929b">Hasta</label><br><input type="date" name="hasta" value="{{ $hasta }}"></div>
            <button class="btn btn-primary btn-sm" style="width:auto"><i class="fa-solid fa-filter"></i> Aplicar</button>
        </form>
        <div class="spacer"></div>
        <a href="{{ route('reportes.ventas.export', ['desde' => $desde, 'hasta' => $hasta]) }}" class="btn btn-success btn-sm"><i class="fa-solid fa-file-excel"></i> Exportar Excel</a>
        <button onclick="window.print()" class="btn btn-light btn-sm"><i class="fa-solid fa-print"></i> Imprimir</button>
    </div>

    <div class="cards">
        <div class="stat blue">
            <i class="fa-solid fa-sack-dollar icon"></i>
            <div><div class="num">S/ {{ number_format($totalVentas, 2) }}</div><div class="label">Total vendido</div></div>
            <i class="fa-solid fa-sack-dollar bg-ico"></i>
        </div>
        <div class="stat orange">
            <i class="fa-solid fa-receipt icon"></i>
            <div><div class="num">{{ number_format($numVentas) }}</div><div class="label">N° de ventas</div></div>
            <i class="fa-solid fa-receipt bg-ico"></i>
        </div>
        <div class="stat purple">
            <i class="fa-solid fa-calculator icon"></i>
            <div><div class="num">S/ {{ number_format($ticketPromedio, 2) }}</div><div class="label">Ticket promedio</div></div>
            <i class="fa-solid fa-calculator bg-ico"></i>
        </div>
        <div class="stat lime">
            <i class="fa-solid fa-percent icon"></i>
            <div><div class="num">S/ {{ number_format($impuestos, 2) }}</div><div class="label">IGV recaudado</div></div>
            <i class="fa-solid fa-percent bg-ico"></i>
        </div>
    </div>

    <div class="grid-2">
        <div class="panel">
            <h3><span class="dot"></span> Ventas por día</h3>
            <div class="chart-box"><canvas id="chartDia"></canvas></div>
        </div>
        <div class="panel">
            <h3><span class="dot"></span> Ventas por método de pago</h3>
            <div class="chart-box"><canvas id="chartMetodo"></canvas></div>
        </div>
    </div>

    <div class="panel">
        <h3><span class="dot"></span> Productos más vendidos (top 10)</h3>
        <div class="panel-scroll">
        <table class="table">
            <thead><tr><th>#</th><th>Producto</th><th>Unidades</th><th>Total</th></tr></thead>
            <tbody>
            @forelse($topProductos as $i => $p)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $p->descripcion }}</td>
                    <td>{{ number_format($p->unidades) }}</td>
                    <td><strong>S/ {{ number_format($p->total, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;color:#9aa3ab;padding:20px">Sin ventas en el periodo.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
const porDia = @json($porDia);
const porMetodo = @json($porMetodo);
const money = v => 'S/ ' + Number(v).toLocaleString('es-PE');
const base = {responsive:true, maintainAspectRatio:false};
const paleta = ['#d18a1c','#4b5866','#a87552','#7b8794','#e2a13a','#5c6874','#8a6b4f','#333e49'];

new Chart(document.getElementById('chartDia'), {
    type:'line',
    data:{labels:porDia.map(d=>d.label),datasets:[{data:porDia.map(d=>d.total),
        borderColor:'#d18a1c',backgroundColor:'rgba(209,138,28,.14)',fill:true,tension:.35,borderWidth:2}]},
    options:{...base,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:money}}}}
});
new Chart(document.getElementById('chartMetodo'), {
    type:'doughnut',
    data:{labels:porMetodo.map(m=>m.label),datasets:[{data:porMetodo.map(m=>m.total),backgroundColor:paleta,borderColor:'#fff',borderWidth:2}]},
    options:{...base,cutout:'58%',plugins:{legend:{position:'right'},tooltip:{callbacks:{label:c=>' '+c.label+': '+money(c.parsed)}}}}
});
</script>
@endpush
