@extends('layouts.app')

@section('title', 'Cotizaciones')

@section('content')
    <div class="page-head">
        <h1>COTIZACIONES</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Ventas <span class="sep">/</span> Cotizaciones
        </div>
    </div>

    <div class="cards">
        <div class="stat warn">
            <i class="fa-solid fa-clock icon"></i>
            <div>
                <div class="num">{{ number_format($resumen['pendientes']) }}</div>
                <div class="label">Vigentes</div>
            </div>
        </div>
        <div class="stat blue">
            <i class="fa-solid fa-sack-dollar icon"></i>
            <div>
                <div class="num">{{ $empresa->moneda ?? 'S/' }} {{ number_format($resumen['monto_pendiente'], 2) }}</div>
                <div class="label">Por cerrar</div>
            </div>
        </div>
        <div class="stat ok">
            <i class="fa-solid fa-circle-check icon"></i>
            <div>
                <div class="num">{{ number_format($resumen['aceptadas']) }}</div>
                <div class="label">Aceptadas</div>
            </div>
        </div>
        <div class="stat bad">
            <i class="fa-solid fa-hourglass-end icon"></i>
            <div>
                <div class="num">{{ number_format($resumen['vencidas']) }}</div>
                <div class="label">Vencidas</div>
            </div>
        </div>
    </div>

    <form method="GET" class="toolbar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="{{ $q }}" placeholder="Número o cliente...">
        </div>
        <select name="estado" class="form-control" style="width:auto">
            <option value="">Todo estado</option>
            @foreach(['PENDIENTE'=>'Pendiente','VENCIDA'=>'Vencida','ACEPTADA'=>'Aceptada','RECHAZADA'=>'Rechazada','ANULADA'=>'Anulada'] as $v=>$t)
                <option value="{{ $v }}" {{ $estado === $v ? 'selected' : '' }}>{{ $t }}</option>
            @endforeach
        </select>
        <input type="date" name="desde" value="{{ $desde }}" class="form-control" style="width:auto">
        <input type="date" name="hasta" value="{{ $hasta }}" class="form-control" style="width:auto">
        <button class="btn btn-light" style="width:auto"><i class="fa-solid fa-filter"></i> Filtrar</button>
        <div class="spacer"></div>
        <a href="{{ route('cotizaciones.export') }}" class="btn btn-light" style="width:auto">
            <i class="fa-solid fa-file-csv"></i> Exportar</a>
        <a href="{{ route('cotizaciones.punto') }}" class="btn btn-success" style="width:auto">
            <i class="fa-solid fa-plus"></i> Nueva cotización</a>
    </form>

    @include('layouts.flash')

    <div class="panel">
        <div class="panel-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Número</th><th>Fecha</th><th>Válida hasta</th><th>Cliente</th>
                    <th style="text-align:right">Total</th><th>Estado</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($cotizaciones as $c)
                @php($ev = $c->estadoVisible())
                <tr>
                    <td><strong>{{ $c->numero }}</strong></td>
                    <td>{{ $c->fecha?->format('d/m/Y') }}</td>
                    <td>
                        {{ $c->valida_hasta?->format('d/m/Y') }}
                        @if($ev === 'PENDIENTE')
                            <div style="font-size:11px;color:#9aa3ab">quedan {{ $c->diasDeVigencia() }} día(s)</div>
                        @endif
                    </td>
                    <td>{{ $c->cliente->nombre ?? 'Sin cliente' }}</td>
                    <td style="text-align:right"><strong>{{ $empresa->moneda ?? 'S/' }} {{ number_format($c->total, 2) }}</strong></td>
                    <td>
                        <span class="pill {{ ['PENDIENTE'=>'info','ACEPTADA'=>'ok','VENCIDA'=>'warn','RECHAZADA'=>'err','ANULADA'=>'mute'][$ev] ?? 'mute' }}">
                            {{ $c->estadoLabel() }}
                        </span>
                    </td>
                    <td style="text-align:right">
                        <a href="{{ route('cotizaciones.show', $c) }}" class="btn-icon edit" title="Ver">
                            <i class="fa-solid fa-eye"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;color:#9aa3ab;padding:24px">No hay cotizaciones con estos filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{ $cotizaciones->links() }}
@endsection
