<?php

namespace App\Services\Facturacion\Drivers;

use App\Models\Empresa;
use App\Models\FacturacionConfig;
use App\Models\Venta;
use App\Services\Facturacion\Contracts\FacturadorDriver;
use App\Services\Facturacion\ResultadoEmision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Driver de emisión electrónica ante SUNAT usando la librería Greenter
 * (UBL 2.1). Genera el XML, lo firma con el certificado digital y lo envía
 * al servicio de SUNAT (Beta u Producción).
 *
 * REQUISITO: instalar la librería en el proyecto:
 *     composer require greenter/greenter
 *
 * Y contar con un certificado digital .pem válido (en Beta se puede usar el
 * certificado de homologación de SUNAT). Mientras Greenter no esté instalado,
 * este driver informa el paso pendiente sin romper el sistema.
 */
class GreenterDriver implements FacturadorDriver
{
    public function nombre(): string
    {
        return 'greenter';
    }

    // ------------------------------------------------------------------

    public function probarConexion(FacturacionConfig $config): ResultadoEmision
    {
        if (! $this->greenterInstalado()) {
            return ResultadoEmision::error($this->mensajeInstalacion());
        }

        if (empty($config->ruc) || empty($config->sol_user) || empty($config->sol_pass)) {
            return ResultadoEmision::error('Faltan credenciales SUNAT (RUC, usuario o clave SOL).');
        }

        if (! $config->certificadoExiste()) {
            return ResultadoEmision::error('No se encontró el certificado (.pem) en la ruta indicada.');
        }

        try {
            $see = $this->construirSee($config);
        } catch (\Throwable $e) {
            // Aquí caen los problemas del certificado (contraseña, formato, ruta).
            return ResultadoEmision::error('No se pudo cargar el certificado: ' . $e->getMessage());
        }

        $produccion = $config->entorno === 'produccion';
        $entorno = $produccion ? 'Producción' : 'Beta (homologación)';

        /*
         | Consulta de un ticket inexistente: es la llamada más inocua que
         | existe contra SUNAT —no envía ni crea ningún comprobante— y basta
         | para saber si el diálogo se establece.
         |
         | Si SUNAT devuelve una respuesta estructurada (aunque sea el error
         | "el ticket no existe"), el circuito completo funciona: red, endpoint,
         | SOAP, certificado y firma. Si en cambio la respuesta no es válida,
         | SUNAT rechazó la conversación antes de llegar a mirar el ticket.
         */
        try {
            $status = $see->getStatus('0000000000000');
        } catch (\Throwable $e) {
            return ResultadoEmision::error(
                "No se pudo establecer la conexión con SUNAT ({$entorno}). "
                . ($produccion
                    ? 'Revisa el RUC, el usuario y la clave SOL, y que el certificado corresponda a ese RUC. '
                    : '')
                . 'Detalle: ' . $e->getMessage()
            );
        }

        $error = $status->getError();
        $codigo = $error ? (string) $error->getCode() : '';

        // Códigos de SUNAT que señalan credenciales, no un problema del ticket.
        if (in_array($codigo, self::CODIGOS_CREDENCIALES, true)) {
            return ResultadoEmision::error(
                "SUNAT rechazó las credenciales ({$codigo}): "
                . ($error->getMessage() ?: 'usuario o clave SOL incorrectos.')
                . ' Recuerda que el usuario debe ir sin el RUC delante.'
            );
        }

        $detalle = $codigo !== '' ? " Respondió con el código {$codigo}, que es el esperado en esta prueba." : '';

        // En Beta, SUNAT contesta igual con credenciales válidas o inválidas,
        // así que no se puede prometer más de lo que realmente se comprobó.
        return ResultadoEmision::ok(
            'ENVIADO',
            $produccion
                ? "Conexión establecida con SUNAT (Producción).{$detalle} El certificado y las credenciales fueron aceptados."
                : "Conexión establecida con SUNAT (Beta).{$detalle} Certificado y firma correctos. "
                    . 'Ten en cuenta que en Beta SUNAT no valida el usuario y la clave SOL: '
                    . 'para confirmarlos, emite un comprobante de prueba.'
        );
    }

    /**
     * Códigos con los que SUNAT indica que el problema está en las credenciales
     * y no en el documento consultado.
     */
    protected const CODIGOS_CREDENCIALES = ['0102', '0103', '0105', '0108', '0109', '0110', '0111', '0112'];

