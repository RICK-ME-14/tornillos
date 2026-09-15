<?php

namespace App\Mail;

use App\Models\FacturacionConfig;
use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Envía al cliente su comprobante electrónico con el XML firmado y el CDR
 * de SUNAT adjuntos (y el PDF si se pudo generar).
 */
class ComprobanteElectronicoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Venta $venta,
        public FacturacionConfig $cfg,
        public ?string $pdfPath = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $numero = $this->venta->comprobanteElectronico() ?? $this->venta->numero;
        $emisor = $this->cfg->razon_social ?: config('app.name');

        return new Envelope(
            subject: "{$this->venta->comprobanteTitulo()} {$numero} - {$emisor}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.comprobante',
            with: ['venta' => $this->venta, 'cfg' => $this->cfg],
        );
    }

    public function attachments(): array
    {
        $adjuntos = [];
        $numero = $this->venta->comprobanteElectronico() ?? $this->venta->numero;

        if ($this->venta->fe_xml_ruta && Storage::disk('facturacion')->exists($this->venta->fe_xml_ruta)) {
            $adjuntos[] = Attachment::fromStorageDisk('facturacion', $this->venta->fe_xml_ruta)
                ->as("{$numero}.xml")->withMime('application/xml');
        }

        if ($this->venta->fe_cdr_ruta && Storage::disk('facturacion')->exists($this->venta->fe_cdr_ruta)) {
            $adjuntos[] = Attachment::fromStorageDisk('facturacion', $this->venta->fe_cdr_ruta)
                ->as("R-{$numero}.zip")->withMime('application/zip');
        }

        if ($this->pdfPath && is_file($this->pdfPath)) {
            $adjuntos[] = Attachment::fromPath($this->pdfPath)
                ->as("{$numero}.pdf")->withMime('application/pdf');
        }

        return $adjuntos;
    }
}
