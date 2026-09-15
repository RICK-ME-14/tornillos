<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Prepara el entorno de Facturación Electrónica:
 *  - Crea las carpetas de almacenamiento (XML, CDR, PDF, resúmenes).
 *  - Genera un certificado digital autofirmado para el ambiente BETA de SUNAT.
 *  - Verifica que las librerías necesarias estén instaladas.
 *
 * Uso:  php artisan facturacion:instalar
 */
class InstalarFacturacion extends Command
{
    protected $signature = 'facturacion:instalar {--force : Regenera el certificado aunque ya exista}';

    protected $description = 'Prepara carpetas y un certificado de prueba para la Facturación Electrónica';

    public function handle(): int
    {
        $this->info('Preparando entorno de Facturación Electrónica...');

        // 1) Carpetas de almacenamiento
        $base = storage_path('app/facturacion');
        foreach (['xml', 'cdr', 'pdf', 'resumen', 'resumen/cdr', 'pe'] as $sub) {
            $dir = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
                $this->line("  creada  storage/app/facturacion/{$sub}");
            }
        }

        // 2) Certificado autofirmado (válido para BETA)
        $certPath = $base . DIRECTORY_SEPARATOR . 'pe' . DIRECTORY_SEPARATOR . 'certificate.pem';
        if (is_file($certPath) && ! $this->option('force')) {
            $this->line('  certificado ya existe (usa --force para regenerarlo)');
        } else {
            if ($this->generarCertificado($certPath)) {
                $this->info("  certificado generado: {$certPath}");
            } else {
                $this->warn('  no se pudo generar el certificado (extensión OpenSSL de PHP no disponible).');
                $this->warn('  Coloca manualmente tu certificado .pem en: ' . $certPath);
            }
        }

        // 3) Verificación de librerías
        $this->newLine();
        $this->line('Librerías:');
        $this->line('  greenter/greenter: ' . (class_exists('\\Greenter\\See') ? 'OK' : 'NO instalada (composer require greenter/greenter)'));
        $this->line('  dompdf/dompdf:     ' . (class_exists('\\Dompdf\\Dompdf') ? 'OK' : 'NO instalada (composer require dompdf/dompdf)'));

        $this->newLine();
        $this->info('Listo. En Configuración → Facturación Electrónica indica la ruta del certificado:');
        $this->line('  ' . $certPath);
        $this->line('Credenciales BETA: RUC 20000000001 · usuario y clave MODDATOS.');

        return self::SUCCESS;
    }

    /** Genera un certificado autofirmado (cert + clave privada) en formato PEM. */
    private function generarCertificado(string $ruta): bool
    {
        if (! function_exists('openssl_pkey_new')) {
            return false;
        }

        try {
            $dn = [
                'countryName' => 'PE',
                'stateOrProvinceName' => 'LIMA',
                'localityName' => 'LIMA',
                'organizationName' => 'MINIMARKET DEMO SAC',
                'commonName' => 'CERTIFICADO DE PRUEBA SUNAT BETA',
            ];

            $config = [
                'digest_alg' => 'sha256',
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ];

            $privKey = openssl_pkey_new($config);
            if ($privKey === false) {
                return false;
            }

            $csr = openssl_csr_new($dn, $privKey, $config);
            $x509 = openssl_csr_sign($csr, null, $privKey, 1825, $config); // 5 años

            $pem = '';
            openssl_x509_export($x509, $certOut);
            openssl_pkey_export($privKey, $keyOut, null, $config);
            $pem = $certOut . $keyOut;

            file_put_contents($ruta, $pem);

            return is_file($ruta) && filesize($ruta) > 0;
        } catch (\Throwable $e) {
            $this->warn('  OpenSSL: ' . $e->getMessage());
            return false;
        }
    }
}
