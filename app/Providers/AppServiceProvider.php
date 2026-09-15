<?php

namespace App\Providers;

use App\Models\Empresa;
use App\Support\Avisos;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Evita errores de longitud de índice en MySQL < 5.7.7
        Schema::defaultStringLength(191);

        // Paginación con markup Bootstrap (compatible con el CSS del proyecto)
        Paginator::useBootstrapFour();

        // Comparte la configuración de la empresa con todas las vistas.
        // Protegido con try/catch para no romper antes de migrar la BD.
        View::composer('*', function ($view) {
            try {
                $view->with('empresa', Empresa::actual());
            } catch (\Throwable $e) {
                $view->with('empresa', null);
            }
        });

        // Avisos de la campana: solo en el layout de la aplicacion y solo con
        // sesion iniciada, para no consultar la base en la pantalla de acceso.
        View::composer('layouts.app', function ($view) {
            try {
                $view->with('avisos', auth()->check()
                    ? Avisos::paraLaBarra()
                    : ['items' => [], 'total' => 0]);
            } catch (\Throwable $e) {
                $view->with('avisos', ['items' => [], 'total' => 0]);
            }
        });
    }
}
