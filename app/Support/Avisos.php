<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\Venta;

/**
 * Avisos de la campana de la barra superior.
 *
 * Solo cuenta cosas que el sistema sabe de verdad y que se pueden resolver
 * desde una pantalla concreta. Si no hay nada que avisar, devuelve una lista
 * vacía y la campana se muestra apagada: un número inventado es peor que
 * ningún número.
 */
class Avisos
{
    /**
     * @return array{items: array<int, array{texto:string, url:string, tono:string}>, total: int}
     */
    public static function paraLaBarra(): array
    {
        $items = [];

        // --- Inventario: lo que hay que reponer ---
        $bajos = Producto::where('activo', true)->stockBajo()->count();
        if ($bajos > 0) {
            $items[] = [
                'texto' => $bajos === 1
                    ? '1 producto con stock bajo o agotado'
                    : "{$bajos} productos con stock bajo o agotado",
                'url' => route('productos.index', ['estado' => 'bajo']),
                'tono' => 'alerta',
            ];
        }

        // --- SUNAT: comprobantes que no llegaron a destino ---
        $estados = Venta::whereIn('fe_estado', ['PENDIENTE', 'RECHAZADO', 'ERROR'])
            ->selectRaw('fe_estado, COUNT(*) as total')
            ->groupBy('fe_estado')
            ->pluck('total', 'fe_estado');

        $pendientes = (int) ($estados['PENDIENTE'] ?? 0);
        if ($pendientes > 0) {
            $items[] = [
                'texto' => $pendientes === 1
                    ? '1 comprobante pendiente de envío a SUNAT'
                    : "{$pendientes} comprobantes pendientes de envío a SUNAT",
                'url' => route('comprobantes.index', ['estado' => 'PENDIENTE']),
                'tono' => 'info',
            ];
        }

        $problema = (int) ($estados['RECHAZADO'] ?? 0) + (int) ($estados['ERROR'] ?? 0);
        if ($problema > 0) {
            $items[] = [
                'texto' => $problema === 1
                    ? '1 comprobante rechazado o con error'
                    : "{$problema} comprobantes rechazados o con error",
                'url' => route('comprobantes.index', ['estado' => 'PROBLEMA']),
                'tono' => 'grave',
            ];
        }

        return ['items' => $items, 'total' => count($items)];
    }
}
