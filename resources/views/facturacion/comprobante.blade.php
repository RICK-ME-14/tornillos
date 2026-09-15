<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $venta->comprobanteTitulo() }} {{ $venta->comprobanteElectronico() ?? $venta->numero }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{ --brand:#1583b8; --ink:#333; --line:#d9e2e9; }
        *{box-sizing:border-box}
        body{font-family:'Segoe UI',Arial,sans-serif;color:var(--ink);margin:0;background:#eef1f4}
        .sheet{background:#fff;max-width:820px;margin:24px auto;padding:34px 40px;position:relative;
               box-shadow:0 4px 18px rgba(16,42,67,.12)}
        .bar{position:absolute;left:0;top:0;height:6px;width:100%;background:linear-gradient(90deg,var(--brand),#29b6d8)}
        .top{display:flex;gap:20px;align-items:flex-start;justify-content:space-between;padding-top:12px}
        .emisor h1{margin:0 0 4px;font-size:20px;color:var(--brand);font-weight:800}
        .emisor p{margin:1px 0;font-size:12px;color:#555}
        .doc-box{border:2px solid var(--brand);border-radius:10px;text-align:center;padding:12px 22px;min-width:230px}
        .doc-box .ruc{font-size:13px;font-weight:700;color:var(--ink)}
        .doc-box .tit{font-size:14px;font-weight:800;color:var(--brand);margin:6px 0}
        .doc-box .num{font-size:16px;font-weight:800;letter-spacing:1px}
        .cliente{margin-top:22px;border:1px solid var(--line);border-radius:10px;padding:14px 16px;
                 display:grid;grid-template-columns:1fr 1fr;gap:4px 24px;font-size:12.5px}
        .cliente b{color:#6b7480;font-weight:600;display:inline-block;min-width:130px}
        table{width:100%;border-collapse:collapse;margin-top:18px;font-size:12.5px}
        thead th{background:var(--brand);color:#fff;padding:9px 8px;text-align:left;font-weight:600}
        thead th.r,tbody td.r{text-align:right}
        tbody td{padding:8px;border-bottom:1px solid var(--line)}
        .tots{margin-top:16px;display:flex;justify-content:flex-end}
        .tots table{width:320px;margin:0}
        .tots td{padding:6px 8px;border:none;font-size:13px}
        .tots .big td{font-size:16px;font-weight:800;color:var(--brand);border-top:2px solid var(--line)}
        .letras{margin-top:16px;font-size:12.5px;background:#f4f8fb;border:1px solid var(--line);border-radius:8px;padding:10px 14px}
        .foot{margin-top:22px;display:flex;gap:20px;align-items:flex-end;justify-content:space-between}
        .foot .hash{font-size:11px;color:#8a929b;max-width:520px;word-break:break-all}
        #qr{width:120px;height:120px}
        .estado{margin-top:10px;font-size:12px}
        .estado .ok{color:#1e9e5a;font-weight:700}
        .estado .warn{color:#d98613;font-weight:700}
        .estado .err{color:#c0392b;font-weight:700}
        .watermark{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
                   font-size:120px;font-weight:900;color:rgba(192,57,43,.12);transform:rotate(-25deg);pointer-events:none}
        .actions{max-width:820px;margin:16px auto 0;text-align:right}
        .btn{display:inline-block;background:var(--brand);color:#fff;border:none;border-radius:8px;
             padding:9px 16px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none}
        .btn.light{background:#fff;color:var(--brand);border:1px solid var(--brand)}
        @media print{ body{background:#fff} .actions{display:none} .sheet{box-shadow:none;margin:0;max-width:none} }
    </style>
</head>
<body>
@php use App\Support\NumeroALetras; $moneda = $cfg->pais === 'PE' ? 'S/' : ($venta->empresa->moneda ?? 'S/'); @endphp

<div class="actions">
    <a href="{{ url()->previous() }}" class="btn light"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    <a href="{{ route('facturacion.ticket', $venta) }}" class="btn light"><i class="fa-solid fa-receipt"></i> Ver ticket</a>
    <button class="btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir / PDF</button>
</div>

<div class="sheet">
    <div class="bar"></div>
    @if($venta->estado === 'ANULADA')
        <div class="watermark">ANULADO</div>
    @endif

    <div class="top">
        <div class="emisor">
            <h1>{{ $cfg->razon_social ?: config('app.name') }}</h1>
            @if($cfg->nombre_comercial)<p>{{ $cfg->nombre_comercial }}</p>@endif
            @if($cfg->direccion_fiscal)<p>{{ $cfg->direccion_fiscal }}</p>@endif
            <p>{{ trim(($cfg->distrito ? $cfg->distrito.' - ' : '').($cfg->provincia ? $cfg->provincia.' - ' : '').($cfg->departamento ?? ''), ' -') }}</p>
        </div>
        <div class="doc-box">
            <div class="ruc">RUC {{ $cfg->ruc ?: '—' }}</div>
            <div class="tit">{{ $venta->comprobanteTitulo() }}</div>
            <div class="num">{{ $venta->comprobanteElectronico() ?? $venta->numero }}</div>
        </div>
    </div>

    <div class="cliente">
        <div><b>Cliente:</b> {{ $venta->cliente->nombre ?? 'CLIENTE VARIOS' }}</div>
        <div><b>Fecha de emisión:</b> {{ optional($venta->created_at)->format('d/m/Y H:i') }}</div>
        <div><b>{{ strtoupper($venta->tipo_comprobante) === 'FACTURA' ? 'RUC' : 'Documento' }}:</b>
            {{ $venta->cliente->numero_documento ?? '—' }}</div>
        <div><b>Moneda:</b> {{ $cfg->pais === 'PE' ? 'SOLES (PEN)' : $moneda }}</div>
        @if($venta->cliente && $venta->cliente->direccion)
            <div style="grid-column:1/-1"><b>Dirección:</b> {{ $venta->cliente->direccion }}</div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:70px">Cant.</th>
                <th>Descripción</th>
                <th class="r" style="width:110px">V. Unit.</th>
                <th class="r" style="width:120px">Importe</th>
            </tr>
        </thead>
        <tbody>
        @foreach($venta->detalles as $d)
            <tr>
                <td>{{ rtrim(rtrim(number_format($d->cantidad,2),'0'),'.') }} NIU</td>
                <td>{{ $d->descripcion }}</td>
                <td class="r">{{ number_format($d->precio,2) }}</td>
                <td class="r">{{ number_format($d->subtotal,2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="tots">
        <table>
            @if($venta->descuento > 0)
            <tr><td>Descuentos</td><td class="r">{{ $moneda }} {{ number_format($venta->descuento,2) }}</td></tr>
            @endif
            @php($desglose = $venta->desgloseAfectacion())
            <tr><td>Op. Gravada</td><td class="r">{{ $moneda }} {{ number_format($desglose['gravadas'],2) }}</td></tr>
            @if($desglose['exoneradas'] > 0)
            <tr><td>Op. Exonerada</td><td class="r">{{ $moneda }} {{ number_format($desglose['exoneradas'],2) }}</td></tr>
            @endif
            @if($desglose['inafectas'] > 0)
            <tr><td>Op. Inafecta</td><td class="r">{{ $moneda }} {{ number_format($desglose['inafectas'],2) }}</td></tr>
            @endif
            <tr><td>IGV</td><td class="r">{{ $moneda }} {{ number_format($venta->impuesto,2) }}</td></tr>
            <tr class="big"><td>IMPORTE TOTAL</td><td class="r">{{ $moneda }} {{ number_format($venta->total,2) }}</td></tr>
        </table>
    </div>

    <div class="letras">
        <b>SON:</b> {{ NumeroALetras::moneda((float) $venta->total, $moneda) }}
    </div>

    <div class="foot">
        <div>
            <div id="qr" data-qr="{{ $qr }}"></div>
            <div class="estado">
                @if($venta->estado === 'ANULADA')
                    <span class="err">** COMPROBANTE ANULADO **</span><br>
                @endif
                Estado SUNAT: <span class="{{ $venta->fe_estado === 'ACEPTADO' ? 'ok' : ($venta->fe_estado === 'RECHAZADO' || $venta->fe_estado === 'ERROR' ? 'err' : 'warn') }}">{{ $venta->feEstadoLabel() }}</span>
                @if($venta->notaCreditoNumero())
                    <br>Nota de crédito: {{ $venta->notaCreditoNumero() }} ({{ $venta->fe_nc_estado }})
                @endif
            </div>
        </div>
        <div class="hash">
            @if($venta->fe_hash)<b>Hash:</b> {{ $venta->fe_hash }}<br>@endif
            Representación impresa del comprobante electrónico. Consulte su validez en el portal de SUNAT.
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    (function(){
        var el = document.getElementById('qr');
        var data = el.getAttribute('data-qr') || '';
        try { new QRCode(el, { text: data, width: 120, height: 120, correctLevel: QRCode.CorrectLevel.M }); }
        catch(e){ el.textContent = 'QR'; }
    })();
</script>
</body>
</html>
