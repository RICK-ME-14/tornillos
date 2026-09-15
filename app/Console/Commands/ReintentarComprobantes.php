<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\FacturacionConfig;
use App\Services\Facturacion\FacturacionManager;
use App\Support\Tenant;
use Illuminate\Console\Command;

/**
 * Reenvía a SUNAT los comprobantes electrónicos que quedaron en estado
 * PENDIENTE o ERROR. Recorre cada empresa (tenant) con su propia configuración.
 *
 * Uso:
 *   php artisan facturacion:reintentar
 *   php artisan facturacion:reintentar --empresa=5
 */
class ReintentarComprobantes extends Command
{
    protected $signature = 'facturacion:reintentar {--empresa= : ID de una empresa específica}';

    protected $description = 'Reenvía a SUNAT los comprobantes pendientes o con error';

    public function handle(FacturacionManager $manager): int
    {
        $empresas = Empresa::query()
            ->when($this->option('empresa'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        $totalGlobal = 0;

        foreach ($empresas as $empresa) {
            Tenant::withTenant($empresa->getKey(), function () use ($manager, $empresa, &$totalGlobal) {
                $cfg = FacturacionConfig::actual();

                if (! $cfg->activa()) {
                    return; // FE deshabilitada o sin driver: nada que reenviar
                }

                $res = $manager->reintentarPendientes();
                $totalGlobal += $res['total'];

                if ($res['total'] > 0) {
                    $this->line("[{$empresa->nombre}] {$res['ok']} ok, {$res['fail']} con problemas de {$res['total']}.");
                }
            });
        }

        $this->info("Reintento finalizado. Comprobantes procesados: {$totalGlobal}.");

        return self::SUCCESS;
    }
}
