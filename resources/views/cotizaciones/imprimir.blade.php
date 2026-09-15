<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cotización {{ $cotizacion->numero }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>
        /* Documento para entregar al cliente: sin dependencias externas,
           en blanco y negro salvo el acento de la marca. */
        *{box-sizing:border-box}
        body{margin:0;padding:28px;background:#f2f3f5;color:#1f252b;
             font-family:'Segoe UI',Roboto,Arial,sans-serif;font-size:13px;line-height:1.5}
        .hoja{max-width:820px;margin:0 auto;background:#fff;padding:38px 40px;
              border-radius:6px;box-shadow:0 4px 24px rgba(0,0,0,.10)}

        .cab{display:flex;justify-content:space-between;align-items:flex-start;
             gap:24px;padding-bottom:20px;border-bottom:2px solid #e0930f}
        .emisor{display:flex;gap:14px;align-items:flex-start}
        .emisor img{width:52px;height:52px;object-fit:contain}
        .emisor h1{margin:0 0 3px;font-size:19px;letter-spacing:-.2px}
        .emisor p{margin:1px 0;font-size:11.5px;color:#5c666f}
        .doc{text-align:right;border:1.5px solid #e0930f;border-radius:6px;padding:12px 18px;min-width:210px}
        .doc .tipo{font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#a86f10;font-weight:700}
        .doc .num{font-size:19px;font-weight:800;margin-top:2px}
        .doc .ruc{font-size:11.5px;color:#5c666f;margin-top:5px}

        .meta{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin:22px 0 18px}
        .meta h2{margin:0 0 7px;font-size:10.5px;letter-spacing:1.4px;
                 text-transform:uppercase;color:#8a939c}
        .meta .fila{display:flex;gap:8px;font-size:12.5px;margin-bottom:2px}
        .meta .fila span{color:#6d7780;min-width:88px}

        table{width:100%;border-collapse:collapse;margin-top:6px}
        thead th{background:#f6f7f9;font-size:10.5px;letter-spacing:.9px;text-transform:uppercase;
                 color:#5c666f;padding:9px 10px;text-align:left;border-bottom:1.5px solid #e3e7eb}
        tbody td{padding:10px;border-bottom:1px solid #eef1f4;vertical-align:top}
        .r{text-align:right}
        .exo{display:inline-block;background:#f1eefc;color:#5f45c8;border-radius:6px;
             padding:1px 7px;font-size:10px;font-weight:700;margin-left:5px}

        .cierre{display:flex;justify-content:space-between;gap:32px;margin-top:22px}
        .notas{flex:1;font-size:12px;color:#5c666f}
        .notas h3{margin:0 0 6px;font-size:10.5px;letter-spacing:1.4px;
                  text-transform:uppercase;color:#8a939c}
        .totales{min-width:270px}
        .totales .r2{display:flex;justify-content:space-between;padding:6px 0;font-size:13px}
        .totales .total{border-top:2px solid #1f252b;margin-top:6px;padding-top:10px;
                        font-size:17px;font-weight:800}
        .letras{margin-top:14px;font-size:11.5px;color:#5c666f;
                border:1px dashed #d7dce1;border-radius:6px;padding:9px 12px}

        .pie{margin-top:26px;padding-top:14px;border-top:1px solid #eef1f4;
             font-size:11px;color:#8a939c;text-align:center;line-height:1.6}
        .validez{background:#fdf3e0;border:1px solid #f0d9ae;color:#8a5c0c;
                 border-radius:6px;padding:9px 13px;font-size:12px;margin-top:16px;font-weight:600}

        .barra{max-width:820px;margin:0 auto 16px;display:flex;gap:10px;justify-content:flex-end}
        .barra button,.barra a{font:inherit;font-size:13px;font-weight:600;cursor:pointer;
             border:1px solid #d7dce1;background:#fff;color:#1f252b;
             padding:9px 16px;border-radius:8px;text-decoration:none}
        .barra button{background:#e0930f;border-color:#e0930f;color:#2b1e05}

        @media print{
            body{background:#fff;padding:0}
            .hoja{box-shadow:none;border-radius:0;padding:0;max-width:none}
            .barra{display:none}
        }
    </style>
</head>
<body>

<div class="barra">
    <a href="{{ route('cotizaciones.show', $cotizacion) }}">Volver</a>
    <a href="{{ route('cotizaciones.ticket', $cotizacion) }}">Ver ticket 80 mm</a>
    <button onclick="window.print()">Imprimir / Guardar PDF</button>
</div>

<div class="hoja">
    <div class="cab">
        <div class="emisor">
            @if($empresa->logo ?? false)
                <img src="{{ asset('storage/'.$empresa->logo) }}" alt="">
            @else
                <img src="{{ asset('img/logo.svg') }}" alt="">
            @endif
            <div>
                <h1>{{ $empresa->nombre ?? config('app.name') }}</h1>
                @if($empresa->direccion ?? false)<p>{{ $empresa->direccion }}</p>@endif
                @if($empresa->telefono ?? false)<p>Tel. {{ $empresa->telefono }}</p>@endif
                @if($empresa->email ?? false)<p>{{ $empresa->email }}</p>@endif
            </div>
        </div>
        <div class="doc">
            <div class="tipo">Cotización</div>
            <div class="num">{{ $cotizacion->numero }}</div>
            @if($empresa->ruc ?? false)<div class="ruc">RUC {{ $empresa->ruc }}</div>@endif
        </div>
    </div>

    <div class="meta">
        <div>
            <h2>Cliente</h2>
            <div class="fila"><span>Nombre</span><strong>{{ $cotizacion->cliente->nombre ?? 'Sin cliente asignado' }}</strong></div>
            @if($cotizacion->cliente?->numero_documento)
                <div class="fila"><span>{{ $cotizacion->cliente->tipo_documento }}</span>{{ $cotizacion->cliente->numero_documento }}</div>
            @endif
            @if($cotizacion->cliente?->direccion)
                <div class="fila"><span>Dirección</span>{{ $cotizacion->cliente->direccion }}</div>
            @endif
        </div>
        <div>
            <h2>Documento</h2>
            <div class="fila"><span>Emitida</span>{{ $cotizacion->fecha?->format('d/m/Y') }}</div>
            <div class="fila"><span>Válida hasta</span><strong>{{ $cotizacion->valida_hasta?->format('d/m/Y') }}</strong></div>
            <div class="fila"><span>Atendido por</span>{{ $cotizacion->usuario->name ?? '—' }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:44px">#</th>
                <th>Descripción</th>
                <th style="width:70px">Unidad</th>
                <th class="r" style="width:70px">Cant.</th>
                <th class="r" style="width:100px">P. unit.</th>
                <th class="r" style="width:110px">Importe</th>
            </tr>
        </thead>
        <tbody>
        @foreach($cotizacion->detalles as $i => $d)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    {{ $d->descripcion }}
                    @if($d->tipo_afectacion_igv !== '10')
                        <span class="exo">{{ $d->tipo_afectacion_igv === '20' ? 'Exonerado' : 'Inafecto' }}</span>
                    @endif
                </td>
                <td>{{ $d->unidad_sunat }}</td>
                <td class="r">{{ $d->cantidad }}</td>
                <td class="r">{{ number_format($d->precio, 2) }}</td>
                <td class="r"><strong>{{ number_format($d->subtotal, 2) }}</strong></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="cierre">
        <div class="notas">
            @if($cotizacion->observacion)
                <h3>Observaciones</h3>
                {{ $cotizacion->observacion }}
            @endif
        </div>
        <div class="totales">
            @php($m = $empresa->moneda ?? 'S/')
            <div class="r2"><span>Subtotal</span><span>{{ $m }} {{ number_format($cotizacion->subtotal, 2) }}</span></div>
            @if($cotizacion->descuento > 0)
                <div class="r2"><span>Descuento</span><span>− {{ $m }} {{ number_format($cotizacion->descuento, 2) }}</span></div>
            @endif
            <div class="r2"><span>IGV ({{ rtrim(rtrim(number_format($empresa->igv ?? 18, 2), '0'), '.') }}%)</span><span>{{ $m }} {{ number_format($cotizacion->impuesto, 2) }}</span></div>
            <div class="r2 total"><span>TOTAL</span><span>{{ $m }} {{ number_format($cotizacion->total, 2) }}</span></div>
        </div>
    </div>

    <div class="letras">
        <strong>SON:</strong> {{ \App\Support\NumeroALetras::moneda((float) $cotizacion->total, $empresa->moneda ?? 'S/') }}
    </div>

    <div class="validez">
        Esta cotización tiene validez hasta el {{ $cotizacion->valida_hasta?->format('d/m/Y') }}.
        Pasada esa fecha los precios pueden variar.
    </div>

    <div class="pie">
        Documento sin valor tributario. No sustituye a la boleta ni a la factura,
        que se emiten al concretarse la venta.
    </div>
</div>

</body>
</html>
