@extends('layouts.app')

@section('title', 'Compras')

@section('content')
    <div class="page-head">
        <h1>COMPRAS</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Compras <span class="sep">/</span> Historial
        </div>
    </div>

    <div class="toolbar">
        <form method="GET" action="{{ route('compras.index') }}" style="display:flex;gap:10px;flex-wrap:wrap">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" value="{{ $q }}" placeholder="N° de compra...">
            </div>
            <div class="search-box no-ico">
                <select name="estado" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <option value="RECIBIDA" {{ $estado === 'RECIBIDA' ? 'selected' : '' }}>Recibida</option>
                    <option value="ANULADA" {{ $estado === 'ANULADA' ? 'selected' : '' }}>Anulada</option>
                </select>
            </div>
        </form>
        <div class="spacer"></div>
        <a href="{{ route('compras.create') }}" class="btn btn-success btn-sm">
            <i class="fa-solid fa-plus"></i> Nueva compra
        </a>
    </div>

    <div class="panel">
        <div class="panel-scroll">
        <table class="table">
            <thead>
                <tr><th>N°</th><th>Fecha</th><th>Proveedor</th><th>Total</th><th>Estado</th><th style="width:120px">Acciones</th></tr>
            </thead>
            <tbody>
            @forelse($compras as $c)
                <tr>
                    <td><strong>{{ $c->numero }}</strong></td>
                    <td>{{ $c->fecha->format('d/m/Y') }}</td>
                    <td>{{ $c->proveedor->nombre ?? '—' }}</td>
                    <td><strong>S/ {{ number_format($c->total, 2) }}</strong></td>
                    <td><span class="pill {{ $c->estado === 'RECIBIDA' ? 'ok' : 'warn' }}">{{ $c->estado }}</span></td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('compras.show', $c) }}" class="btn-icon edit" title="Ver"><i class="fa-solid fa-eye"></i></a>
                            @if($c->estado === 'RECIBIDA')
                                <form method="POST" action="{{ route('compras.anular', $c) }}"
                                      onsubmit="return confirm('¿Anular la compra {{ $c->numero }}? Se ajustará el stock.')">
                                    @csrf
                                    <button class="btn-icon del" title="Anular"><i class="fa-solid fa-ban"></i></button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#9aa3ab;padding:24px">No hay compras registradas.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        {{ $compras->links() }}
    </div>
@endsection
