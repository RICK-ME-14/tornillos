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
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de un negocio de LUBRICANTES Y PERNOS.
 *
 * Reemplaza el catálogo de bodega que traía la demo por uno del rubro:
 * aceites por grado, filtros, grasas, refrigerantes, pernos por medida,
 * tuercas, arandelas y abrazaderas, con sus unidades reales (galón, litro,
 * kilo, ciento, metro, par, juego) y su afectación al IGV.
 *
 * Después reconstruye un historial coherente de compras y ventas para que
 * el dashboard, el kardex y los reportes tengan datos con sentido. El stock
 * se lleva en memoria: cada movimiento parte del stock real anterior y
 * ninguno deja el inventario en negativo.
 *
 * ATENCIÓN: borra productos, compras, ventas y movimientos existentes.
 *
 * Uso:  php artisan db:seed --class=CatalogoLubricantesPernosSeeder
 */
class CatalogoLubricantesPernosSeeder extends Seeder
{
    /** Precios de venta SIN IGV: el sistema lo agrega al cobrar. */
    public function run(): void
    {
        $empresa = Empresa::orderBy('id')->first();

        if (! $empresa) {
            $this->command->error('No existe ninguna empresa. Ejecuta antes: php artisan migrate --seed');
            return;
        }

        Tenant::set($empresa->id);
        $admin = User::where('rol', 'admin')->orderBy('id')->first() ?? User::orderBy('id')->first();

        $this->limpiar();
        $marcas = $this->marcas();
        $categorias = $this->categorias();
        $proveedores = $this->proveedores();
        $productos = $this->productos($categorias, $marcas);

        $this->command->info(sprintf(
            'Catálogo cargado: %d categorías · %d marcas · %d proveedores · %d productos',
            count($categorias), count($marcas), count($proveedores), $productos->count()
        ));

        $this->historial($productos, $proveedores, $admin);
    }

    // =================================================================
    // Limpieza
    // =================================================================
    private function limpiar(): void
    {
        DB::transaction(function () {
            // Hijos antes que padres, por las claves foráneas.
            VentaDetalle::query()->delete();
            CompraDetalle::query()->delete();
            MovimientoInventario::query()->delete();
            Venta::query()->delete();
            Compra::query()->delete();
            Producto::query()->delete();
            Categoria::query()->delete();
            Marca::query()->delete();
            Proveedor::query()->delete();
        });

        $this->command->warn('Catálogo e historial anteriores eliminados.');
    }

    // =================================================================
    // Catálogo
    // =================================================================
    private function categorias(): array
    {
        $nombres = [
            'Aceites de motor',
            'Aceites de transmisión',
            'Filtros',
            'Grasas y aditivos',
            'Refrigerantes y líquidos',
            'Pernos y tornillos',
            'Tuercas y arandelas',
            'Abrazaderas y sujetadores',
            'Herramientas y accesorios',
        ];

        $out = [];
        foreach ($nombres as $n) {
            $out[$n] = Categoria::create(['nombre' => $n, 'activo' => true])->id;
        }

        return $out;
    }

    private function marcas(): array
    {
        $nombres = [
            'Mobil', 'Castrol', 'Shell', 'Vistony', 'Repsol', 'Total', 'Valvoline',
            'Bosch', 'Mann-Filter', 'Fram', 'Bardahl', 'Stanley', 'Genérico',
        ];

        $out = [];
        foreach ($nombres as $n) {
            $out[$n] = Marca::create(['nombre' => $n, 'activo' => true])->id;
        }

        return $out;
    }

    private function proveedores(): \Illuminate\Support\Collection
    {
        $filas = [
            ['Lubricantes del Perú SAC',        '20512345671', 'Av. Argentina 2450, Lima',        '01-3364821'],
            ['Distribuidora Vistony Lima EIRL', '20512345672', 'Av. Nicolás Ayllón 1820, Ate',    '01-3487200'],
            ['Importaciones Automotriz SAC',    '20512345673', 'Jr. Paruro 1150, Lima',           '01-4271590'],
            ['Repuestos y Filtros del Norte SA','20512345674', 'Av. Colonial 3200, Callao',       '01-4529830'],
            ['Ferretería Industrial Andina SAC','20512345675', 'Av. Aviación 4120, San Borja',    '01-2258740'],
            ['Tornillos y Fijaciones SAC',      '20512345676', 'Jr. Cusco 890, Cercado de Lima',  '01-4265510'],
            ['Comercial Lubrimax EIRL',         '20512345677', 'Av. Universitaria 5400, Los Olivos', '01-5238960'],
            ['Aceites y Grasas Pacífico SA',    '20512345678', 'Av. Elmer Faucett 1730, Callao',  '01-5741200'],
        ];

        $out = collect();
        foreach ($filas as [$nombre, $ruc, $dir, $tel]) {
            $out->push(Proveedor::create([
                'nombre' => $nombre, 'ruc' => $ruc, 'direccion' => $dir,
                'telefono' => $tel, 'activo' => true,
            ]));
        }

        return $out;
    }

