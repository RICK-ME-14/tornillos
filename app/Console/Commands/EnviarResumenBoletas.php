<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\FacturacionConfig;
use App\Services\Facturacion\FacturacionManager;
use App\Support\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Genera y envía a SUNAT el resumen diario de boletas (RC) de cada empresa.
 * Por defecto procesa el día anterior.
 *
 * Uso:
 *   php artisan facturacion:resumen-boletas
 *   php artisan facturacion:resumen-boletas --fecha=2026-08-10
 *   php artisan facturacion:resumen-boletas --empresa=5
 */
class EnviarResumenBoletas extends Command
{
    protected $signature = 'facturacion:resumen-boletas {--fecha= : Fecha (Y-m-d) a resumir} {--empresa= : ID de una empresa}';

    protected $description = 'Envía a SUNAT el resumen diario de boletas de cada empresa';

    public function handle(FacturacionManager $manager): int
    {
        $fecha = $this->option('fecha') ? Carbon::parse($this->option('fecha')) : null;

        $empresas = Empresa::query()
            ->when($this->option('empresa'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        foreach ($empresas as $empresa) {
            Tenant::withTenant($empresa->getKey(), function () use ($manager, $empresa, $fecha) {
                if (! FacturacionConfig::actual()->activa()) {
                    return;
                }

                $res = $manager->enviarResumenDiario($fecha);
                $this->line("[{$empresa->nombre}] {$res['mensaje']}");
            });
        }

        $this->info('Resumen de boletas finalizado.');

        return self::SUCCESS;
    }
}
