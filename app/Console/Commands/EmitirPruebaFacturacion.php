<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\FacturacionConfig;
use App\Support\Tenant;
use Illuminate\Console\Command;

/**
 * Envía una boleta de PRUEBA al ambiente Beta de SUNAT usando la configuración
 * de una empresa. Valida de extremo a extremo: certificado, credenciales,
 * firma, envío y CDR. No toca la base de datos de ventas.
 *
 * Uso:  php artisan facturacion:emitir-prueba --empresa=1
 */
class EmitirPruebaFacturacion extends Command
{
    protected $signature = 'facturacion:emitir-prueba {--empresa= : ID de la empresa (por defecto la primera)}';

    protected $description = 'Envía una boleta de prueba a SUNAT Beta para validar la configuración';

    public function handle(): int
    {
        if (! class_exists('\\Greenter\\See')) {
            $this->error('Greenter no está instalado. Ejecuta: composer require greenter/greenter');
            return self::FAILURE;
        }

        $empresa = Empresa::query()
            ->when($this->option('empresa'), fn ($q, $id) => $q->whereKey($id))
            ->first();

        if (! $empresa) {
            $this->error('No se encontró la empresa.');
            return self::FAILURE;
        }

        return Tenant::withTenant($empresa->getKey(), function () {
            $cfg = FacturacionConfig::actual();

            if (! $cfg->certificadoExiste()) {
                $this->error('No se encontró el certificado en: ' . $cfg->certificado_ruta);
                $this->line('Ejecuta primero: php artisan facturacion:instalar');
                return self::FAILURE;
            }
            if (empty($cfg->ruc) || empty($cfg->sol_user) || empty($cfg->sol_pass)) {
                $this->error('Faltan credenciales SUNAT (RUC, usuario o clave SOL) en la configuración.');
                return self::FAILURE;
            }

            $this->info("Emisor: {$cfg->razon_social} (RUC {$cfg->ruc}) · Entorno: {$cfg->entorno}");

            try {
                $see = new \Greenter\See();
                $see->setCertificate(file_get_contents($cfg->certificado_ruta));
                $see->setService(
                    $cfg->entorno === 'produccion'
                        ? \Greenter\Ws\Services\SunatEndpoints::FE_PRODUCCION
                        : \Greenter\Ws\Services\SunatEndpoints::FE_BETA
                );
                $see->setClaveSOL($cfg->ruc, $cfg->sol_user, $cfg->sol_pass);

                $invoice = $this->boletaEjemplo($cfg);

                $this->line('Enviando ' . $invoice->getName() . ' a SUNAT...');
                $result = $see->send($invoice);

                if (! $result->isSuccess()) {
                    $e = $result->getError();
                    $this->error('SUNAT rechazó: ' . ($e ? $e->getCode() . ' - ' . $e->getMessage() : 'desconocido'));
                    return self::FAILURE;
                }

                $cdr = $result->getCdrResponse();
                $this->info('OK · CDR código ' . ($cdr ? $cdr->getCode() : '?') . ' - ' . ($cdr ? $cdr->getDescription() : ''));
                $this->line('¡La configuración funciona correctamente!');

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error('Error: ' . $e->getMessage());
                return self::FAILURE;
            }
        });
    }

    /** Construye una boleta de ejemplo mínima válida. */
    private function boletaEjemplo(FacturacionConfig $cfg)
    {
        $address = (new \Greenter\Model\Company\Address())
            ->setUbigueo($cfg->ubigeo ?: '150101')
            ->setDepartamento($cfg->departamento ?: 'LIMA')
            ->setProvincia($cfg->provincia ?: 'LIMA')
            ->setDistrito($cfg->distrito ?: 'LIMA')
            ->setDireccion($cfg->direccion_fiscal ?: 'AV. PRINCIPAL 123');

        $company = (new \Greenter\Model\Company\Company())
            ->setRuc($cfg->ruc)
            ->setRazonSocial($cfg->razon_social ?: 'EMPRESA DEMO')
            ->setNombreComercial($cfg->nombre_comercial ?: 'DEMO')
            ->setAddress($address);

        $client = (new \Greenter\Model\Client\Client())
            ->setTipoDoc('1')
            ->setNumDoc('12345678')
            ->setRznSocial('CLIENTE DE PRUEBA');

        $item = (new \Greenter\Model\Sale\SaleDetail())
            ->setCodProducto('P001')
            ->setUnidad('NIU')
            ->setCantidad(1)
            ->setDescripcion('PRODUCTO DE PRUEBA')
            ->setMtoBaseIgv(100.00)
            ->setPorcentajeIgv(18.00)
            ->setIgv(18.00)
            ->setTipAfeIgv('10')
            ->setTotalImpuestos(18.00)
            ->setMtoValorVenta(100.00)
            ->setMtoValorUnitario(100.00)
            ->setMtoPrecioUnitario(118.00);

        $legend = (new \Greenter\Model\Sale\Legend())
            ->setCode('1000')
            ->setValue(\App\Support\NumeroALetras::moneda(118.00, 'S/'));

        // Correlativo alto para no chocar con envíos reales.
        $correlativo = (string) random_int(50000, 99999);

        return (new \Greenter\Model\Sale\Invoice())
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101')
            ->setTipoDoc('03')
            ->setSerie($cfg->serie_boleta ?: 'B001')
            ->setCorrelativo($correlativo)
            ->setFechaEmision(new \DateTime())
            ->setTipoMoneda('PEN')
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas(100.00)
            ->setMtoIGV(18.00)
            ->setTotalImpuestos(18.00)
            ->setValorVenta(100.00)
            ->setSubTotal(118.00)
            ->setMtoImpVenta(118.00)
            ->setDetails([$item])
            ->setLegends([$legend]);
    }
}
