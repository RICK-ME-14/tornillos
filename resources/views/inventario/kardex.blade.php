@extends('layouts.app')

@section('title', 'Kardex')

@section('content')
    <div class="page-head">
        <h1>KARDEX / MOVIMIENTOS</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Inventario <span class="sep">/</span> Kardex
        </div>
    </div>

    <div class="toolbar">
        <form method="GET" action="{{ route('inventario.kardex') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div class="search-box no-ico">
                <select name="producto">
                    <option value="">Todos los productos</option>
                    @foreach($productos as $p)
                        <option value="{{ $p->id }}" {{ (string)$productoId === (string)$p->id ? 'selected' : '' }}>
                            {{ $p->codigo }} · {{ $p->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="search-box no-ico">
                <select name="tipo">
                    <option value="">Todos los tipos</option>
                    <option value="ENTRADA" {{ $tipo === 'ENTRADA' ? 'selected' : '' }}>Entradas</option>
                    <option value="SALIDA" {{ $tipo === 'SALIDA' ? 'selected' : '' }}>Salidas</option>
                    <option value="AJUSTE" {{ $tipo === 'AJUSTE' ? 'selected' : '' }}>Ajustes</option>
                </select>
            </div>
            <div class="search-box no-ico"><input type="date" name="desde" value="{{ $desde }}" title="Desde"></div>
            <div class="search-box no-ico"><input type="date" name="hasta" value="{{ $hasta }}" title="Hasta"></div>
            <button class="btn btn-light btn-sm"><i class="fa-solid fa-filter"></i> Filtrar</button>
            <a href="{{ route('inventario.kardex') }}" class="btn btn-light btn-sm">Limpiar</a>
        </form>
    </div>

    <div class="panel">
        <div class="panel-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th><th>Producto</th><th>Tipo</th><th>Motivo</th>
                    <th>Cantidad</th><th>Stock antes</th><th>Stock después</th><th>Usuario</th>
                </tr>
            </thead>
            <tbody>
            @forelse($movimientos as $m)
                @php
                    $clase = $m->tipo === 'ENTRADA' ? 'ok' : ($m->tipo === 'SALIDA' ? 'out' : 'low');
                    $signo = $m->tipo === 'ENTRADA' ? '+' : ($m->tipo === 'SALIDA' ? '−' : '±');
                @endphp
                <tr>
                    <td>{{ $m->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $m->producto->nombre ?? 'Producto eliminado' }}</td>
                    <td><span class="badge-stock {{ $clase }}">{{ $m->tipo }}</span></td>
                    <td><span class="badge-soft">{{ $m->motivo }}</span></td>
                    <td><strong>{{ $signo }}{{ $m->cantidad }}</strong></td>
                    <td>{{ $m->stock_anterior }}</td>
                    <td><strong>{{ $m->stock_nuevo }}</strong></td>
                    <td>{{ $m->usuario->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;color:#9aa3ab;padding:24px">No hay movimientos registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        {{ $movimientos->links() }}
    </div>
@endsection