    // ------------------------------------------------------------------

    public function emitir(Venta $venta, FacturacionConfig $config): ResultadoEmision
    {
        if (! $this->greenterInstalado()) {
            // No rompemos la venta: queda pendiente hasta instalar la librería.
            return ResultadoEmision::error(
                $this->mensajeInstalacion() . ' El comprobante quedó PENDIENTE.',
                'PENDIENTE'
            );
        }

        if (! $config->certificadoExiste()) {
            return ResultadoEmision::error(
                'No se encontró el certificado digital. El comprobante quedó PENDIENTE.',
                'PENDIENTE'
            );
        }

        $see = $this->construirSee($config);
        $invoice = $this->construirComprobante($venta, $config);

        // Firma + envío a SUNAT.
        $result = $see->send($invoice);

        // Guarda el XML firmado.
        $xmlRuta = null;
        try {
            $xmlFirmado = $see->getFactory()->getLastXml();
            if ($xmlFirmado) {
                $nombre = $invoice->getName(); // p.ej. 20000000001-03-B001-1
                $xmlRuta = "xml/{$nombre}.xml";
                Storage::disk('facturacion')->put($xmlRuta, $xmlFirmado);
            }
        } catch (\Throwable $e) {
            // El XML es informativo; no bloquea el resultado.
        }

        // El hash (DigestValue) puede extraerse del XML firmado si se requiere.
        $hash = $this->extraerHash($see);

        if (! $result->isSuccess()) {
            $error = $result->getError();
            return ResultadoEmision::error(
                'SUNAT rechazó el envío: ' . ($error ? $error->getCode() . ' - ' . $error->getMessage() : 'desconocido'),
                'RECHAZADO',
                ['xmlRuta' => $xmlRuta]
            );
        }

        // Procesa el CDR (Constancia de Recepción).
        $cdrRuta = null;
        $cdr = $result->getCdrResponse();
        try {
            $zip = $result->getCdrZip();
            if ($zip) {
                $nombre = $invoice->getName();
                $cdrRuta = "cdr/R-{$nombre}.zip";
                Storage::disk('facturacion')->put($cdrRuta, $zip);
            }
        } catch (\Throwable $e) {
            // opcional
        }

        $code = $cdr ? $cdr->getCode() : null;

        if ($code === '0' || $code === 0) {
            return ResultadoEmision::ok(
                'ACEPTADO',
                'Comprobante aceptado por SUNAT.' . ($cdr ? ' ' . $cdr->getDescription() : ''),
                ['cdrRuta' => $cdrRuta, 'xmlRuta' => $xmlRuta, 'hash' => $hash]
            );
        }

        return ResultadoEmision::ok(
            'ENVIADO',
            'Comprobante enviado a SUNAT.' . ($cdr ? ' ' . $cdr->getDescription() : ''),
            ['cdrRuta' => $cdrRuta, 'xmlRuta' => $xmlRuta, 'hash' => $hash]
        );
    }

