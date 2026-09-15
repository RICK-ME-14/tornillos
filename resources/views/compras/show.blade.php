@extends('layouts.app')

@section('title', 'Compra '.$compra->numero)

@section('content')
    <div class="page-head">
        <h1>COMPRA {{ $compra->numero }}</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('compras.index') }}">Compras</a>
            <span class="sep">/</span> {{ $compra->numero }}
        </div>
    </div>

    <div class="toolbar">
        <a href="{{ route('compras.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Volver</a>
        <div class="spacer"></div>
        @if($compra->estado === 'RECIBIDA')
            <form method="POST" action="{{ route('compras.anular', $compra) }}"
                  onsubmit="return confirm('¿Anular esta compra? Se ajustará el stock.')">
                @csrf
                <button class="btn btn-danger btn-sm"><i class="fa-solid fa-ban"></i> Anular</button>
            </form>
        @endif
    </div>

    <div class="grid-2">
        <div class="panel">
            <h3><span class="dot"></span> Datos de la compra</h3>
            <table class="table">
                <tr><td><strong>Proveedor</strong></td><td>{{ $compra->proveedor->nombre ?? '—' }}</td></tr>
                <tr><td><strong>RUC</strong></td><td>{{ $compra->proveedor->ruc ?? '—' }}</td></tr>
                <tr><td><strong>Fecha</strong></td><td>{{ $compra->fecha->format('d/m/Y') }}</td></tr>
                <tr><td><strong>Registrado por</strong></td><td>{{ $compra->usuario->name ?? '—' }}</td></tr>
                <tr><td><strong>Estado</strong></td><td><span class="pill {{ $compra->estado === 'RECIBIDA' ? 'ok' : 'warn' }}">{{ $compra->estado }}</span></td></tr>
                <tr><td><strong>Observación</strong></td><td>{{ $compra->observacion ?: '—' }}</td></tr>
            </table>
        </div>
        <div class="panel">
            <h3><span class="dot"></span> Totales</h3>
            <div class="totales">
                <div class="r"><span>Subtotal</span><span>S/ {{ number_format($compra->subtotal, 2) }}</span></div>
                <div class="r"><span>IGV (18%)</span><span>S/ {{ number_format($compra->impuesto, 2) }}</span></div>
                <div class="r total"><span>Total</span><span>S/ {{ number_format($compra->total, 2) }}</span></div>
            </div>
        </div>
    </div>

    <div class="panel">
        <h3><span class="dot"></span> Productos</h3>
        <div class="panel-scroll">
        <table class="table">
            <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th></tr></thead>
            <tbody>
            @foreach($compra->detalles as $d)
                <tr>
                    <td>{{ $d->producto->nombre ?? 'Producto eliminado' }}</td>
                    <td>{{ $d->cantidad }}</td>
                    <td>S/ {{ number_format($d->precio, 2) }}</td>
                    <td><strong>S/ {{ number_format($d->subtotal, 2) }}</strong></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>
@endsection
