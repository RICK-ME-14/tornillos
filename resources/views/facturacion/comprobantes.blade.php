@extends('layouts.app')

@section('title', 'Comprobantes Electrónicos')

@php
    $totAceptado = $resumen['ACEPTADO'] ?? 0;
    $totEnviado  = $resumen['ENVIADO'] ?? 0;
    $totPend     = $resumen['PENDIENTE'] ?? 0;
    $totProblema = ($resumen['RECHAZADO'] ?? 0) + ($resumen['ERROR'] ?? 0);

    if (! function_exists('feBadge')) {
        function feBadge($estado) {
            return match ($estado) {
                'ACEPTADO', 'ENVIADO' => 'ok',
                'PENDIENTE' => 'warn',
                'RECHAZADO', 'ERROR' => 'err',
                default => 'warn',
            };
        }
    }
@endphp

@section('content')
    <div class="page-head">
        <h1>COMPROBANTES ELECTRÓNICOS</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Ventas <span class="sep">/</span> Comprobantes
        </div>
    </div>

    <div class="cards">
        <div class="stat ok">
            <i class="fa-solid fa-circle-check icon"></i>
            <div>
                <div class="num">{{ number_format($totAceptado) }}</div>
                <div class="label">Aceptados</div>
            </div>
        </div>
        <div class="stat info">
            <i class="fa-solid fa-paper-plane icon"></i>
            <div>
                <div class="num">{{ number_format($totEnviado) }}</div>
                <div class="label">Enviados</div>
            </div>
        </div>
        <div class="stat warn">
            <i class="fa-solid fa-clock icon"></i>
            <div>
                <div class="num">{{ number_format($totPend) }}</div>
                <div class="label">Pendientes</div>
            </div>
        </div>
        <div class="stat bad">
            <i class="fa-solid fa-triangle-exclamation icon"></i>
            <div>
                <div class="num">{{ number_format($totProblema) }}</div>
                <div class="label">Rechazados / Error</div>
            </div>
        </div>
    </div>

    <div class="toolbar">
        <form method="GET" action="{{ route('comprobantes.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="Serie, número o cliente...">
            </div>
            <div class="search-box no-ico">
                <select name="tipo">
                    <option value="">Todo tipo</option>
                    <option value="BOLETA" {{ $filtros['tipo'] === 'BOLETA' ? 'selected' : '' }}>Boleta</option>
                    <option value="FACTURA" {{ $filtros['tipo'] === 'FACTURA' ? 'selected' : '' }}>Factura</option>
                </select>
            </div>
            <div class="search-box no-ico">
                <select name="estado">
                    <option value="">Todo estado</option>
                    @foreach(['ACEPTADO','ENVIADO','PENDIENTE','RECHAZADO','ERROR'] as $e)
                        <option value="{{ $e }}" {{ $filtros['estado'] === $e ? 'selected' : '' }}>{{ $e }}</option>
                    @endforeach
                </select>
            </div>
            <div class="search-box no-ico">
                <input type="date" name="desde" value="{{ $filtros['desde'] }}" title="Desde">
            </div>
            <div class="search-box no-ico">
                <input type="date" name="hasta" value="{{ $filtros['hasta'] }}" title="Hasta">
            </div>
            <button class="btn btn-light btn-sm"><i class="fa-solid fa-filter"></i> Filtrar</button>
        </form>
        <div class="spacer"></div>
        <form method="POST" action="{{ route('facturacion.resumen') }}"
              onsubmit="return confirm('¿Generar y enviar a SUNAT el resumen diario de las boletas de ayer?')">
            @csrf
            <button class="btn btn-light btn-sm" style="width:auto"><i class="fa-solid fa-layer-group"></i> Resumen de boletas</button>
        </form>
        @if($totPend > 0 || $totProblema > 0)
        <form method="POST" action="{{ route('facturacion.reenviar.pendientes') }}"
              onsubmit="return confirm('¿Reenviar a SUNAT todos los comprobantes pendientes o con error?')">
            @csrf
            <button class="btn btn-success btn-sm" style="width:auto"><i class="fa-solid fa-rotate"></i> Reenviar pendientes</button>
        </form>
        @endif
    </div>

    <div class="panel">
        <div class="panel-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Comprobante</th><th>Tipo</th><th>Fecha</th><th>Cliente</th>
                    <th style="text-align:right">Total</th><th>Estado SUNAT</th><th style="width:180px">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($comprobantes as $c)
                <tr>
                    <td><strong>{{ $c->comprobanteElectronico() ?? '—' }}</strong>
                        <div style="font-size:11px;color:#9aa3ab">{{ $c->numero }}</div>
                    </td>
                    <td><span class="badge-soft">{{ $c->tipo_comprobante }}</span></td>
                    <td>{{ $c->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $c->cliente->nombre ?? 'Cliente varios' }}</td>
                    <td style="text-align:right"><strong>S/ {{ number_format($c->total, 2) }}</strong></td>
                    <td>
                        <span class="pill {{ feBadge($c->fe_estado) }}">{{ $c->feEstadoLabel() }}</span>
                        @if($c->estado === 'ANULADA')
                            <div style="font-size:11px;color:#c0392b;margin-top:3px">Anulada
                                @if($c->anulacionEstado()) · {{ $c->anulacionEstado() }} @endif
                            </div>
                        @endif
                    </td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('facturacion.comprobante', $c) }}" target="_blank" class="btn-icon edit" title="Comprobante A4">
                                <i class="fa-solid fa-file-invoice"></i>
                            </a>
                            <a href="{{ route('facturacion.ticket', $c) }}" target="_blank" class="btn-icon edit" title="Ticket">
                                <i class="fa-solid fa-receipt"></i>
                            </a>
                            @if($c->fe_xml_ruta)
                                <a href="{{ route('facturacion.xml', $c) }}" class="btn-icon" title="Descargar XML">
                                    <i class="fa-solid fa-code"></i>
                                </a>
                            @endif
                            @if($c->fe_cdr_ruta)
                                <a href="{{ route('facturacion.cdr', $c) }}" class="btn-icon" title="Descargar CDR">
                                    <i class="fa-solid fa-file-zipper"></i>
                                </a>
                            @endif
                            @if(in_array($c->fe_estado, ['PENDIENTE','ERROR']))
                                <form method="POST" action="{{ route('facturacion.reenviar', $c) }}"
                                      onsubmit="return confirm('¿Reenviar este comprobante a SUNAT?')">
                                    @csrf
                                    <button class="btn-icon" title="Reenviar a SUNAT" style="color:#1e9e5a"><i class="fa-solid fa-rotate"></i></button>
                                </form>
                            @endif
                            @if($c->feEmitido() && ($c->cliente->email ?? false))
                                <form method="POST" action="{{ route('facturacion.email', $c) }}"
                                      onsubmit="return confirm('¿Enviar el comprobante por correo a {{ $c->cliente->email }}?')">
                                    @csrf
                                    <button class="btn-icon" title="Enviar por correo {{ $c->fe_email_enviado_en ? '(ya enviado)' : '' }}"
                                            style="color:{{ $c->fe_email_enviado_en ? '#9aa3ab' : '#1583b8' }}"><i class="fa-solid fa-envelope"></i></button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;color:#9aa3ab;padding:24px">No hay comprobantes electrónicos con estos filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        {{ $comprobantes->links() }}
    </div>
@endsection