    /** Extrae el DigestValue (hash) del último XML firmado, si es posible. */
    protected function extraerHash($see): ?string
    {
        try {
            $xml = $see->getFactory()->getLastXml();
            if (! $xml) {
                return null;
            }
            $doc = new \DOMDocument();
            $doc->loadXML($xml);
            $nodos = $doc->getElementsByTagName('DigestValue');
            return $nodos->length ? $nodos->item(0)->nodeValue : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Construcción de objetos Greenter
    // ------------------------------------------------------------------

    /** Crea y configura el objeto See (firmante + endpoint SUNAT). */
    protected function construirSee(FacturacionConfig $config)
    {
        $seeClass = '\\Greenter\\See';
        $endpointsClass = '\\Greenter\\Ws\\Services\\SunatEndpoints';

        /** @var object $see */
        $see = new $seeClass();
        $see->setCertificate($this->cargarCertificado($config));
        $see->setService(
            $config->entorno === 'produccion'
                ? $endpointsClass::FE_PRODUCCION
                : $endpointsClass::FE_BETA
        );
        $see->setClaveSOL($config->ruc, $config->sol_user, $config->sol_pass);

        return $see;
    }

    /**
     * Devuelve el certificado en el PEM sin cifrar que espera Greenter.
     *
     * Acepta los tres formatos con los que llega en la práctica:
     *  - PKCS#12 (.pfx / .p12), que es lo que entregan las entidades
     *    certificadoras, abierto con la clave del certificado;
     *  - PEM con la clave privada cifrada, descifrado con esa misma clave;
     *  - PEM ya sin cifrar, que se usa tal cual.
     *
     * @throws \RuntimeException si el archivo no se puede abrir o la clave no corresponde.
     */
    protected function cargarCertificado(FacturacionConfig $config): string
    {
        $contenido = @file_get_contents($config->certificado_ruta);

        if ($contenido === false || $contenido === '') {
            throw new \RuntimeException('No se pudo leer el archivo del certificado digital.');
        }

        $clave = (string) ($config->certificado_pass ?? '');

        // PKCS#12: binario, no empieza por "-----BEGIN".
        if (! str_contains($contenido, '-----BEGIN')) {
            $certs = [];
            if (! openssl_pkcs12_read($contenido, $certs, $clave)) {
                throw new \RuntimeException(
                    'No se pudo abrir el certificado .pfx/.p12. Verifica que la contraseña del '
                    . 'certificado sea la correcta.'
                );
            }

            return $certs['cert'] . $certs['pkey'];
        }

        $cifrada = str_contains($contenido, 'ENCRYPTED');

        // Nunca se llama a OpenSSL con contraseña vacía sobre una clave cifrada:
        // en ese caso abre un prompt interactivo y la petición se queda colgada
        // en lugar de devolver un error.
        if ($cifrada && $clave === '') {
            throw new \RuntimeException(
                'La clave privada del certificado .pem está protegida por contraseña. '
                . 'Ingrésala en la configuración de Facturación Electrónica.'
            );
        }

        $privada = openssl_pkey_get_private($contenido, $cifrada ? $clave : null);

        if ($privada === false) {
            throw new \RuntimeException(
                'No se pudo leer la clave privada del certificado .pem. Si está protegida por '
                . 'contraseña, ingrésala en la configuración; si no, verifica que el archivo '
                . 'contenga el certificado y su clave privada.'
            );
        }

        // Si la clave no está cifrada, el archivo ya sirve tal cual: se devuelve
        // sin tocarlo. Reexportarlo sería peor, porque openssl_pkey_export
        // necesita un openssl.cnf que muchos servidores no tienen configurado y
        // falla en silencio devolviendo una clave vacía.
        if (! $cifrada) {
            return $contenido;
        }

        // Clave cifrada: hay que reexportarla sin cifrar para que Greenter firme.
        $keyOut = '';
        if (! openssl_pkey_export($privada, $keyOut) || $keyOut === '') {
            throw new \RuntimeException(
                'La clave privada del .pem está cifrada y este servidor no puede desencriptarla '
                . '(falta la configuración de OpenSSL). Usa el archivo .pfx/.p12 original, o '
                . 'convierte el certificado a un .pem sin contraseña con: '
                . 'openssl pkcs12 -in certificado.pfx -out certificado.pem -nodes'
            );
        }

        $publica = openssl_x509_read($contenido);
        $certOut = '';
        if ($publica === false || ! openssl_x509_export($publica, $certOut)) {
            throw new \RuntimeException('El archivo .pem no contiene un certificado X.509 válido.');
        }

        return $certOut . $keyOut;
    }

    /** Arma el comprobante (Invoice) a partir de la venta. */
    protected function construirComprobante(Venta $venta, FacturacionConfig $config)
    {
        $empresa = Empresa::actual();
        $tasaIgv = $empresa->tasaIgv(); // p.ej. 0.18
        $esFactura = strtoupper($venta->tipo_comprobante) === 'FACTURA';

        $address = (new \Greenter\Model\Company\Address())
            ->setUbigueo($config->ubigeo ?: '150101')
            ->setDepartamento($config->departamento ?: 'LIMA')
            ->setProvincia($config->provincia ?: 'LIMA')
            ->setDistrito($config->distrito ?: 'LIMA')
            ->setDireccion($config->direccion_fiscal ?: '-');

        $company = (new \Greenter\Model\Company\Company())
            ->setRuc($config->ruc)
            ->setRazonSocial($config->razon_social)
            ->setNombreComercial($config->nombre_comercial ?: $config->razon_social)
            ->setAddress($address);

        $client = $this->client($venta, $esFactura);

        // Detalle (con prorrateo del descuento global para cuadrar con la venta).
        $r = $this->construirDetalles($venta, $tasaIgv);
        $detalles = $r['detalles'];
        $gravadas = $r['gravadas'];
        $igvTotal = $r['igv'];
        $total = $r['total'];

        $legend = (new \Greenter\Model\Sale\Legend())
            ->setCode('1000')
            ->setValue(\App\Support\NumeroALetras::moneda($total, $empresa->moneda));

        $tipoDoc = $esFactura ? '01' : '03';

        $invoice = (new \Greenter\Model\Sale\Invoice())
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101') // Venta interna
            ->setTipoDoc($tipoDoc)
            ->setSerie($venta->fe_serie)
            ->setCorrelativo((string) $venta->fe_correlativo)
            // Fecha real de la operación, no la del envío: un pendiente que se
            // reenvía días después debe conservar la fecha en que se vendió.
            ->setFechaEmision($venta->created_at ? $venta->created_at->toDate() : new \DateTime())
            ->setTipoMoneda('PEN')
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas($gravadas)
            ->setMtoOperExoneradas($r['exoneradas'])
            ->setMtoOperInafectas($r['inafectas'])
            ->setMtoIGV($igvTotal)
            ->setTotalImpuestos($igvTotal)
            ->setValorVenta(round($gravadas + $r['exoneradas'] + $r['inafectas'], 2))
            ->setSubTotal($total)
            ->setMtoImpVenta($total)
            // SUNAT exige el bloque Forma de Pago en UBL 2.1. El POS cobra
            // siempre al momento (efectivo, tarjeta, transferencia o Yape),
            // así que todo comprobante sale al contado.
            ->setFormaPago(new \Greenter\Model\Sale\FormaPagos\FormaPagoContado())
            ->setDetails($detalles)
            ->setLegends([$legend]);

        return $invoice;
    }

    // ------------------------------------------------------------------

    /**
     * ¿Está el entorno listo para emitir? Además de la librería hace falta la
     * extensión SOAP de PHP: Greenter habla con SUNAT por SOAP y su cliente
     * extiende \SoapClient, así que sin la extensión la emisión revienta con un
     * error fatal en vez de dar un mensaje entendible.
     */
    protected function greenterInstalado(): bool
    {
        return class_exists('\\Greenter\\See') && class_exists('\\SoapClient');
    }

    protected function mensajeInstalacion(): string
    {
        if (! class_exists('\\Greenter\\See')) {
            return 'La librería Greenter no está instalada. Ejecuta "composer require greenter/greenter" en el servidor para habilitar la emisión ante SUNAT.';
        }

        return 'Falta la extensión SOAP de PHP, necesaria para comunicarse con SUNAT. '
            . 'Habilita "extension=soap" en el php.ini del servidor y reinicia el servicio web.';
    }

    // ==================================================================
    // Anulación electrónica
    // ==================================================================

    /**
     * Anula un comprobante ya aceptado:
     *  - FACTURA -> Comunicación de Baja (RA). Devuelve ticket de SUNAT.
     *  - BOLETA  -> Nota de Crédito (07) con motivo "01" (anulación).
     */
    public function anular(Venta $venta, string $motivo, FacturacionConfig $config): ResultadoEmision
    {
        if (! $this->greenterInstalado()) {
            return ResultadoEmision::error($this->mensajeInstalacion(), 'PENDIENTE');
        }
        if (! $config->certificadoExiste()) {
            return ResultadoEmision::error('No se encontró el certificado digital.', 'PENDIENTE');
        }

        return strtoupper($venta->tipo_comprobante) === 'FACTURA'
            ? $this->comunicacionBaja($venta, $motivo, $config)
            : $this->notaCredito($venta, $motivo, $config);
    }

    /** Comunicación de baja de una factura (documento RA). */
    protected function comunicacionBaja(Venta $venta, string $motivo, FacturacionConfig $config): ResultadoEmision
    {
        $see = $this->construirSee($config);

        $fecComunicacion = new \DateTime();
        $correlativo = $this->reservarCorrelativoBaja($venta, $config, $fecComunicacion);

        $baja = (new \Greenter\Model\Voided\Voided())
            ->setCorrelativo((string) $correlativo)
            ->setFecComunicacion($fecComunicacion)
            ->setFecGeneracion($venta->created_at ? $venta->created_at->toDate() : new \DateTime())
            ->setCompany($this->company($config))
            ->setDetails([
                (new \Greenter\Model\Voided\VoidedDetail())
                    ->setTipoDoc('01')
                    ->setSerie($venta->fe_serie)
                    ->setCorrelativo((string) $venta->fe_correlativo)
                    ->setDesMotivoBaja($motivo ?: 'ANULACION DE LA OPERACION'),
            ]);

        $result = $see->send($baja);

        if (! $result->isSuccess()) {
            $e = $result->getError();
            return ResultadoEmision::error(
                'SUNAT rechazó la baja: ' . ($e ? $e->getCode() . ' - ' . $e->getMessage() : 'desconocido'),
                'ERROR'
            );
        }

        return ResultadoEmision::ok(
            'ENVIADO',
            'Comunicación de baja enviada a SUNAT.',
            ['ticket' => $result->getTicket()]
        );
    }

    /** Nota de crédito (tipo 07) por anulación de la operación. */
    protected function notaCredito(Venta $venta, string $motivo, FacturacionConfig $config): ResultadoEmision
    {
        $empresa = Empresa::actual();
        $tasaIgv = $empresa->tasaIgv();
        $serie = $config->serieNotaCreditoDe($venta->tipo_comprobante);
        $correlativo = $this->reservarCorrelativoNc($venta, $config, $serie);

        // Detalle: se replican las líneas del comprobante original (con su descuento).
        $r = $this->construirDetalles($venta, $tasaIgv);
        $detalles = $r['detalles'];
        $gravadas = $r['gravadas'];
        $igvTotal = $r['igv'];
        $total = $r['total'];

        $legend = (new \Greenter\Model\Sale\Legend())
            ->setCode('1000')
            ->setValue(\App\Support\NumeroALetras::moneda($total, $empresa->moneda));

        $client = $this->client($venta, strtoupper($venta->tipo_comprobante) === 'FACTURA');

        $note = (new \Greenter\Model\Sale\Note())
            ->setUblVersion('2.1')
            ->setTipoDoc('07')                     // Nota de crédito
            ->setSerie($serie)
            ->setCorrelativo((string) $correlativo)
            ->setFechaEmision(new \DateTime())
            ->setTipDocAfectado(strtoupper($venta->tipo_comprobante) === 'FACTURA' ? '01' : '03')
            ->setNumDocfectado($venta->fe_serie . '-' . $venta->fe_correlativo)
            ->setCodMotivo('01')                   // Anulación de la operación
            ->setDesMotivo($motivo ?: 'ANULACION DE LA OPERACION')
            ->setTipoMoneda('PEN')
            ->setCompany($this->company($config))
            ->setClient($client)
            ->setMtoOperGravadas($gravadas)
            ->setMtoOperExoneradas($r['exoneradas'])
            ->setMtoOperInafectas($r['inafectas'])
            ->setMtoIGV($igvTotal)
            ->setTotalImpuestos($igvTotal)
            ->setMtoImpVenta($total)
            ->setDetails($detalles)
            ->setLegends([$legend]);

        $see = $this->construirSee($config);
        $result = $see->send($note);

        $xmlRuta = null;
        try {
            $xml = $see->getFactory()->getLastXml();
            if ($xml) {
                $xmlRuta = "xml/{$note->getName()}.xml";
                Storage::disk('facturacion')->put($xmlRuta, $xml);
            }
        } catch (\Throwable $e) {
        }

        if (! $result->isSuccess()) {
            $e = $result->getError();
            return ResultadoEmision::error(
                'SUNAT rechazó la nota de crédito: ' . ($e ? $e->getCode() . ' - ' . $e->getMessage() : 'desconocido'),
                'ERROR',
                ['serie' => $serie, 'correlativo' => $correlativo, 'xmlRuta' => $xmlRuta]
            );
        }

        $cdrRuta = null;
        try {
            $zip = $result->getCdrZip();
            if ($zip) {
                $cdrRuta = "cdr/R-{$note->getName()}.zip";
                Storage::disk('facturacion')->put($cdrRuta, $zip);
            }
        } catch (\Throwable $e) {
        }

        $cdr = $result->getCdrResponse();
        $code = $cdr ? $cdr->getCode() : null;
        $estado = ($code === '0' || $code === 0) ? 'ACEPTADO' : 'ENVIADO';

        return ResultadoEmision::ok(
            $estado,
            'Nota de crédito ' . ($estado === 'ACEPTADO' ? 'aceptada' : 'enviada') . '.'
                . ($cdr ? ' ' . $cdr->getDescription() : ''),
            ['serie' => $serie, 'correlativo' => $correlativo, 'xmlRuta' => $xmlRuta, 'cdrRuta' => $cdrRuta, 'hash' => $this->extraerHash($see)]
        );
    }

    /**
     * Construye las líneas del comprobante prorrateando el descuento global de
     * la venta para que el total del XML coincida exactamente con la venta
     * registrada (base = subtotal - descuento; IGV = venta->impuesto).
     *
     * Cada línea lleva su propia afectación al IGV (catálogo 07): solo las
     * gravadas ('10') pagan impuesto; exoneradas ('20') e inafectas ('30')
     * suman a su propio total sin IGV.
     *
     * @return array{detalles: array, gravadas: float, exoneradas: float, inafectas: float, igv: float, total: float}
     */
    protected function construirDetalles(Venta $venta, float $tasaIgv): array
    {
        // El reparto de la base por afectación vive en el modelo, para que el
        // XML y la representación impresa no puedan discrepar.
        $desglose = $venta->desgloseAfectacion();
        $lineas = $desglose['lineas'];
        $igvObjetivo = round((float) $venta->impuesto, 2);

        // Índice de la última línea gravada: es la que absorbe el residuo del
        // IGV, porque las no gravadas no pueden llevar impuesto.
        $ultimaGravada = null;
        foreach ($lineas as $i => $l) {
            if ($l['afectacion'] === '10') {
                $ultimaGravada = $i;
            }
        }

        $detalles = [];
        $sumIgv = 0.0;

        foreach ($lineas as $i => $l) {
            $esGravado = $l['afectacion'] === '10';
            $valorVenta = $l['base'];

            $igv = $esGravado ? round($valorVenta * $tasaIgv, 2) : 0.0;
            if ($esGravado && $i === $ultimaGravada) {
                $igv = round($igvObjetivo - $sumIgv, 2);
            }
            $sumIgv = round($sumIgv + $igv, 2);

            $cant = (float) $l['detalle']->cantidad ?: 1;
            $valorUnit = round($valorVenta / $cant, 6);
            $precioUnit = round(($valorVenta + $igv) / $cant, 6);

            $detalles[] = (new \Greenter\Model\Sale\SaleDetail())
                ->setCodProducto((string) $l['detalle']->producto_id)
                ->setUnidad($l['detalle']->unidad_sunat ?: 'NIU')
                ->setCantidad($cant)
                ->setDescripcion($l['detalle']->descripcion)
                ->setMtoBaseIgv($valorVenta)
                ->setPorcentajeIgv($esGravado ? $tasaIgv * 100 : 0)
                ->setIgv($igv)
                ->setTipAfeIgv($l['afectacion'])
                ->setTotalImpuestos($igv)
                ->setMtoValorVenta($valorVenta)
                ->setMtoValorUnitario($valorUnit)
                ->setMtoPrecioUnitario($precioUnit);
        }

        $igvTotal = round($sumIgv, 2);

        return [
            'detalles' => $detalles,
            'gravadas' => $desglose['gravadas'],
            'exoneradas' => $desglose['exoneradas'],
            'inafectas' => $desglose['inafectas'],
            'igv' => $igvTotal,
            'total' => round($desglose['base'] + $igvTotal, 2),
        ];
    }

    /** Company reutilizable para baja / nota de crédito. */
    protected function company(FacturacionConfig $config)
    {
        $address = (new \Greenter\Model\Company\Address())
            ->setUbigueo($config->ubigeo ?: '150101')
            ->setDepartamento($config->departamento ?: 'LIMA')
            ->setProvincia($config->provincia ?: 'LIMA')
            ->setDistrito($config->distrito ?: 'LIMA')
            ->setDireccion($config->direccion_fiscal ?: '-');

        return (new \Greenter\Model\Company\Company())
            ->setRuc($config->ruc)
            ->setRazonSocial($config->razon_social)
            ->setNombreComercial($config->nombre_comercial ?: $config->razon_social)
            ->setAddress($address);
    }

    /**
     * Client reutilizable. En factura el receptor debe ser un RUC de 11 dígitos
     * (el Manager ya lo valida antes de emitir); en boleta se admite DNI y, si
     * no hay documento, el consumidor final genérico.
     */
    protected function client(Venta $venta, bool $esFactura)
    {
        $cliente = $venta->cliente;
        $doc = preg_replace('/\D/', '', (string) ($cliente->numero_documento ?? ''));

        if ($esFactura) {
            $tipoDoc = '6';
            $numDoc = strlen($doc) === 11 ? $doc : '00000000000';
        } else {
            // Boleta: RUC si lo tiene, DNI si son 8 dígitos, si no consumidor final.
            $tipoDoc = strlen($doc) === 11 ? '6' : '1';
            $numDoc = strlen($doc) === 11 || strlen($doc) === 8 ? $doc : '00000000';
        }

        return (new \Greenter\Model\Client\Client())
            ->setTipoDoc($tipoDoc)
            ->setNumDoc($numDoc)
            ->setRznSocial($cliente->nombre ?? 'CLIENTE VARIOS');
    }

    /**
     * Reserva y persiste el correlativo de la nota de crédito antes de enviarla.
     * Igual que en la emisión: el bloqueo sobre la configuración serializa la
     * numeración y el guardado previo evita que dos anulaciones simultáneas
     * lleguen a SUNAT con el mismo número.
     */
    protected function reservarCorrelativoNc(Venta $venta, FacturacionConfig $config, string $serie): int
    {
        if ($venta->fe_nc_serie === $serie && $venta->fe_nc_correlativo) {
            return (int) $venta->fe_nc_correlativo; // Reintento: mismo número.
        }

        return DB::transaction(function () use ($venta, $config, $serie) {
            DB::table('facturacion_configs')
                ->where('id', $config->getKey())
                ->lockForUpdate()
                ->first();

            $ultimo = Venta::where('empresa_id', $config->empresa_id)
                ->where('fe_nc_serie', $serie)
                ->max('fe_nc_correlativo');

            $correlativo = ((int) $ultimo) + 1;

            $venta->forceFill([
                'fe_nc_serie' => $serie,
                'fe_nc_correlativo' => $correlativo,
            ])->save();

            return $correlativo;
        });
    }

    /**
     * Reserva el correlativo diario de la Comunicación de Baja (RA). SUNAT
     * identifica cada RA como RA-YYYYMMDD-correlativo, así que dos bajas del
     * mismo día necesitan correlativos distintos o la segunda es rechazada.
     */
    protected function reservarCorrelativoBaja(Venta $venta, FacturacionConfig $config, \DateTimeInterface $fecha): int
    {
        if ($venta->fe_baja_correlativo && $venta->fe_baja_fecha
            && $venta->fe_baja_fecha->isSameDay($fecha)) {
            return (int) $venta->fe_baja_correlativo; // Reintento del mismo día.
        }

        return DB::transaction(function () use ($venta, $config, $fecha) {
            DB::table('facturacion_configs')
                ->where('id', $config->getKey())
                ->lockForUpdate()
                ->first();

            $ultimo = Venta::where('empresa_id', $config->empresa_id)
                ->whereDate('fe_baja_fecha', $fecha->format('Y-m-d'))
                ->max('fe_baja_correlativo');

            $correlativo = ((int) $ultimo) + 1;

            $venta->forceFill([
                'fe_baja_correlativo' => $correlativo,
                'fe_baja_fecha' => $fecha->format('Y-m-d'),
            ])->save();

            return $correlativo;
        });
    }

    // ==================================================================
    // Resumen diario de boletas (RC)
    // ==================================================================

    /**
     * Envía a SUNAT el resumen diario de boletas. Devuelve el ticket para
     * consultar luego el resultado (SUNAT procesa de forma asíncrona).
     *
     * @param  iterable  $boletas  Ventas tipo BOLETA a resumir.
     */
    public function enviarResumenBoletas(
        iterable $boletas,
        \DateTimeInterface $fechaReferencia,
        \DateTimeInterface $fechaGeneracion,
        int $correlativo,
        FacturacionConfig $config
    ): ResultadoEmision {
        if (! $this->greenterInstalado()) {
            return ResultadoEmision::error($this->mensajeInstalacion(), 'PENDIENTE');
        }
        if (! $config->certificadoExiste()) {
            return ResultadoEmision::error('No se encontró el certificado digital.', 'PENDIENTE');
        }

        $empresa = Empresa::actual();
        $tasaIgv = $empresa->tasaIgv();

        $detalles = [];
        foreach ($boletas as $venta) {
            // Mismo desglose por afectación que en la emisión individual.
            $r = $this->construirDetalles($venta, $tasaIgv);
            $igv = $r['igv'];
            $total = round((float) $venta->total, 2);

            // Estado: 1 = Adicionar, 3 = Anular (boleta ya informada y anulada).
            $estado = $venta->estado === 'ANULADA' ? '3' : '1';

            // Documento real del cliente (RUC 11 dígitos -> tipo 6; si no, DNI).
            $doc = $venta->cliente->numero_documento ?? '';
            $tipoDocCli = strlen($doc) === 11 ? '6' : '1';
            $numDocCli = $doc !== '' ? $doc : '00000000';

            $detalles[] = (new \Greenter\Model\Summary\SummaryDetail())
                ->setTipoDoc('03')
                ->setSerieNro($venta->fe_serie . '-' . $venta->fe_correlativo)
                ->setEstado($estado)
                ->setClienteTipo($tipoDocCli)
                ->setClienteNro($numDocCli)
                ->setTotal($total)
                ->setMtoOperGravadas($r['gravadas'])
                ->setMtoOperExoneradas($r['exoneradas'])
                ->setMtoOperInafectas($r['inafectas'])
                ->setMtoIGV($igv);
        }

        // Greenter arma el ID como RC-{fecResumen}-{correlativo}, es decir con
        // la fecha de generación: el correlativo se lleva por ese día, no por
        // el día de las boletas resumidas.
        $summary = (new \Greenter\Model\Summary\Summary())
            ->setFecGeneracion($fechaReferencia)
            ->setFecResumen($fechaGeneracion)
            ->setCorrelativo((string) $correlativo)
            ->setCompany($this->company($config))
            ->setDetails($detalles);

        // Greenter arma internamente el ID (RC-YYYYMMDD-correlativo).
        $see = $this->construirSee($config);
        $result = $see->send($summary);

        $xmlRuta = null;
        try {
            $xml = $see->getFactory()->getLastXml();
            if ($xml) {
                $xmlRuta = "resumen/{$summary->getName()}.xml";
                Storage::disk('facturacion')->put($xmlRuta, $xml);
            }
        } catch (\Throwable $e) {
        }

        if (! $result->isSuccess()) {
            $e = $result->getError();
            return ResultadoEmision::error(
                'SUNAT rechazó el resumen: ' . ($e ? $e->getCode() . ' - ' . $e->getMessage() : 'desconocido'),
                'ERROR',
                ['xmlRuta' => $xmlRuta]
            );
        }

        return ResultadoEmision::ok(
            'ENVIADO',
            'Resumen enviado. Ticket ' . $result->getTicket() . '. Consulte el resultado en unos minutos.',
            ['ticket' => $result->getTicket(), 'xmlRuta' => $xmlRuta]
        );
    }

    /** Consulta a SUNAT el estado de un resumen previamente enviado (por ticket). */
    public function consultarTicket(string $ticket, FacturacionConfig $config, string $carpeta = 'resumen/cdr'): ResultadoEmision
    {
        if (! $this->greenterInstalado()) {
            return ResultadoEmision::error($this->mensajeInstalacion(), 'ENVIADO');
        }

        $see = $this->construirSee($config);
        $status = $see->getStatus($ticket);

        if (! $status->isSuccess()) {
            $e = $status->getError();
            return ResultadoEmision::error(
                'Resumen aún en proceso o con error: ' . ($e ? $e->getCode() . ' - ' . $e->getMessage() : ''),
                'ENVIADO'
            );
        }

        $cdrRuta = null;
        try {
            $zip = $status->getCdrZip();
            if ($zip) {
                $cdrRuta = "{$carpeta}/R-{$ticket}.zip";
                Storage::disk('facturacion')->put($cdrRuta, $zip);
            }
        } catch (\Throwable $e) {
        }

        $cdr = $status->getCdrResponse();
        $code = $cdr ? $cdr->getCode() : null;
        $estado = ($code === '0' || $code === 0) ? 'ACEPTADO' : 'RECHAZADO';

        return ResultadoEmision::ok(
            $estado,
            'Resumen ' . ($estado === 'ACEPTADO' ? 'aceptado' : 'rechazado') . ' por SUNAT.'
                . ($cdr ? ' ' . $cdr->getDescription() : ''),
            ['cdrRuta' => $cdrRuta]
        );
    }
}