    /**
     * [categoría, marca, código, nombre, unidad, precio compra, precio venta, stock mínimo]
     * La unidad se declara con la clave del catálogo de Producto::UNIDADES,
     * que el sistema traduce al código de SUNAT al emitir.
     */
    private function productos(array $cat, array $mar): \Illuminate\Support\Collection
    {
        $filas = [
            // ---- Aceites de motor ----
            ['Aceites de motor', 'Mobil',     'AC-0001', 'Aceite Mobil Super 20W-50 x 1 gal',        'GAL',  62.00,  79.00,  8],
            ['Aceites de motor', 'Castrol',   'AC-0002', 'Aceite Castrol GTX 20W-50 x 1 gal',        'GAL',  68.00,  86.00,  8],
            ['Aceites de motor', 'Shell',     'AC-0003', 'Aceite Shell Helix HX5 15W-40 x 1 gal',    'GAL',  58.00,  74.00,  8],
            ['Aceites de motor', 'Vistony',   'AC-0004', 'Aceite Vistony Turbo 25W-60 x 1 gal',      'GAL',  42.00,  55.00, 10],
            ['Aceites de motor', 'Repsol',    'AC-0005', 'Aceite Repsol Elite 10W-40 x 4 L',         'UND',  78.00,  98.00,  6],
            ['Aceites de motor', 'Mobil',     'AC-0006', 'Aceite Mobil Delvac 15W-40 balde 5 gal',   'UND', 280.00, 350.00,  3],
            ['Aceites de motor', 'Total',     'AC-0007', 'Aceite Total Rubia TIR 15W-40 x 1 gal',    'GAL',  55.00,  70.00,  8],
            ['Aceites de motor', 'Valvoline', 'AC-0008', 'Aceite Valvoline 20W-50 x 1 L',            'LT',   18.00,  24.00, 12],

            // ---- Aceites de transmisión ----
            ['Aceites de transmisión', 'Vistony', 'AT-0001', 'Aceite de caja 80W-90 x 1 gal',        'GAL',  48.00,  62.00,  6],
            ['Aceites de transmisión', 'Shell',   'AT-0002', 'Aceite hidráulico ATF Dexron III x 1 gal', 'GAL', 45.00, 58.00, 6],
            ['Aceites de transmisión', 'Castrol', 'AT-0003', 'Aceite de corona 85W-140 x 1 gal',     'GAL',  52.00,  68.00,  5],

            // ---- Filtros ----
            ['Filtros', 'Fram',        'FI-0001', 'Filtro de aceite Fram PH4967',            'UND', 12.00, 19.00, 12],
            ['Filtros', 'Bosch',       'FI-0002', 'Filtro de aceite Bosch 0986452041',       'UND', 14.00, 22.00, 12],
            ['Filtros', 'Mann-Filter', 'FI-0003', 'Filtro de aire Mann C2433',               'UND', 26.00, 39.00,  8],
            ['Filtros', 'Bosch',       'FI-0004', 'Filtro de combustible Bosch F026402085',  'UND', 22.00, 34.00,  8],
            ['Filtros', 'Mann-Filter', 'FI-0005', 'Filtro de cabina Mann CU2545',            'UND', 20.00, 32.00,  6],

            // ---- Grasas y aditivos ----
            ['Grasas y aditivos', 'Vistony',  'GR-0001', 'Grasa multiuso x 1 kg',                  'KG',  14.00, 21.00, 10],
            ['Grasas y aditivos', 'Vistony',  'GR-0002', 'Grasa de litio EP-2 balde x 5 kg',       'UND', 58.00, 78.00,  4],
            ['Grasas y aditivos', 'Bardahl',  'GR-0003', 'Aditivo limpia inyectores x 500 ml',     'UND', 22.00, 33.00,  8],
            ['Grasas y aditivos', 'Genérico', 'GR-0004', 'Sellador de empaquetaduras gris x 85 g', 'UND', 12.00, 19.00, 10],

            // ---- Refrigerantes y líquidos ----
            ['Refrigerantes y líquidos', 'Vistony',  'RF-0001', 'Refrigerante verde concentrado x 1 gal', 'GAL', 32.00, 45.00, 6],
            ['Refrigerantes y líquidos', 'Bosch',    'RF-0002', 'Líquido de frenos DOT-3 x 500 ml',       'UND', 11.00, 17.00, 12],
            ['Refrigerantes y líquidos', 'Genérico', 'RF-0003', 'Agua destilada x 1 L',                   'LT',   3.00,  5.00, 20],

            // ---- Pernos y tornillos ----
            ['Pernos y tornillos', 'Genérico', 'PE-0001', 'Perno hexagonal 1/4 x 1 G5',      'CIEN',  32.00,  48.00, 5],
            ['Pernos y tornillos', 'Genérico', 'PE-0002', 'Perno hexagonal 3/8 x 2 G5',      'CIEN',  68.00,  95.00, 5],
            ['Pernos y tornillos', 'Genérico', 'PE-0003', 'Perno hexagonal 1/2 x 2 G8',      'CIEN', 145.00, 195.00, 3],
            ['Pernos y tornillos', 'Genérico', 'PE-0004', 'Perno de rueda M12 x 1.5',        'UND',    3.50,   6.00, 40],
            ['Pernos y tornillos', 'Genérico', 'PE-0005', 'Tornillo autorroscante 8 x 1',    'CIEN',  14.00,  22.00, 6],
            ['Pernos y tornillos', 'Genérico', 'PE-0006', 'Perno Allen M8 x 30',             'UND',    1.20,   2.20, 60],

            // ---- Tuercas y arandelas ----
            ['Tuercas y arandelas', 'Genérico', 'TU-0001', 'Tuerca hexagonal 3/8',       'CIEN', 18.00, 28.00, 6],
            ['Tuercas y arandelas', 'Genérico', 'TU-0002', 'Tuerca de seguridad 1/2',    'CIEN', 42.00, 62.00, 4],
            ['Tuercas y arandelas', 'Genérico', 'TU-0003', 'Arandela plana 3/8',         'CIEN',  9.00, 15.00, 8],
            ['Tuercas y arandelas', 'Genérico', 'TU-0004', 'Arandela de presión 1/2',    'CIEN', 12.00, 19.00, 8],

            // ---- Abrazaderas y sujetadores ----
            ['Abrazaderas y sujetadores', 'Genérico', 'AB-0001', 'Abrazadera metálica 1/2"',  'UND',  1.80,  3.00, 50],
            ['Abrazaderas y sujetadores', 'Genérico', 'AB-0002', 'Abrazadera metálica 2"',    'UND',  3.20,  5.50, 40],
            ['Abrazaderas y sujetadores', 'Genérico', 'AB-0003', 'Precinto plástico 30 cm',   'CIEN',  8.00, 14.00, 6],

            // ---- Herramientas y accesorios ----
            ['Herramientas y accesorios', 'Stanley',  'HE-0001', 'Juego de llaves hexagonales 9 pzas', 'JGO', 22.00, 38.00, 4],
            ['Herramientas y accesorios', 'Genérico', 'HE-0002', 'Embudo plástico 1 L',                'UND',  4.00,  8.00, 15],
            ['Herramientas y accesorios', 'Genérico', 'HE-0003', 'Franela industrial',                 'MT',   5.00,  9.00, 20],
            ['Herramientas y accesorios', 'Genérico', 'HE-0004', 'Guantes de nitrilo',                 'PAR',  4.50,  8.00, 25],
        ];

        $out = collect();
        foreach ($filas as [$c, $m, $cod, $nom, $uni, $pc, $pv, $min]) {
            $out->push(Producto::create([
                'codigo' => $cod,
                'nombre' => $nom,
                'categoria_id' => $cat[$c],
                'marca_id' => $mar[$m],
                'unidad' => $uni,
                'tipo_afectacion_igv' => '10',   // todo el rubro está gravado
                'precio_compra' => $pc,
                'precio_venta' => $pv,
                'stock' => 0,                    // lo llenan las compras
                'stock_minimo' => $min,
                'activo' => true,
            ]));
        }

        return $out;
    }

