@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="page-head">
        <h1>DASHBOARD</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Panel principal
            <span class="sep">/</span> {{ now()->locale('es')->isoFormat('DD MMMM YYYY') }}
        </div>
    </div>

    {{-- ===== Tarjetas ===== --}}
    <div class="cards">
        <div class="stat blue">
            <i class="fa-solid fa-eye icon"></i>
            <div>
                <div class="num">S/ {{ number_format($ventasHoy, 2) }}</div>
                <div class="label">Ventas de hoy</div>
            </div>
            <i class="fa-solid fa-eye bg-ico"></i>
        </div>
        <div class="stat orange">
            <i class="fa-solid fa-cart-shopping icon"></i>
            <div>
                <div class="num">S/ {{ number_format($ventasMes, 2) }}</div>
                <div class="label">Ventas del mes</div>
            </div>
            <i class="fa-solid fa-cart-shopping bg-ico"></i>
        </div>
        <div class="stat purple">
            <i class="fa-solid fa-box icon"></i>
            <div>
                <div class="num">{{ number_format($totalProductos) }}</div>
                <div class="label">Productos activos</div>
            </div>
            <i class="fa-solid fa-box bg-ico"></i>
        </div>
        <div class="stat lime">
            <i class="fa-solid fa-users icon"></i>
            <div>
                <div class="num">{{ number_format($totalClientes) }}</div>
                <div class="label">Clientes</div>
            </div>
            <i class="fa-solid fa-users bg-ico"></i>
        </div>
    </div>

    @if($productosStockBajo > 0)
        <div class="panel" style="border-left:4px solid var(--brand-claro);margin-bottom:18px">
            <span style="color:#d98613;font-weight:600">
                <i class="fa-solid fa-triangle-exclamation"></i>
                {{ $productosStockBajo }} producto(s) con stock bajo o agotado.
            </span>
            <a href="{{ route('reportes.inventario') }}" style="margin-left:8px;color:var(--brand)">Ver detalle</a>
        </div>
    @endif

    {{-- ===== Gráficos (fila 1) ===== --}}
    <div class="grid-2">
        <div class="panel">
            <h3><span class="dot"></span> Ventas últimos 14 días</h3>
            <div class="chart-box"><canvas id="chartDias"></canvas></div>
        </div>
        <div class="panel">
            <h3><span class="dot"></span> Ventas por mes ({{ now()->year }})</h3>
            <div class="chart-box"><canvas id="chartMeses"></canvas></div>
        </div>
    </div>

    {{-- ===== Gráficos (fila 2 - nuevos) ===== --}}
    <div class="grid-2">
        <div class="panel">
            <h3><span class="dot"></span> Ventas por método de pago</h3>
            <div class="chart-box"><canvas id="chartMetodos"></canvas></div>
        </div>
        <div class="panel">
            <h3><span class="dot"></span> Ventas por categoría</h3>
            <div class="chart-box"><canvas id="chartCategorias"></canvas></div>
        </div>
    </div>

    {{-- ===== Tablas ===== --}}
    <div class="grid-2">
        <div class="panel">
            <h3><span class="dot"></span> Productos más vendidos</h3>
            <div class="panel-scroll">
            <table class="table">
                <thead><tr><th>Producto</th><th>Unidades</th><th>Total</th></tr></thead>
                <tbody>
                @forelse($topProductos as $p)
                    <tr>
                        <td>{{ $p->descripcion }}</td>
                        <td>{{ number_format($p->unidades) }}</td>
                        <td>S/ {{ number_format($p->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="text-align:center;color:#9aa3ab">Sin datos aún</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </div>
        <div class="panel">
            <h3><span class="dot"></span> Últimas ventas</h3>
            <div class="panel-scroll">
            <table class="table">
                <thead><tr><th>N°</th><th>Cliente</th><th>Total</th><th>Estado</th></tr></thead>
                <tbody>
                @forelse($ultimasVentas as $v)
                    <tr>
                        <td>{{ $v->numero }}</td>
                        <td>{{ $v->cliente->nombre ?? 'Cliente varios' }}</td>
                        <td>S/ {{ number_format($v->total, 2) }}</td>
                        <td>
                            <span class="pill {{ $v->estado === 'COMPLETADA' ? 'ok' : 'warn' }}">
                                {{ $v->estado }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;color:#9aa3ab">Sin ventas registradas</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const dias       = @json($dias);
    const meses      = @json($meses);
    const metodos    = @json($metodosPago);
    const categorias = @json($ventasCategoria);

    const money = v => 'S/ ' + Number(v).toLocaleString('es-PE');
    const base  = { responsive: true, maintainAspectRatio: false };

    // 1) Línea - ventas últimos 14 días
    new Chart(document.getElementById('chartDias'), {
        type: 'line',
        data: {
            labels: dias.map(d => d.label),
            datasets: [{
                label: 'Ventas (S/)',
                data: dias.map(d => d.total),
                borderColor: '#d18a1c',
                backgroundColor: 'rgba(209,138,28,.14)',
                fill: true, tension: .35, pointRadius: 3,
                pointBackgroundColor: '#fff', pointBorderColor: '#d18a1c', borderWidth: 2,
            }]
        },
        options: { ...base,
            plugins:{legend:{display:false}},
            scales:{y:{beginAtZero:true,ticks:{callback:money}}}
        }
    });

    // 2) Área - ventas por mes
    new Chart(document.getElementById('chartMeses'), {
        type: 'line',
        data: {
            labels: meses.map(m => m.label),
            datasets: [{
                label: 'Ventas (S/)',
                data: meses.map(m => m.total),
                borderColor: '#4b5866',
                backgroundColor: 'rgba(75,88,102,.13)',
                fill: true, tension: .35, pointRadius: 2, borderWidth: 2,
            }]
        },
        options: { ...base,
            plugins:{legend:{display:false}},
            scales:{y:{beginAtZero:true,ticks:{callback:money}}}
        }
    });

    // 3) Doughnut - ventas por método de pago
    const paleta = ['#d18a1c','#4b5866','#a87552','#7b8794','#e2a13a','#5c6874','#8a6b4f','#333e49'];
    new Chart(document.getElementById('chartMetodos'), {
        type: 'doughnut',
        data: {
            labels: metodos.map(m => m.label),
            datasets: [{
                data: metodos.map(m => m.total),
                backgroundColor: paleta,
                borderColor: '#fff', borderWidth: 2,
            }]
        },
        options: { ...base,
            cutout: '58%',
            plugins:{
                legend:{position:'right', labels:{boxWidth:14, padding:12}},
                tooltip:{callbacks:{label:c=>` ${c.label}: ${money(c.parsed)}`}}
            }
        }
    });

    // 4) Barras - ventas por categoría
    new Chart(document.getElementById('chartCategorias'), {
        type: 'bar',
        data: {
            labels: categorias.map(c => c.label),
            datasets: [{
                label: 'Ventas (S/)',
                data: categorias.map(c => c.total),
                backgroundColor: '#d18a1c',
                borderRadius: 6, maxBarThickness: 46,
            }]
        },
        options: { ...base,
            indexAxis: 'y',
            plugins:{legend:{display:false},
                tooltip:{callbacks:{label:c=>' '+money(c.parsed.x)}}},
            scales:{x:{beginAtZero:true,ticks:{callback:money}}}
        }
    });
</script>
@endpush
