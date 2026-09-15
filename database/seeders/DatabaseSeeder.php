<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ===== La empresa del sistema =====
        $empresa = Empresa::create([
            'nombre' => 'Mi Negocio Demo',
            'ruc' => '20123456789',
            'direccion' => 'Av. Principal 123, Lima',
            'telefono' => '01-4567890',
            'email' => 'ventas@minegocio.test',
            'moneda' => 'S/',
            'igv' => 18.00,
        ]);

        // A partir de aquí todo lo creado pertenece a esta empresa.
        Tenant::set($empresa->id);

        // ===== Usuarios =====
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@saas.test',
            'password' => 'password',
            'rol' => 'admin',
            'activo' => true,
        ]);

        User::create([
            'name' => 'Vendedor Demo',
            'email' => 'vendedor@saas.test',
            'password' => 'password',
            'rol' => 'vendedor',
            'activo' => true,
        ]);

        // ===== Clientes =====
        collect([
            ['Taller Mecánico El Motor SAC',   'RUC', '20456789123'],
            ['Transportes Andinos EIRL',        'RUC', '20567891234'],
            ['Servicentro La Curva SAC',        'RUC', '20678912345'],
            ['Juan Pérez',                      'DNI', '45678912'],
            ['Carlos Ramírez',                  'DNI', '73829104'],
        ])->each(fn ($c) => Cliente::create([
            'nombre' => $c[0], 'tipo_documento' => $c[1], 'numero_documento' => $c[2],
        ]));

        // ===== Catálogo del rubro e historial =====
        // El catálogo (categorías, marcas, proveedores y productos) y el
        // historial de compras y ventas los carga el seeder del rubro, para
        // que una instalación nueva arranque con datos del negocio real.
        $this->call(CatalogoLubricantesPernosSeeder::class);
    }
}