    // =================================================================
    // Historial: compras que abastecen y ventas que consumen
    // =================================================================
    private function historial($productos, $proveedores, ?User $admin): void
    {
        $clientes = Cliente::where('activo', true)->get();
        $stock = [];                              // control en memoria
        foreach ($productos as $p) {
            $stock[$p->id] = 0;
        }

        $nc = 0;
        $nv = 0;

        // ---- Abastecimiento inicial: cada producto entra al inventario ----
        $inicio = Carbon::today()->startOfMonth()->subMonths(8)->addDays(3);
        foreach ($productos->chunk(8) as $i => $lote) {
            $fecha = $inicio->copy()->addDays($i * 2);
            $this->compra($lote, $fecha, $proveedores, $admin, $stock, $nc, function ($p) {
                // Lo barato entra por cientos; lo caro, de a pocos.
                return $p->precio_compra > 100 ? rand(6, 12)
                     : ($p->precio_compra > 30 ? rand(18, 35) : rand(40, 90));
            });
        }

        // ---- Reposiciones mensuales ----
        for ($m = 7; $m >= 0; $m--) {
            $fecha = Carbon::today()->startOfMonth()->subMonths($m)->addDays(rand(4, 20));
            if ($fecha->isFuture()) {
                continue;
            }
            $this->compra($productos->random(rand(5, 9)), $fecha, $proveedores, $admin, $stock, $nc,
                fn ($p) => $p->precio_compra > 100 ? rand(3, 7) : rand(15, 45));
        }

        // ---- Ventas del año, con más movimiento en los meses recientes ----
        for ($m = 7; $m >= 1; $m--) {
            $mes = Carbon::today()->startOfMonth()->subMonths($m);
            $porMes = (int) round(14 + (7 - $m) * 4);      // el negocio crece
            for ($i = 0; $i < $porMes; $i++) {
                $dia = $mes->copy()->addDays(rand(0, $mes->daysInMonth - 1));
                if ($dia->isFuture()) {
                    continue;
                }
                $this->venta($dia, $productos, $clientes, $admin, $stock, $nv);
            }
        }

        // ---- Últimos 14 días: siempre con datos para el gráfico corto ----
        for ($d = 13; $d >= 0; $d--) {
            $dia = Carbon::today()->subDays($d);
            foreach (range(1, rand(2, 5)) as $ignorado) {
                $this->venta($dia, $productos, $clientes, $admin, $stock, $nv);
            }
        }

        // ---- Reposición final: el negocio no abre con los estantes vacíos ----
        // Se deja adrede un puñado de productos por debajo del mínimo para que
        // el aviso de stock bajo del dashboard tenga de qué hablar.
        // Se eligen entre los que de verdad quedaron escasos, no al azar:
        // de otro modo se "reservan" productos que ya estaban surtidos.
        $dejarBajos = $productos
            ->sortBy(fn ($p) => ($stock[$p->id] ?? 0) / max(1, $p->stock_minimo))
            ->take(4)->pluck('id')->all();
        $reponer = $productos->filter(function ($p) use ($stock, $dejarBajos) {
            return ! in_array($p->id, $dejarBajos, true)
                && $stock[$p->id] < $p->stock_minimo * 3;
        });

        if ($reponer->isNotEmpty()) {
            $fecha = Carbon::today()->subDays(6);
            $actual = $stock;
            $this->compra($reponer, $fecha, $proveedores, $admin, $stock, $nc,
                fn ($p) => max(1, (int) ($p->stock_minimo * rand(4, 7)) - ($actual[$p->id] ?? 0)));
        }

        // ---- El stock declarado queda igual al que arroja el kardex ----
        foreach ($stock as $id => $cant) {
            Producto::whereKey($id)->update(['stock' => max(0, $cant)]);
        }

        $this->command->info("Historial reconstruido: {$nc} compras · {$nv} ventas.");
    }

