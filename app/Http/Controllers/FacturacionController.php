<?php

namespace App\Http\Controllers;

use App\Mail\ComprobanteElectronicoMail;
use App\Models\FacturacionConfig;
use App\Models\Venta;
use App\Services\Facturacion\FacturacionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Configuración de la Facturación Electrónica (SUNAT · Perú).
 */
class FacturacionController extends Controller
{
    public function edit()
    {
        return view('facturacion.configuracion', [
            'cfg' => FacturacionConfig::actual(),
        ]);
    }

    public function update(Request $request)
    {
        $config = FacturacionConfig::actual();

        $data = $request->validate([
            'habilitado' => ['nullable', 'boolean'],
            'emitir_automatico' => ['nullable', 'boolean'],
            'modo_boleta' => ['required', 'in:individual,resumen'],
            'driver' => ['required', 'in:none,greenter'],
            'entorno' => ['required', 'in:beta,produccion'],

            'ruc' => ['nullable', 'string', 'max:15'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'direccion_fiscal' => ['nullable', 'string', 'max:255'],
            'ubigeo' => ['nullable', 'string', 'max:10'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'distrito' => ['nullable', 'string', 'max:100'],

            'serie_boleta' => ['nullable', 'string', 'max:8'],
            'serie_factura' => ['nullable', 'string', 'max:8'],
            'serie_nota_credito' => ['nullable', 'string', 'max:8'],
            'serie_nc_factura' => ['nullable', 'string', 'max:8'],

            'sol_user' => ['nullable', 'string', 'max:100'],
            'sol_pass' => ['nullable', 'string', 'max:100'],
            'certificado_ruta' => ['nullable', 'string', 'max:255'],
            'certificado_pass' => ['nullable', 'string', 'max:100'],
        ], [
            'driver.required' => 'Selecciona un driver de emisión.',
            'entorno.required' => 'Selecciona el entorno de SUNAT.',
        ]);

        $data['habilitado'] = $request->boolean('habilitado');
        $data['emitir_automatico'] = $request->boolean('emitir_automatico');

        // No sobrescribir credenciales si el campo se deja vacío.
        if (empty($data['sol_pass'])) {
            unset($data['sol_pass']);
        }
        if (empty($data['certificado_pass'])) {
            unset($data['certificado_pass']);
        }

        $config->update($data);

        return back()->with('success', 'Configuración de facturación electrónica guardada.');
    }

    /**
     * Prueba la conexión con SUNAT usando las credenciales guardadas.
     * Responde JSON (para uso vía fetch/AJAX) o redirect si es formulario.
     */
    public function probarConexion(Request $request, FacturacionManager $manager)
    {
        $config = FacturacionConfig::actual();
        $resultado = $manager->probarConexion($config);

        $config->forceFill([
            'probado_en' => now(),
            'estado_conexion' => $resultado->exito ? 'ok' : 'error',
            'mensaje_conexion' => $resultado->mensaje,
        ])->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'exito' => $resultado->exito,
                'mensaje' => $resultado->mensaje,
            ], $resultado->exito ? 200 : 422);
        }

