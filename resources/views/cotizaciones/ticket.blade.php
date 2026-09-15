<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cotización {{ $cotizacion->numero }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>
        /* Ticketera de 80 mm. Monoespaciada y sin color: la impresora
           termica solo imprime negro y el ancho util son ~72 mm. */
        *{box-sizing:border-box}
        body{background:#eef1f4;margin:0;font-family:'Consolas','Courier New',monospace;color:#111}
        .actions{width:320px;margin:16px auto 0;text-align:center}
        .btn{display:inline-block;background:#e0930f;color:#2b1e05;border:none;border-radius:8px;
             padding:8px 14px;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;margin:2px;
             font-family:'Segoe UI',Roboto,Arial,sans-serif}
        .btn.light{background:#fff;color:#3d454e;border:1px solid #d7dce1;font-weight:600}
        .ticket{width:302px;margin:14px auto;background:#fff;padding:16px 14px;
                box-shadow:0 3px 12px rgba(0,0,0,.12);font-size:12px;line-height:1.45}
        .ticket h2{font-size:14px;text-align:center;margin:0 0 2px}
        .c{text-align:center}
        .muted{color:#555}
        .doc{text-align:center;border:1px dashed #999;border-radius:6px;padding:6px;margin:8px 0;font-weight:700}
        .hr{border-top:1px dashed #999;margin:8px 0}
        table{width:100%;border-collapse:collapse}
        td{padding:2px 0;vertical-align:top}
        .r{text-align:right}
        .tot{display:flex;justify-content:space-between}
        .tot.big{font-size:14px;font-weight:700;margin-top:4px}
        .aviso{border:1px solid #999;border-radius:6px;padding:6px;margin:8px 0;text-align:center;font-weight:700}
        .estado{text-align:center;font-weight:700;border:2px solid #111;border-radius:6px;padding:4px;margin:8px 0}
        .pie{text-align:center;font-size:10.5px;color:#555;margin-top:10px;line-height:1.4}

        @media print{
            body{background:#fff}
            .actions{display:none}
            .ticket{box-shadow:none;margin:0;width:100%;padding:0}
            @page{margin:4mm}
        }
    </style>
</head>
<body>
@php
    use App\Support\NumeroALetras;
    $m = $empresa->moneda ?? 'S/';
    $ev = $cotizacion->estadoVisible();
@endphp

<div class="actions">
    <a href="{{ route('cotizaciones.show', $cotizacion) }}" class="btn light">Volver</a>
    <a href="{{ route('cotizaciones.imprimir', $cotizacion) }}" class="btn light">Ver A4</a>
    <button class="btn" onclick="window.print()">Imprimir</button>
</div>

<div class="ticket">
    <h2>{{ $empresa->nombre ?? config('app.name') }}</h2>
    @if($empresa->ruc ?? false)<div class="c muted">RUC {{ $empresa->ruc }}</div>@endif
    @if($empresa->direccion ?? false)<div class="c muted">{{ $empresa->direccion }}</div>@endif
    @if($empresa->telefono ?? false)<div class="c muted">Tel. {{ $empresa->telefono }}</div>@endif

    <div class="doc">
        COTIZACIÓN<br>
        {{ $cotizacion->numero }}
    </div>

    @if($ev !== 'PENDIENTE')
        <div class="estado">** {{ strtoupper($cotizacion->estadoLabel()) }} **</div>
    @endif

    <div>Fecha: {{ $cotizacion->fecha?->format('d/m/Y') }}</div>
    <div>Válida hasta: {{ $cotizacion->valida_hasta?->format('d/m/Y') }}</div>
    <div>Cliente: {{ $cotizacion->cliente->nombre ?? 'SIN CLIENTE' }}</div>
    @if($cotizacion->cliente?->numero_documento)
        <div>Doc: {{ $cotizacion->cliente->numero_documento }}</div>
    @endif
    <div>Atendió: {{ $cotizacion->usuario->name ?? '—' }}</div>

    <div class="hr"></div>

    <table>
        <tr class="muted"><td>Cant/Desc</td><td class="r">Importe</td></tr>
        @foreach($cotizacion->detalles as $d)
            <tr>
                <td colspan="2">{{ $d->descripcion }}@if($d->tipo_afectacion_igv !== '10') ({{ $d->tipo_afectacion_igv === '20' ? 'EXO' : 'INA' }})@endif</td>
            </tr>
            <tr>
                <td class="muted">{{ $d->cantidad }} {{ $d->unidad_sunat }} x {{ number_format($d->precio, 2) }}</td>
                <td class="r">{{ number_format($d->subtotal, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="hr"></div>

    <div class="tot"><span>Subtotal</span><span>{{ $m }} {{ number_format($cotizacion->subtotal, 2) }}</span></div>
    @if($cotizacion->descuento > 0)
        <div class="tot"><span>Descuento</span><span>- {{ $m }} {{ number_format($cotizacion->descuento, 2) }}</span></div>
    @endif
    <div class="tot"><span>IGV ({{ rtrim(rtrim(number_format($empresa->igv ?? 18, 2), '0'), '.') }}%)</span><span>{{ $m }} {{ number_format($cotizacion->impuesto, 2) }}</span></div>
    <div class="tot big"><span>TOTAL</span><span>{{ $m }} {{ number_format($cotizacion->total, 2) }}</span></div>

    <div class="hr"></div>
    <div class="muted" style="font-size:11px">
        SON: {{ NumeroALetras::moneda((float) $cotizacion->total, $m) }}
    </div>

    @if($cotizacion->observacion)
        <div class="hr"></div>
        <div class="muted" style="font-size:11px">{{ $cotizacion->observacion }}</div>
    @endif

    <div class="aviso">
        VÁLIDA HASTA EL {{ $cotizacion->valida_hasta?->format('d/m/Y') }}<br>
        <span style="font-weight:400;font-size:11px">Después los precios pueden variar</span>
    </div>

    <div class="pie">
        Documento sin valor tributario.<br>
        No sustituye a la boleta ni a la factura.<br>
        ¡Gracias por su preferencia!
    </div>
</div>

</body>
</html>
