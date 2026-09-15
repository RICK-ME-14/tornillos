<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket {{ $venta->comprobanteElectronico() ?? $venta->numero }}</title>
    <style>
        *{box-sizing:border-box}
        body{background:#eef1f4;margin:0;font-family:'Consolas','Courier New',monospace;color:#111}
        .actions{width:320px;margin:16px auto 0;text-align:center}
        .btn{display:inline-block;background:#1583b8;color:#fff;border:none;border-radius:8px;
             padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;margin:2px}
        .btn.light{background:#fff;color:#1583b8;border:1px solid #1583b8}
        .ticket{width:302px;margin:14px auto;background:#fff;padding:16px 14px;box-shadow:0 3px 12px rgba(0,0,0,.12);font-size:12px;line-height:1.45}
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
        #qr{display:flex;justify-content:center;margin:10px 0}
        .anulado{text-align:center;color:#c0392b;font-weight:700;border:2px solid #c0392b;border-radius:6px;padding:4px;margin:8px 0}
        @media print{ body{background:#fff} .actions{display:none} .ticket{box-shadow:none;margin:0;width:100%} @page{margin:4mm} }
    </style>
</head>
<body>
@php use App\Support\NumeroALetras; $moneda = $cfg->pais === 'PE' ? 'S/' : ($venta->empresa->moneda ?? 'S/'); @endphp

<div class="actions">
    <a href="{{ url()->previous() }}" class="btn light">Volver</a>
    <a href="{{ route('facturacion.comprobante', $venta) }}" class="btn light">Ver A4</a>
    <button class="btn" onclick="window.print()">Imprimir</button>
</div>

<div class="ticket">
    <h2>{{ $cfg->razon_social ?: config('app.name') }}</h2>
    @if($cfg->nombre_comercial)<div class="c muted">{{ $cfg->nombre_comercial }}</div>@endif
    <div class="c muted">RUC {{ $cfg->ruc ?: '—' }}</div>
    @if($cfg->direccion_fiscal)<div class="c muted">{{ $cfg->direccion_fiscal }}</div>@endif

    <div class="doc">
        {{ $venta->comprobanteTitulo() }}<br>
        {{ $venta->comprobanteElectronico() ?? $venta->numero }}
    </div>

    @if($venta->estado === 'ANULADA')<div class="anulado">** ANULADO **</div>@endif

    <div>Fecha: {{ optional($venta->created_at)->format('d/m/Y H:i') }}</div>
    <div>Cliente: {{ $venta->cliente->nombre ?? 'CLIENTE VARIOS' }}</div>
    @if($venta->cliente && $venta->cliente->numero_documento)
        <div>Doc: {{ $venta->cliente->numero_documento }}</div>
    @endif

    <div class="hr"></div>
    <table>
        <tr class="muted"><td>Cant/Desc</td><td class="r">Importe</td></tr>
        @foreach($venta->detalles as $d)
        <tr>
            <td>{{ rtrim(rtrim(number_format($d->cantidad,2),'0'),'.') }} x {{ $d->descripcion }}<br>
                <span class="muted">{{ number_format($d->precio,2) }} c/u</span></td>
            <td class="r">{{ number_format($d->subtotal,2) }}</td>
        </tr>
        @endforeach
    </table>

    <div class="hr"></div>
    @if($venta->descuento > 0)
        <div class="tot"><span>Descuento</span><span>{{ $moneda }} {{ number_format($venta->descuento,2) }}</span></div>
    @endif
    <div class="tot"><span>Op. Gravada</span><span>{{ $moneda }} {{ number_format($venta->subtotal - $venta->descuento,2) }}</span></div>
    <div class="tot"><span>IGV</span><span>{{ $moneda }} {{ number_format($venta->impuesto,2) }}</span></div>
    <div class="tot big"><span>TOTAL</span><span>{{ $moneda }} {{ number_format($venta->total,2) }}</span></div>

    <div class="hr"></div>
    <div class="muted">SON: {{ NumeroALetras::moneda((float) $venta->total, $moneda) }}</div>

    <div id="qr" data-qr="{{ $qr }}"></div>
    <div class="c muted">Estado SUNAT: {{ $venta->feEstadoLabel() }}</div>
    @if($venta->notaCreditoNumero())
        <div class="c muted">NC: {{ $venta->notaCreditoNumero() }} ({{ $venta->fe_nc_estado }})</div>
    @endif
    <div class="c muted" style="margin-top:6px">¡Gracias por su compra!</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    (function(){
        var el = document.getElementById('qr');
        var data = el.getAttribute('data-qr') || '';
        try { new QRCode(el, { text: data, width: 110, height: 110, correctLevel: QRCode.CorrectLevel.M }); }
        catch(e){ el.textContent = 'QR'; }
    })();
</script>
</body>
</html>
