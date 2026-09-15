@extends('layouts.app')

@section('title', 'Ventas')

@section('content')
    <div class="page-head">
        <h1>VENTAS</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Ventas <span class="sep">/</span> Historial
        </div>
    </div>

    <div class="toolbar">
        <form method="GET" action="{{ route('ventas.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" value="{{ $q }}" placeholder="N° de venta...">
            </div>
            <div class="search-box no-ico">
                <input type="date" name="desde" value="{{ $desde }}" title="Desde">
            </div>
            <div class="search-box no-ico">
                <input type="date" name="hasta" value="{{ $hasta }}" title="Hasta">
            </div>
            <div class="search-box no-ico">
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="COMPLETADA" {{ $estado === 'COMPLETADA' ? 'selected' : '' }}>Completada</option>
                    <option value="ANULADA" {{ $estado === 'ANULADA' ? 'selected' : '' }}>Anulada</option>
                </select>
            </div>
            <button class="btn btn-light btn-sm"><i class="fa-solid fa-filter"></i> Filtrar</button>
        </form>
        <div class="spacer"></div>
        <a href="{{ route('ventas.pos') }}" class="btn btn-success btn-sm">
            <i class="fa-solid fa-cash-register"></i> Nueva venta
        </a>
    </div>

    <div class="panel">
        <div class="panel-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>N°</th><th>Fecha</th><th>Cliente</th><th>Comprobante</th>
                    <th>Pago</th><th>Total</th><th>Estado</th><th style="width:120px">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($ventas as $v)
                <tr>
                    <td><strong>{{ $v->numero }}</strong></td>
                    <td>{{ $v->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $v->cliente->nombre ?? 'Cliente varios' }}</td>
                    <td><span class="badge-soft">{{ $v->tipo_comprobante }}</span></td>
                    <td>{{ $v->metodo_pago }}</td>
                    <td><strong>S/ {{ number_format($v->total, 2) }}</strong></td>
                    <td>
                        <span class="pill {{ $v->estado === 'COMPLETADA' ? 'ok' : 'warn' }}">{{ $v->estado }}</span>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('ventas.show', $v) }}" class="btn-icon edit" title="Ver / Ticket">
                                <i class="fa-solid fa-receipt"></i>
                            </a>
                            @if($v->estado === 'COMPLETADA')
                                <form method="POST" action="{{ route('ventas.anular', $v) }}"
                                      onsubmit="return confirm('¿Anular la venta {{ $v->numero }}? Se repondrá el stock.')">
                                    @csrf
                                    <button class="btn-icon del" title="Anular"><i class="fa-solid fa-ban"></i></button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;color:#9aa3ab;padding:24px">No hay ventas en este periodo.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        {{ $ventas->links() }}
    </div>
@endsection
