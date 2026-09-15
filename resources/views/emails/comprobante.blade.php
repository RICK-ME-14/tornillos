<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#eef1f4;font-family:'Segoe UI',Arial,sans-serif;color:#333">
    <div style="max-width:560px;margin:24px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 3px 14px rgba(16,42,67,.08)">
        <div style="background:linear-gradient(120deg,#0e5c82,#1583b8);color:#fff;padding:22px 26px">
            <div style="font-size:18px;font-weight:800">{{ $cfg->razon_social ?: config('app.name') }}</div>
            @if($cfg->ruc)<div style="font-size:12px;opacity:.9">RUC {{ $cfg->ruc }}</div>@endif
        </div>

        <div style="padding:26px">
            <p style="margin:0 0 14px">Estimado(a) <strong>{{ $venta->cliente->nombre ?? 'cliente' }}</strong>,</p>
            <p style="margin:0 0 18px;font-size:14px;line-height:1.5">
                Adjuntamos su <strong>{{ strtolower($venta->comprobanteTitulo()) }}</strong>
                emitida electrónicamente. Encontrará el archivo <strong>XML</strong> y la
                constancia de recepción (<strong>CDR</strong>) de SUNAT.
            </p>

            <table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:18px">
                <tr><td style="padding:6px 0;color:#6b7480">Comprobante</td>
                    <td style="padding:6px 0;text-align:right;font-weight:700">{{ $venta->comprobanteElectronico() ?? $venta->numero }}</td></tr>
                <tr><td style="padding:6px 0;color:#6b7480">Fecha</td>
                    <td style="padding:6px 0;text-align:right">{{ optional($venta->created_at)->format('d/m/Y H:i') }}</td></tr>
                <tr><td style="padding:6px 0;color:#6b7480">Total</td>
                    <td style="padding:6px 0;text-align:right;font-weight:800;color:#1583b8">S/ {{ number_format($venta->total,2) }}</td></tr>
                <tr><td style="padding:6px 0;color:#6b7480">Estado SUNAT</td>
                    <td style="padding:6px 0;text-align:right">{{ $venta->feEstadoLabel() }}</td></tr>
            </table>

            <p style="margin:6px 0 0;font-size:13px;color:#333">
                Los archivos <strong>XML</strong> y <strong>CDR</strong> (y el PDF, si aplica) se adjuntan a este correo.
            </p>

            <p style="margin:22px 0 0;font-size:12px;color:#9aa3ab">
                Este es un mensaje automático. Puede verificar la validez de su comprobante en el portal de SUNAT.
            </p>
        </div>

        <div style="background:#f4f8fb;padding:14px 26px;font-size:12px;color:#9aa3ab;text-align:center">
            {{ $cfg->nombre_comercial ?: $cfg->razon_social ?: config('app.name') }}
            @if($cfg->direccion_fiscal) · {{ $cfg->direccion_fiscal }}@endif
        </div>
    </div>
</body>
</html>
