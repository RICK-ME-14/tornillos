<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        *{box-sizing:border-box}
        body{font-family:DejaVu Sans, Arial, sans-serif;color:#333;font-size:12px;margin:0}
        .top{width:100%}
        .top td{vertical-align:top}
        h1{margin:0 0 4px;font-size:18px;color:#1583b8}
        .muted{color:#555;font-size:11px}
        .doc-box{border:2px solid #1583b8;border-radius:8px;text-align:center;padding:10px}
        .doc-box .tit{font-size:13px;font-weight:bold;color:#1583b8;margin:5px 0}
        .doc-box .num{font-size:15px;font-weight:bold}
        .cliente{border:1px solid #d9e2e9;border-radius:8px;padding:10px;margin-top:16px;font-size:12px}
        .cliente b{color:#6b7480}
        table.items{width:100%;border-collapse:collapse;margin-top:14px}
        table.items th{background:#1583b8;color:#fff;padding:7px;text-align:left;font-size:11px}
        table.items td{padding:6px 7px;border-bottom:1px solid #e3eaef}
        .r{text-align:right}
        .tots{width:40%;margin-left:60%;margin-top:12px;border-collapse:collapse}
        .tots td{padding:5px 7px}
        .tots .big td{font-size:14px;font-weight:bold;color:#1583b8;border-top:2px solid #d9e2e9}
        .letras{margin-top:12px;background:#f4f8fb;border:1px solid #d9e2e9;border-radius:6px;padding:9px 12px;font-size:11px}
        .foot{margin-top:16px;font-size:10px;color:#8a929b}
        .anulado{color:#c0392b;font-weight:bold;font-size:14px;text-align:center;border:2px solid #c0392b;padding:5px;margin:10px 0}
    </style>
</head>
<body>
@php use App\Support\NumeroALetras; $moneda = $cfg->pais === 'PE' ? 'S/' : ($venta->empresa->moneda ?? 'S/'); @endphp

<table class="top">
    <tr>
        <td style="width:60%">
            <h1>{{ $cfg->razon_social ?: config('app.name') }}</h1>
            @if($cfg->nombre_comercial)<div class="muted">{{ $cfg->nombre_comercial }}</div>@endif
            @if($cfg->direccion_fiscal)<div class="muted">{{ $cfg->direccion_fiscal }}</div>@endif
            <div class="muted">{{ trim(($cfg->distrito ? $cfg->distrito.' - ' : '').($cfg->provincia ? $cfg->provincia.' - ' : '').($cfg->departamento ?? ''), ' -') }}</div>
        </td>
        <td style="width:40%">
            <div class="doc-box">
                <div style="font-weight:bold">RUC {{ $cfg->ruc ?: '—' }}</div>
                <div class="tit">{{ $venta->comprobanteTitulo() }}</div>
                <div class="num">{{ $venta->comprobanteElectronico() ?? $venta->numero }}</div>
            </div>
        </td>
    </tr>
</table>

@if($venta->estado === 'ANULADA')<div class="anulado">** COMPROBANTE ANULADO **</div>@endif

<div class="cliente">
    <b>Cliente:</b> {{ $venta->cliente->nombre ?? 'CLIENTE VARIOS' }} &nbsp;&nbsp;
    <b>{{ strtoupper($venta->tipo_comprobante) === 'FACTURA' ? 'RUC' : 'Doc' }}:</b> {{ $venta->cliente->numero_documento ?? '—' }}<br>
    <b>Fecha:</b> {{ optional($venta->created_at)->format('d/m/Y H:i') }} &nbsp;&nbsp;
    <b>Moneda:</b> {{ $cfg->pais === 'PE' ? 'SOLES (PEN)' : $moneda }}
</div>

<table class="items">
    <thead>
        <tr><th style="width:60px">Cant.</th><th>Descripción</th><th class="r" style="width:90px">V. Unit.</th><th class="r" style="width:100px">Importe</th></tr>
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

<table class="tots">
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
    <tr class="big"><td>TOTAL</td><td class="r">{{ $moneda }} {{ number_format($venta->total,2) }}</td></tr>
</table>

<div class="letras"><b>SON:</b> {{ NumeroALetras::moneda((float) $venta->total, $moneda) }}</div>

<div class="foot">
    @if($venta->fe_hash)<b>Hash:</b> {{ $venta->fe_hash }}<br>@endif
    @if($venta->notaCreditoNumero())<b>Nota de crédito:</b> {{ $venta->notaCreditoNumero() }} ({{ $venta->fe_nc_estado }})<br>@endif
    Representación impresa del comprobante electrónico. Consulte su validez en el portal de SUNAT.
</div>
</body>
</html>
