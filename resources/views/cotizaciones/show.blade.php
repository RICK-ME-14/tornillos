@extends('layouts.app')

@section('title', 'Cotización ' . $cotizacion->numero)

@section('content')
    @php($ev = $cotizacion->estadoVisible())
    @php($moneda = $empresa->moneda ?? 'S/')

    <div class="page-head">
        <h1>COTIZACIÓN {{ $cotizacion->numero }}</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('cotizaciones.index') }}">Cotizaciones</a>
            <span class="sep">/</span> {{ $cotizacion->numero }}
        </div>
    </div>

    @include('layouts.flash')

    <div class="grid-2">
        {{-- ===== Datos y líneas ===== --}}
        <div class="panel" style="grid-column:1/-1">
            <div class="cot-cab">
                <div>
                    <div class="cot-dato"><span>Cliente</span><strong>{{ $cotizacion->cliente->nombre ?? 'Sin cliente asignado' }}</strong></div>
                    @if($cotizacion->cliente?->numero_documento)
                        <div class="cot-dato"><span>{{ $cotizacion->cliente->tipo_documento }}</span><strong>{{ $cotizacion->cliente->numero_documento }}</strong></div>
                    @endif
                    <div class="cot-dato"><span>Emitida</span><strong>{{ $cotizacion->fecha?->format('d/m/Y') }}</strong></div>
                    <div class="cot-dato">
                        <span>Válida hasta</span>
                        <strong>{{ $cotizacion->valida_hasta?->format('d/m/Y') }}</strong>
                        @if($ev === 'PENDIENTE')
                            <em>quedan {{ $cotizacion->diasDeVigencia() }} día(s)</em>
                        @endif
                    </div>
                    <div class="cot-dato"><span>Elaborada por</span><strong>{{ $cotizacion->usuario->name ?? '—' }}</strong></div>
                </div>
                <div class="cot-estado">
                    <span class="pill {{ ['PENDIENTE'=>'info','ACEPTADA'=>'ok','VENCIDA'=>'warn','RECHAZADA'=>'err','ANULADA'=>'mute'][$ev] ?? 'mute' }}">
                        {{ $cotizacion->estadoLabel() }}
                    </span>
                    @if($cotizacion->venta)
                        <a href="{{ route('ventas.show', $cotizacion->venta) }}" class="cot-venta">
                            <i class="fa-solid fa-arrow-right-long"></i> Venta {{ $cotizacion->venta->numero }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="panel-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Producto</th><th>Unidad</th>
                        <th style="text-align:right">Cantidad</th>
                        <th style="text-align:right">Precio</th>
                        <th style="text-align:right">Importe</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($cotizacion->detalles as $d)
                    <tr>
                        <td>
                            {{ $d->descripcion }}
                            @if($d->tipo_afectacion_igv !== '10')
                                <span class="badge-soft">{{ $d->tipo_afectacion_igv === '20' ? 'Exonerado' : 'Inafecto' }}</span>
                            @endif
                        </td>
                        <td>{{ $d->unidad_sunat }}</td>
                        <td style="text-align:right">{{ $d->cantidad }}</td>
                        <td style="text-align:right">{{ $moneda }} {{ number_format($d->precio, 2) }}</td>
                        <td style="text-align:right"><strong>{{ $moneda }} {{ number_format($d->subtotal, 2) }}</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>

            <div class="cot-totales">
                <div class="r"><span>Subtotal</span><span>{{ $moneda }} {{ number_format($cotizacion->subtotal, 2) }}</span></div>
                @if($cotizacion->descuento > 0)
                    <div class="r"><span>Descuento</span><span>− {{ $moneda }} {{ number_format($cotizacion->descuento, 2) }}</span></div>
                @endif
                <div class="r"><span>IGV</span><span>{{ $moneda }} {{ number_format($cotizacion->impuesto, 2) }}</span></div>
                <div class="r total"><span>Total</span><span>{{ $moneda }} {{ number_format($cotizacion->total, 2) }}</span></div>
            </div>

            @if($cotizacion->observacion)
                <div class="fe-note" style="margin-top:16px">
                    <i class="fa-solid fa-note-sticky"></i> {{ $cotizacion->observacion }}
                </div>
            @endif
        </div>
    </div>

    {{-- ===== Acciones ===== --}}
    <div class="panel">
        <div class="cot-acciones">
            <a href="{{ route('cotizaciones.imprimir', $cotizacion) }}" target="_blank" class="btn btn-light" style="width:auto">
                <i class="fa-solid fa-file-pdf"></i> A4 / PDF
            </a>
            <a href="{{ route('cotizaciones.ticket', $cotizacion) }}" target="_blank" class="btn btn-light" style="width:auto">
                <i class="fa-solid fa-receipt"></i> Ticket 80 mm
            </a>

            @if($cotizacion->convertible())
                <form method="POST" action="{{ route('cotizaciones.convertir', $cotizacion) }}" class="cot-convertir">
                    @csrf
                    <select name="tipo_comprobante" class="form-control" style="width:auto" required>
                        <option value="TICKET">Ticket</option>
                        <option value="BOLETA" selected>Boleta</option>
                        <option value="FACTURA">Factura</option>
                    </select>
                    <select name="metodo_pago" class="form-control" style="width:auto" required>
                        <option value="EFECTIVO">Efectivo</option>
                        <option value="TARJETA">Tarjeta</option>
                        <option value="YAPE">Yape</option>
                        <option value="TRANSFERENCIA">Transferencia</option>
                    </select>
                    <button class="btn btn-success" style="width:auto"
                            onclick="return confirm('Se registrará la venta y se descontará el stock. ¿Continuar?')">
                        <i class="fa-solid fa-cash-register"></i> Convertir en venta
                    </button>
                </form>

                <form method="POST" action="{{ route('cotizaciones.estado', $cotizacion) }}">
                    @csrf
                    <input type="hidden" name="estado" value="RECHAZADA">
                    <button class="btn btn-light" style="width:auto"
                            onclick="return confirm('¿Marcar esta cotización como rechazada por el cliente?')">
                        <i class="fa-solid fa-thumbs-down"></i> Rechazada
                    </button>
                </form>
            @elseif($cotizacion->venta_id)
                <span class="cot-cerrada">
                    <i class="fa-solid fa-circle-check"></i>
                    Convertida en la venta {{ $cotizacion->venta->numero ?? '' }}.
                </span>
            @elseif($cotizacion->vencida())
                <span class="cot-cerrada">
                    <i class="fa-solid fa-hourglass-end"></i>
                    Venció el {{ $cotizacion->valida_hasta->format('d/m/Y') }}. Genera una nueva con precios actuales.
                </span>
            @else
                <span class="cot-cerrada">
                    <i class="fa-solid fa-ban"></i> Cotización {{ strtolower($cotizacion->estadoLabel()) }}.
                </span>
            @endif

            <div class="spacer"></div>
            <a href="{{ route('cotizaciones.index') }}" class="btn btn-light" style="width:auto">Volver</a>
        </div>
    </div>
@endsection
