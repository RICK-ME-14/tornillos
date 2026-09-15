<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Empresa;
use App\Models\Marca;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Support\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeder de demostración para poblar el DASHBOARD.
 *
 * Agrega ~10 registros a cada módulo del sistema (categorías, marcas,
 * proveedores, clientes, productos, compras, ventas y movimientos de
 * inventario) usando FECHAS RELATIVAS (Carbon::today()). Esto garantiza
 * que el gráfico "Ventas últimos 14 días" siempre tenga datos, sin importar
 * el día en que se ejecute el seeder.
 *
 * Se puede ejecutar varias veces sin romper nada: usa numeración con
 * marca de tiempo y stock controlado.
 *
 * Uso:
 *   php artisan db:seed --class=DemoDashboardSeeder
 */
class DemoDashboardSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Empresa demo existente (tenant activo)
        $empresa = Empresa::where('nombre', 'Mi Negocio Demo')->first()
            ?? Empresa::orderBy('id')->first();

        if (! $empresa) {
            $this->command->error('No existe ninguna empresa. Ejecuta primero: php artisan migrate:fresh --seed');
            return;
        }

        Tenant::set($empresa->id);

        $admin = User::where('rol', 'admin')->orderBy('id')->first()
            ?? User::orderBy('id')->first();

        // Sufijo único para evitar choques de "numero" / "codigo" al re-ejecutar
        $sfx = now()->format('ymdHis');

        $this->command->info("Poblando la empresa: {$empresa->nombre}");

        // ================================================================
        // 2) CATEGORÍAS (10)
        // ================================================================
        $catNombres = [
            'Cuidado Personal', 'Congelados', 'Panadería', 'Mascotas', 'Ferretería',
            'Papelería', 'Frutas y Verduras', 'Carnes', 'Electrónica', 'Juguetería',
        ];
        $categorias = collect($catNombres)->map(fn ($n) => Categoria::firstOrCreate(
            ['empresa_id' => $empresa->id, 'nombre' => $n],
            ['activo' => true]
        ));
        // incluir también las categorías previas para repartir productos/ventas
        $todasCategorias = Categoria::all();

        // ================================================================
        // 3) MARCAS (10)
        // ================================================================
        $marcaNombres = [
            'Alicorp', 'P&G', 'Unilever', 'San Fernando', 'Laive',
            'Backus', 'Molitalia', 'Colgate', 'Kimberly', 'Nescafé',
        ];
        $marcas = collect($marcaNombres)->map(fn ($n) => Marca::firstOrCreate(
            ['empresa_id' => $empresa->id, 'nombre' => $n],
            ['activo' => true]
        ));
        $todasMarcas = Marca::all();

        // ================================================================
        // 4) PROVEEDORES (10)
        // ================================================================
        $proveedores = collect([
            ['Distribuidora Norteña SAC', '20100200301', '01-2001001'],
            ['Comercial El Águila EIRL', '20100200302', '01-2001002'],
            ['Importaciones Perú SA', '20100200303', '01-2001003'],
            ['Mayorista Los Andes', '20100200304', '01-2001004'],
            ['Abarrotes del Sur SAC', '20100200305', '01-2001005'],
            ['Grupo Comercial Lima', '20100200306', '01-2001006'],
            ['Distribuidora Pacífico', '20100200307', '01-2001007'],
            ['Almacenes Unidos EIRL', '20100200308', '01-2001008'],
            ['Corporación Andina SAC', '20100200309', '01-2001009'],
            ['Proveedora Central', '20100200310', '01-2001010'],
        ])->map(fn ($p) => Proveedor::firstOrCreate(
            ['empresa_id' => $empresa->id, 'ruc' => $p[1]],
            ['nombre' => $p[0], 'telefono' => $p[2], 'activo' => true]
        ));

        // ================================================================
        // 5) CLIENTES (10)
        // ================================================================
        $clientesData = [
            ['Rosa Gutiérrez', 'DNI', '41205060'],
            ['Luis Fernández', 'DNI', '42305061'],
            ['Minimarket La Esquina SAC', 'RUC', '20512345671'],
            ['Patricia Salas', 'DNI', '43405062'],
            ['Bodega San José', 'RUC', '20512345672'],
            ['Jorge Mendoza', 'DNI', '44505063'],
            ['Comercial Los Pinos EIRL', 'RUC', '20512345673'],
            ['Carmen Díaz', 'DNI', '45605064'],
            ['Restaurante El Sabor', 'RUC', '20512345674'],
            ['Miguel Castro', 'DNI', '46705065'],
        ];
        collect($clientesData)->each(fn ($c) => Cliente::firstOrCreate(
            ['empresa_id' => $empresa->id, 'numero_documento' => $c[2]],
            ['nombre' => $c[0], 'tipo_documento' => $c[1], 'activo' => true]
        ));
        $clientes = Cliente::all();

        // ================================================================
        // 6) PRODUCTOS (10 nuevos)
        // ================================================================
        $productosData = [
            ['Shampoo Head&Shoulders 400ml', 9.50, 14.90, 'Cuidado Personal'],
            ['Jabón Dove x3', 4.20, 6.90, 'Cuidado Personal'],
            ['Pan de molde Bimbo', 3.80, 5.50, 'Panadería'],
            ['Nuggets de pollo 1kg', 12.00, 17.90, 'Congelados'],
            ['Alimento para perro 2kg', 15.00, 22.50, 'Mascotas'],
            ['Foco LED 9W', 3.50, 6.00, 'Ferretería'],
            ['Cuaderno A4 100h', 2.80, 4.50, 'Papelería'],
            ['Manzana roja (kg)', 2.50, 4.00, 'Frutas y Verduras'],
            ['Pechuga de pollo (kg)', 8.50, 12.90, 'Carnes'],
            ['Audífonos USB', 11.00, 19.90, 'Electrónica'],
        ];
        $nuevosProductos = collect();
        foreach ($productosData as $i => [$nombre, $pc, $pv, $cat]) {
            $categoria = $todasCategorias->firstWhere('nombre', $cat) ?? $categorias->first();
            $nuevosProductos->push(Producto::firstOrCreate(
                ['empresa_id' => $empresa->id, 'codigo' => 'D' . $sfx . str_pad($i + 1, 2, '0', STR_PAD_LEFT)],
                [
                    'nombre' => $nombre,
                    'categoria_id' => $categoria->id,
                    'marca_id' => $todasMarcas->random()->id,
                    'precio_compra' => $pc,
                    'precio_venta' => $pv,
                    'stock' => 0, // se llena con las compras
                    'stock_minimo' => 10,
                    'activo' => true,
                ]
            ));
        }
        // pool de productos para compras/ventas (nuevos + existentes)
        $productos = Producto::where('activo', true)->get();

        // ================================================================
        // 7) COMPRAS (10) — reparten stock y generan movimientos ENTRADA
        //    Distribuidas en los últimos ~55 días.
        // ================================================================
        $stock = $productos->pluck('stock', 'id')->toArray(); // control en memoria
        $diasCompra = [55, 48, 41, 34, 27, 20, 14, 9, 5, 2];

        foreach ($diasCompra as $n => $dias) {
            $fecha = Carbon::today()->subDays($dias);
            $compra = new Compra([
                'numero' => 'C-D' . $sfx . '-' . str_pad($n + 1, 4, '0', STR_PAD_LEFT),
                'proveedor_id' => $proveedores->random()->id,
                'user_id' => $admin?->id,
                'fecha' => $fecha->toDateString(),
                'estado' => 'RECIBIDA',
                'observacion' => 'Compra demo para dashboard',
            ]);
            $compra->created_at = $fecha->copy()->addHours(rand(8, 17));
            $compra->updated_at = $compra->created_at;
            $compra->save();

            $subtotal = 0;
            foreach ($productos->random(rand(2, 4)) as $prod) {
                $cant = rand(10, 40);
                $sub = round($cant * (float) $prod->precio_compra, 2);
                $subtotal += $sub;

                CompraDetalle::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $prod->id,
                    'cantidad' => $cant,
                    'precio' => $prod->precio_compra,
                    'subtotal' => $sub,
                ]);

                $antes = $stock[$prod->id] ?? 0;
                $despues = $antes + $cant;
                $stock[$prod->id] = $despues;

                $mov = new MovimientoInventario([
                    'producto_id' => $prod->id,
                    'user_id' => $admin?->id,
                    'tipo' => 'ENTRADA',
                    'motivo' => 'COMPRA',
                    'cantidad' => $cant,
                    'stock_anterior' => $antes,
                    'stock_nuevo' => $despues,
                    'referencia_type' => Compra::class,
                    'referencia_id' => $compra->id,
                ]);
                $mov->created_at = $compra->created_at;
                $mov->updated_at = $compra->created_at;
                $mov->save();
            }

            $impuesto = round($subtotal * 0.18, 2);
            $compra->update([
                'subtotal' => $subtotal,
                'impuesto' => $impuesto,
                'total' => $subtotal + $impuesto,
            ]);
        }

        // ================================================================
        // 8) VENTAS — 1 a 3 por día en CADA uno de los últimos 14 días,
        //    más algunas en meses previos para el gráfico anual.
        //    Genera movimientos SALIDA y descuenta stock.
        // ================================================================
        $metodos = ['EFECTIVO', 'TARJETA', 'YAPE', 'TRANSFERENCIA'];
        $comprobantes = ['TICKET', 'BOLETA', 'FACTURA'];
        $vc = 0;

        $registrarVenta = function (Carbon $fecha) use (
            &$vc, &$stock, $sfx, $admin, $clientes, $productos, $metodos, $comprobantes
        ) {
            $venta = new Venta([
                'numero' => 'V-D' . $sfx . '-' . str_pad(++$vc, 4, '0', STR_PAD_LEFT),
                'cliente_id' => $clientes->random()->id,
                'user_id' => $admin?->id,
                'tipo_comprobante' => $comprobantes[array_rand($comprobantes)],
                'metodo_pago' => $metodos[array_rand($metodos)],
                'estado' => 'COMPLETADA',
            ]);
            $venta->created_at = $fecha->copy()->addHours(rand(9, 20))->addMinutes(rand(0, 59));
            $venta->updated_at = $venta->created_at;
            $venta->save();

            $subtotal = 0;
            foreach ($productos->random(rand(1, 4)) as $prod) {
                $disp = $stock[$prod->id] ?? 0;
                if ($disp <= 0) {
                    continue;
                }
                $cant = min(rand(1, 5), $disp);
                $sub = round($cant * (float) $prod->precio_venta, 2);
                $subtotal += $sub;

                VentaDetalle::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $prod->id,
                    'descripcion' => $prod->nombre,
                    'cantidad' => $cant,
                    'precio' => $prod->precio_venta,
                    'subtotal' => $sub,
                ]);

                $antes = $disp;
                $despues = $antes - $cant;
                $stock[$prod->id] = $despues;

                $mov = new MovimientoInventario([
                    'producto_id' => $prod->id,
                    'user_id' => $admin?->id,
                    'tipo' => 'SALIDA',
                    'motivo' => 'VENTA',
                    'cantidad' => $cant,
                    'stock_anterior' => $antes,
                    'stock_nuevo' => $despues,
                    'referencia_type' => Venta::class,
                    'referencia_id' => $venta->id,
                ]);
                $mov->created_at = $venta->created_at;
                $mov->updated_at = $venta->created_at;
                $mov->save();
            }

            // si por stock no se agregó ningún detalle, forzar uno simple
            if ($subtotal == 0) {
                $prod = $productos->random();
                $sub = round((float) $prod->precio_venta, 2);
                $subtotal = $sub;
                VentaDetalle::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $prod->id,
                    'descripcion' => $prod->nombre,
                    'cantidad' => 1,
                    'precio' => $prod->precio_venta,
                    'subtotal' => $sub,
                ]);
            }

            $impuesto = round($subtotal * 0.18, 2);
            $total = $subtotal + $impuesto;
            $extra = [];
            if ($venta->metodo_pago === 'EFECTIVO') {
                $recibido = ceil($total / 10) * 10;
                $extra = ['efectivo_recibido' => $recibido, 'vuelto' => $recibido - $total];
            }
            $venta->update(array_merge([
                'subtotal' => $subtotal,
                'impuesto' => $impuesto,
                'total' => $total,
            ], $extra));
        };

        // Últimos 14 días: al menos una venta por día (line chart siempre poblado)
        for ($d = 13; $d >= 0; $d--) {
            $fecha = Carbon::today()->subDays($d);
            foreach (range(1, rand(1, 3)) as $_) {
                $registrarVenta($fecha);
            }
        }

        // Meses previos (para el gráfico "Ventas por mes")
        foreach ([1, 2, 3, 4, 5] as $mesesAtras) {
            $base = Carbon::today()->subMonths($mesesAtras);
            foreach (range(1, rand(2, 4)) as $_) {
                $registrarVenta($base->copy()->subDays(rand(0, 20)));
            }
        }

        // Sincronizar el stock final calculado a los productos
        foreach ($stock as $prodId => $valor) {
            Producto::where('id', $prodId)->update(['stock' => max(0, $valor)]);
        }

        // ================================================================
        // 9) MOVIMIENTOS DE INVENTARIO — 10 AJUSTES manuales (Ajustes de Stock)
        //    en distintas fechas.
        // ================================================================
        $motivosAjuste = ['AJUSTE_MANUAL', 'MERMA', 'AJUSTE_MANUAL', 'MERMA', 'AJUSTE_MANUAL'];
        for ($a = 0; $a < 10; $a++) {
            $prod = $productos->random();
            $antes = $stock[$prod->id] ?? (int) $prod->stock;
            $delta = rand(0, 1) ? rand(1, 8) : -rand(1, 5);
            $tipo = $delta >= 0 ? 'ENTRADA' : 'SALIDA';
            if ($antes + $delta < 0) {
                $delta = -$antes;
            }
            $despues = $antes + $delta;
            $stock[$prod->id] = $despues;

            $fecha = Carbon::today()->subDays(rand(0, 25));
            $mov = new MovimientoInventario([
                'producto_id' => $prod->id,
                'user_id' => $admin?->id,
                'tipo' => 'AJUSTE',
                'motivo' => $motivosAjuste[array_rand($motivosAjuste)],
                'cantidad' => abs($delta),
                'stock_anterior' => $antes,
                'stock_nuevo' => $despues,
            ]);
            $mov->created_at = $fecha->copy()->addHours(rand(8, 18));
            $mov->updated_at = $mov->created_at;
            $mov->save();

            Producto::where('id', $prod->id)->update(['stock' => max(0, $despues)]);
        }

        Tenant::clear();

        $this->command->info('¡Listo! Datos demo agregados. Revisa el Dashboard.');
    }
}
