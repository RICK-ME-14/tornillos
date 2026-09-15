@extends('layouts.app')

@section('title', 'Venta '.$venta->numero)

@section('content')
    <div class="page-head no-print">
        <h1>VENTA {{ $venta->numero }}</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> <a href="{{ route('ventas.index') }}">Ventas</a>
            <span class="sep">/</span> {{ $venta->numero }}
        </div>
    </div>

    <div class="toolbar no-print">
        <a href="{{ route('ventas.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Volver</a>
        <div class="spacer"></div>
        <button onclick="window.print()" class="btn btn-primary btn-sm" style="width:auto">
            <i class="fa-solid fa-print"></i> Imprimir
        </button>
        @if($venta->fe_estado && $venta->fe_estado !== 'NO_APLICA')
            <a href="{{ route('facturacion.comprobante', $venta) }}" target="_blank" class="btn btn-light btn-sm" style="width:auto">
                <i class="fa-solid fa-file-invoice"></i> Comprobante A4
            </a>
            <a href="{{ route('facturacion.ticket', $venta) }}" target="_blank" class="btn btn-light btn-sm" style="width:auto">
                <i class="fa-solid fa-receipt"></i> Ticket SUNAT
            </a>
            @if($venta->feEmitido() && ($venta->cliente->email ?? false))
                <form method="POST" action="{{ route('facturacion.email', $venta) }}"
                      onsubmit="return confirm('¿Enviar el comprobante por correo a {{ $venta->cliente->email }}?')">
                    @csrf
                    <button class="btn btn-light btn-sm" style="width:auto"><i class="fa-solid fa-envelope"></i> Enviar por correo</button>
                </form>
            @endif
        @endif
        @if($venta->estado === 'COMPLETADA')
            <form method="POST" action="{{ route('ventas.anular', $venta) }}" id="form-anular">
                @csrf
                <input type="hidden" name="motivo" id="motivo-anular" value="ANULACION DE LA OPERACION">
                <button type="button" onclick="anularVenta()" class="btn btn-danger btn-sm"><i class="fa-solid fa-ban"></i> Anular</button>
            </form>
        @endif
    </div>

    @if($venta->estado === 'COMPLETADA')
    @push('scripts')
    <script>
        function anularVenta(){
            @if($venta->feEmitido())
            var motivo = prompt('Motivo de la anulación (se enviará a SUNAT):', 'ANULACION DE LA OPERACION');
            if(motivo === null){ return; }
            document.getElementById('motivo-anular').value = motivo || 'ANULACION DE LA OPERACION';
            @else
            if(!confirm('¿Anular esta venta? Se repondrá el stock.')){ return; }
            @endif
            document.getElementById('form-anular').submit();
        }
    </script>
    @endpush
    @endif

    <div class="ticket">
        <h2>{{ $empresa->nombre ?? config('app.name') }}</h2>
        @if($empresa && ($empresa->ruc || $empresa->direccion))
            <div class="sub" style="margin-bottom:4px">
                @if($empresa->ruc)RUC: {{ $empresa->ruc }}@endif
                @if($empresa->direccion)<br>{{ $empresa->direccion }}@endif
            </div>
        @endif
        <div class="sub">
            {{ $venta->tipo_comprobante }} · {{ $venta->numero }}
            @if($venta->estado === 'ANULADA')
                <br><strong style="color:#e74c3c">** ANULADA **</strong>
            @endif
        </div>

        <div class="meta">
            <div><span>Fecha:</span><span>{{ $venta->created_at->format('d/m/Y H:i') }}</span></div>
            <div><span>Cliente:</span><span>{{ $venta->cliente->nombre ?? 'Cliente varios' }}</span></div>
            <div><span>Atendió:</span><span>{{ $venta->usuario->name ?? '—' }}</span></div>
            <div><span>Pago:</span><span>{{ $venta->metodo_pago }}</span></div>
            @if($venta->fe_estado && $venta->fe_estado !== 'NO_APLICA')
                @if($venta->comprobanteElectronico())
                    <div><span>Comprobante SUNAT:</span><span>{{ $venta->comprobanteElectronico() }}</span></div>
                @endif
                <div><span>Estado SUNAT:</span><span>{{ $venta->feEstadoLabel() }}</span></div>
            @endif
        </div>

        <table>
            <thead>
                <tr><th>Cant</th><th>Producto</th><th style="text-align:right">Importe</th></tr>
            </thead>
            <tbody>
            @foreach($venta->detalles as $d)
                <tr>
                    <td>{{ $d->cantidad }}</td>
                    <td>{{ $d->descripcion }}<br><span style="color:#888">{{ number_format($d->precio,2) }} c/u</span></td>
                    <td style="text-align:right">{{ number_format($d->subtotal,2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="tot">
            <div class="r"><span>Subtotal</span><span>S/ {{ number_format($venta->subtotal,2) }}</span></div>
            @if($venta->descuento > 0)
                <div class="r"><span>Descuento</span><span>- S/ {{ number_format($venta->descuento,2) }}</span></div>
            @endif
            <div class="r"><span>IGV (18%)</span><span>S/ {{ number_format($venta->impuesto,2) }}</span></div>
            <div class="r big"><span>TOTAL</span><span>S/ {{ number_format($venta->total,2) }}</span></div>
            @if($venta->metodo_pago === 'EFECTIVO' && $venta->efectivo_recibido !== null)
                <div class="r"><span>Efectivo recibido</span><span>S/ {{ number_format($venta->efectivo_recibido,2) }}</span></div>
                <div class="r"><span>Vuelto</span><span>S/ {{ number_format($venta->vuelto,2) }}</span></div>
            @endif
        </div>

        <div class="foot">
            ¡Gracias por su compra!<br>
            {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
@endsection
