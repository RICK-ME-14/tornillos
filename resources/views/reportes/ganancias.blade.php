@extends('layouts.app')

@section('title', 'Reporte de Ganancias')

@section('content')
    <div class="page-head no-print">
        <h1>REPORTE DE GANANCIAS</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Reportes <span class="sep">/</span> Ganancias
        </div>
    </div>

    <div class="toolbar no-print">
        <form method="GET" action="{{ route('reportes.ganancias') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div class="search-box no-ico"><label style="font-size:11px;color:#8a929b">Desde</label><br><input type="date" name="desde" value="{{ $desde }}"></div>
            <div class="search-box no-ico"><label style="font-size:11px;color:#8a929b">Hasta</label><br><input type="date" name="hasta" value="{{ $hasta }}"></div>
            <button class="btn btn-primary btn-sm" style="width:auto"><i class="fa-solid fa-filter"></i> Aplicar</button>
        </form>
        <div class="spacer"></div>
        <a href="{{ route('reportes.ganancias.export', ['desde' => $desde, 'hasta' => $hasta]) }}" class="btn btn-success btn-sm"><i class="fa-solid fa-file-excel"></i> Exportar Excel</a>
        <button onclick="window.print()" class="btn btn-light btn-sm"><i class="fa-solid fa-print"></i> Imprimir</button>
    </div>

    <div class="cards">
        <div class="stat blue">
            <i class="fa-solid fa-arrow-trend-up icon"></i>
            <div><div class="num">S/ {{ number_format($ingresos, 2) }}</div><div class="label">Ingresos (sin IGV)</div></div>
            <i class="fa-solid fa-arrow-trend-up bg-ico"></i>
        </div>
        <div class="stat orange">
            <i class="fa-solid fa-money-bill-wave icon"></i>
            <div><div class="num">S/ {{ number_format($costo, 2) }}</div><div class="label">Costo de ventas</div></div>
            <i class="fa-solid fa-money-bill-wave bg-ico"></i>
        </div>
        <div class="stat purple">
            <i class="fa-solid fa-coins icon"></i>
            <div><div class="num">S/ {{ number_format($utilidad, 2) }}</div><div class="label">Utilidad bruta</div></div>
            <i class="fa-solid fa-coins bg-ico"></i>
        </div>
        <div class="stat lime">
            <i class="fa-solid fa-chart-pie icon"></i>
            <div><div class="num">{{ number_format($margen, 1) }}%</div><div class="label">Margen</div></div>
            <i class="fa-solid fa-chart-pie bg-ico"></i>
        </div>
    </div>

    <div class="panel">
        <h3><span class="dot"></span> Utilidad por día</h3>
        <div class="chart-box"><canvas id="chartUtil"></canvas></div>
    </div>

    <div class="panel">
        <h3><span class="dot"></span> Utilidad por producto</h3>
        <div class="panel-scroll">
        <table class="table">
            <thead><tr><th>Producto</th><th>Unidades</th><th>Ingresos</th><th>Costo</th><th>Utilidad</th><th>Margen</th></tr></thead>
            <tbody>
            @forelse($detalle as $d)
                @php $m = $d->ingresos > 0 ? ($d->utilidad / $d->ingresos) * 100 : 0; @endphp
                <tr>
                    <td>{{ $d->descripcion }}</td>
                    <td>{{ number_format($d->unidades) }}</td>
                    <td>S/ {{ number_format($d->ingresos, 2) }}</td>
                    <td>S/ {{ number_format($d->costo, 2) }}</td>
                    <td><strong style="color:{{ $d->utilidad >= 0 ? '#1e9e5a' : '#e74c3c' }}">S/ {{ number_format($d->utilidad, 2) }}</strong></td>
                    <td><span class="pill {{ $m >= 0 ? 'ok' : 'warn' }}">{{ number_format($m, 1) }}%</span></td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#9aa3ab;padding:20px">Sin ventas en el periodo.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
const porDia = @json($porDia);
const money = v => 'S/ ' + Number(v).toLocaleString('es-PE');
const base = {responsive:true, maintainAspectRatio:false};

new Chart(document.getElementById('chartUtil'), {
    type:'bar',
    data:{labels:porDia.map(d=>d.label),datasets:[{label:'Utilidad',data:porDia.map(d=>d.total),
        backgroundColor:'#d18a1c',borderRadius:5,maxBarThickness:34}]},
    options:{...base,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>' '+money(c.parsed.y)}}},
        scales:{y:{beginAtZero:true,ticks:{callback:money}}}}
});
</script>
@endpush