    private function compra($lote, Carbon $fecha, $proveedores, ?User $admin, array &$stock, int &$nc, callable $cantidad): void
    {
        $compra = new Compra([
            'numero' => 'C-' . str_pad((string) (++$nc), 6, '0', STR_PAD_LEFT),
            'proveedor_id' => $proveedores->random()->id,
            'user_id' => $admin?->id,
            'fecha' => $fecha->toDateString(),
            'estado' => 'RECIBIDA',
        ]);
        $compra->created_at = $fecha->copy()->addHours(rand(8, 16));
        $compra->updated_at = $compra->created_at;
        $compra->save();

        $subtotal = 0.0;
        foreach ($lote as $prod) {
            $cant = $cantidad($prod);
            $sub = round($cant * (float) $prod->precio_compra, 2);
            $subtotal += $sub;

            CompraDetalle::create([
                'compra_id' => $compra->id,
                'producto_id' => $prod->id,
                'cantidad' => $cant,
                'precio' => $prod->precio_compra,
                'subtotal' => $sub,
            ]);

            $antes = $stock[$prod->id];
            $stock[$prod->id] = $antes + $cant;

            $mov = new MovimientoInventario([
                'producto_id' => $prod->id,
                'user_id' => $admin?->id,
                'tipo' => 'ENTRADA',
                'motivo' => 'COMPRA',
                'cantidad' => $cant,
                'stock_anterior' => $antes,
                'stock_nuevo' => $stock[$prod->id],
                'referencia_type' => Compra::class,
                'referencia_id' => $compra->id,
            ]);
            $mov->created_at = $compra->created_at;
            $mov->updated_at = $compra->created_at;
            $mov->save();
        }

        $impuesto = round($subtotal * 0.18, 2);
        $compra->update([
            'subtotal' => round($subtotal, 2),
            'impuesto' => $impuesto,
            'total' => round($subtotal + $impuesto, 2),
        ]);
    }

