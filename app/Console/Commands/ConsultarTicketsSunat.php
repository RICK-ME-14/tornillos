<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\FacturacionConfig;
use App\Services\Facturacion\FacturacionManager;
use App\Support\Tenant;
use Illuminate\Console\Command;

/**
 * Consulta en SUNAT el resultado de los envíos que devuelven un *ticket* y se
 * procesan de forma asíncrona: los resúmenes diarios de boletas (RC) y las
 * comunicaciones de baja de facturas (RA).
 *
 * SUNAT no responde estos envíos en el acto —tarda minutos—, así que sin esta
 * consulta posterior las boletas se quedarían PENDIENTE indefinidamente y las
 * bajas en "Enviado" sin que nadie sepa si fueron aceptadas.
 *
 * Uso:
 *   php artisan facturacion:consultar-tickets
 *   php artisan facturacion:consultar-tickets --empresa=5
 */
class ConsultarTicketsSunat extends Command
{
    protected $signature = 'facturacion:consultar-tickets {--empresa= : ID de una empresa específica}';

    protected $description = 'Consulta en SUNAT el resultado de los resúmenes de boletas y las bajas enviadas';

    public function handle(FacturacionManager $manager): int
    {
        $empresas = Empresa::query()
            ->when($this->option('empresa'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        $totalResueltos = 0;

        foreach ($empresas as $empresa) {
            Tenant::withTenant($empresa->getKey(), function () use ($manager, $empresa, &$totalResueltos) {
                if (! FacturacionConfig::actual()->activa()) {
                    return;
                }

                $rc = $manager->consultarResumenesPendientes();
                $ra = $manager->consultarBajasPendientes();

                $totalResueltos += $rc['resueltos'] + $ra['resueltos'];

                if ($rc['total'] > 0) {
                    $this->line("[{$empresa->nombre}] resúmenes: {$rc['resueltos']} resueltos, {$rc['en_proceso']} en proceso de {$rc['total']}.");
                }
                if ($ra['total'] > 0) {
                    $this->line("[{$empresa->nombre}] bajas: {$ra['resueltos']} resueltas, {$ra['en_proceso']} en proceso de {$ra['total']}.");
                }
            });
        }

        $this->info("Consulta de tickets finalizada. Resueltos: {$totalResueltos}.");

        return self::SUCCESS;
    }
}