        return back()->with($resultado->exito ? 'success' : 'error', $resultado->mensaje);
    }

    /**
     * Bandeja de comprobantes electrónicos: listado con filtros, resumen
     * por estado y acciones (reenviar, imprimir, descargar XML/CDR).
     */
    public function comprobantes(Request $request)
    {
        $estado = $request->get('estado');
        $tipo = $request->get('tipo');
        $q = trim((string) $request->get('q'));
        $desde = $request->get('desde');
        $hasta = $request->get('hasta');

        $base = Venta::query()
            ->whereIn('tipo_comprobante', ['BOLETA', 'FACTURA'])
            ->where('fe_estado', '!=', 'NO_APLICA');

        // Resumen por estado (sobre el universo sin filtro de estado).
        $resumen = (clone $base)
            ->selectRaw('fe_estado, COUNT(*) as total, COALESCE(SUM(total),0) as monto')
            ->groupBy('fe_estado')
            ->pluck('total', 'fe_estado');

        $comprobantes = $base
            // PROBLEMA agrupa lo que no llego a destino, que es como se mira
            // en la practica: rechazado por SUNAT o fallado en el envio.
            ->when($estado === 'PROBLEMA', fn ($query) => $query->whereIn('fe_estado', ['RECHAZADO', 'ERROR']))
            ->when($estado && $estado !== 'PROBLEMA', fn ($query) => $query->where('fe_estado', $estado))
            ->when($tipo, fn ($query) => $query->where('tipo_comprobante', $tipo))
            ->when($desde, fn ($query) => $query->whereDate('created_at', '>=', $desde))
            ->when($hasta, fn ($query) => $query->whereDate('created_at', '<=', $hasta))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('fe_serie', 'like', "%{$q}%")
                        ->orWhere('fe_correlativo', 'like', "%{$q}%")
                        ->orWhere('numero', 'like', "%{$q}%")
                        ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$q}%"));
                });
            })
            ->with('cliente')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('facturacion.comprobantes', [
            'comprobantes' => $comprobantes,
            'resumen' => $resumen,
            'cfg' => FacturacionConfig::actual(),
            'filtros' => compact('estado', 'tipo', 'q', 'desde', 'hasta'),
        ]);
    }

    /** Reenvía a SUNAT un comprobante PENDIENTE/ERROR. */
    public function reenviar(Venta $venta, FacturacionManager $manager)
    {
        if (! in_array($venta->fe_estado, ['PENDIENTE', 'ERROR'], true)) {
            return back()->with('error', 'Solo se pueden reenviar comprobantes pendientes o con error.');
        }

        $r = $manager->emitirParaVenta($venta, true);

        return back()->with($r->exito ? 'success' : 'error',
            "Comprobante {$venta->comprobanteElectronico()}: {$r->mensaje}");
    }

    /** Genera y envía el resumen diario de boletas (del día anterior). */
    public function enviarResumenDiario(Request $request, FacturacionManager $manager)
    {
        $fecha = $request->filled('fecha')
            ? \Illuminate\Support\Carbon::parse($request->input('fecha'))
            : null;

        $res = $manager->enviarResumenDiario($fecha);

        return back()->with($res['ok'] ? 'success' : 'error', $res['mensaje']);
    }

    /** Reenvía en bloque todos los comprobantes pendientes/con error. */
    public function reenviarPendientes(FacturacionManager $manager)
    {
        $res = $manager->reintentarPendientes();

        if ($res['total'] === 0) {
            return back()->with('success', 'No hay comprobantes pendientes por reenviar.');
        }

        return back()->with('success',
            "Reenvío procesado: {$res['ok']} aceptados/enviados, {$res['fail']} con problemas de {$res['total']}.");
    }

    /** Descarga el XML firmado del comprobante. */
    public function descargarXml(Venta $venta)
    {
        return $this->descargar($venta->fe_xml_ruta, $venta->comprobanteElectronico() . '.xml');
    }

    /** Descarga el CDR (constancia de recepción) de SUNAT. */
    public function descargarCdr(Venta $venta)
    {
        return $this->descargar($venta->fe_cdr_ruta, 'R-' . $venta->comprobanteElectronico() . '.zip');
    }

    private function descargar(?string $ruta, string $nombre)
    {
        if (! $ruta || ! Storage::disk('facturacion')->exists($ruta)) {
            return back()->with('error', 'El archivo no está disponible.');
        }
        return Storage::disk('facturacion')->download($ruta, $nombre);
    }

    /** Envía el comprobante por correo al cliente (XML + CDR + PDF si es posible). */
    public function enviarCorreo(Venta $venta)
    {
        $cfg = FacturacionConfig::actual();
        $venta->load('detalles', 'cliente');

        $email = $venta->cliente->email ?? null;
        if (! $email) {
            return back()->with('error', 'El cliente no tiene un correo registrado.');
        }
        if (! $venta->feEmitido()) {
            return back()->with('error', 'El comprobante aún no fue aceptado/enviado a SUNAT.');
        }

        $pdf = $this->generarPdf($venta, $cfg);

        try {
            Mail::to($email)->send(new ComprobanteElectronicoMail($venta, $cfg, $pdf));
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo enviar el correo: ' . $e->getMessage());
        }

        $venta->forceFill(['fe_email_enviado_en' => now()])->save();

        return back()->with('success', "Comprobante enviado a {$email}.");
    }

    /**
     * Genera un PDF del comprobante si hay un motor disponible (dompdf).
     * Devuelve la ruta absoluta del archivo o null si no se pudo generar.
     */
    private function generarPdf(Venta $venta, FacturacionConfig $cfg): ?string
    {
        if (! class_exists('\\Dompdf\\Dompdf')) {
            return null; // "composer require dompdf/dompdf" para habilitarlo
        }

        try {
            $html = view('facturacion.comprobante_pdf', compact('venta', 'cfg'))->render();

            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();

            $numero = $venta->comprobanteElectronico() ?? $venta->numero;
            $dir = storage_path('app/facturacion/pdf');
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $ruta = $dir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], '-', $numero) . '.pdf';
            file_put_contents($ruta, $dompdf->output());

            return $ruta;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Representación impresa A4 del comprobante. */
    public function comprobante(Venta $venta)
    {
        $venta->load('detalles', 'cliente', 'usuario');

        return view('facturacion.comprobante', [
            'venta' => $venta,
            'cfg' => FacturacionConfig::actual(),
            'qr' => $this->datosQr($venta),
        ]);
    }

    /** Representación impresa en ticket (80mm) del comprobante. */
    public function ticket(Venta $venta)
    {
        $venta->load('detalles', 'cliente', 'usuario');

        return view('facturacion.ticket', [
            'venta' => $venta,
            'cfg' => FacturacionConfig::actual(),
            'qr' => $this->datosQr($venta),
        ]);
    }

    /**
     * Cadena para el código QR según el formato de SUNAT:
     * RUC | TIPO | SERIE | CORRELATIVO | IGV | TOTAL | FECHA | TIPODOC_CLI | NUMDOC_CLI
     */
    private function datosQr(Venta $venta): string
    {
        $cfg = FacturacionConfig::actual();
        $tipoDoc = strtoupper($venta->tipo_comprobante) === 'FACTURA' ? '01' : '03';
        $tipoDocCli = strtoupper($venta->tipo_comprobante) === 'FACTURA' ? '6' : '1';
        $numDocCli = $venta->cliente->numero_documento ?? '00000000';

        return implode('|', [
            $cfg->ruc,
            $tipoDoc,
            $venta->fe_serie,
            $venta->fe_correlativo,
            number_format((float) $venta->impuesto, 2, '.', ''),
            number_format((float) $venta->total, 2, '.', ''),
            optional($venta->created_at)->format('Y-m-d'),
            $tipoDocCli,
            $numDocCli,
        ]);
    }
}