    private function venta(Carbon $dia, $productos, $clientes, ?User $admin, array &$stock, int &$nv): void
    {
        // Solo se vende lo que hay: nunca se deja el inventario en negativo.
        $disponibles = $productos->filter(fn ($p) => ($stock[$p->id] ?? 0) > 0);
        if ($disponibles->isEmpty()) {
            return;
        }

        $lineas = $disponibles->random(min(rand(1, 4), $disponibles->count()));
        $momento = $dia->copy()->addHours(rand(8, 19))->addMinutes(rand(0, 59));

        $venta = new Venta([
            'numero' => 'V-' . str_pad((string) (++$nv), 6, '0', STR_PAD_LEFT),
            'cliente_id' => $clientes->isNotEmpty() ? $clientes->random()->id : null,
            'user_id' => $admin?->id,
            'tipo_comprobante' => ['TICKET', 'BOLETA', 'BOLETA', 'FACTURA'][rand(0, 3)],
            'metodo_pago' => ['EFECTIVO', 'EFECTIVO', 'TARJETA', 'YAPE', 'TRANSFERENCIA'][rand(0, 4)],
            'estado' => 'COMPLETADA',
        ]);
        $venta->created_at = $momento;
        $venta->updated_at = $momento;
        $venta->save();

        $subtotal = 0.0;
        foreach ($lineas as $prod) {
            $tope = min((int) $stock[$prod->id], $prod->precio_venta > 100 ? 2 : 6);
            $cant = max(1, rand(1, max(1, $tope)));
            $sub = round($cant * (float) $prod->precio_venta, 2);
            $subtotal += $sub;

            VentaDetalle::create([
                'venta_id' => $venta->id,
                'producto_id' => $prod->id,
                'descripcion' => $prod->nombre,
                'cantidad' => $cant,
                'precio' => $prod->precio_venta,
                'tipo_afectacion_igv' => $prod->tipo_afectacion_igv ?: '10',
                'unidad_sunat' => $prod->unidadSunat(),
                'subtotal' => $sub,
            ]);

            $antes = $stock[$prod->id];
            $stock[$prod->id] = $antes - $cant;

            $mov = new MovimientoInventario([
                'producto_id' => $prod->id,
                'user_id' => $admin?->id,
                'tipo' => 'SALIDA',
                'motivo' => 'VENTA',
                'cantidad' => $cant,
                'stock_anterior' => $antes,
                'stock_nuevo' => $stock[$prod->id],
                'referencia_type' => Venta::class,
                'referencia_id' => $venta->id,
            ]);
            $mov->created_at = $momento;
            $mov->updated_at = $momento;
            $mov->save();
        }

        $subtotal = round($subtotal, 2);
        $impuesto = round($subtotal * 0.18, 2);   // todo el catálogo es gravado

        $venta->forceFill([
            'subtotal' => $subtotal,
            'descuento' => 0,
            'impuesto' => $impuesto,
            'total' => round($subtotal + $impuesto, 2),
        ])->save();
    }
}
