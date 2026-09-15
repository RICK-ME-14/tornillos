<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Controlador genérico para mostrar los módulos aún en construcción.
 * Cada módulo tendrá su propio controlador con CRUD real en las siguientes iteraciones.
 */
class ModuloController extends Controller
{
    public function show(Request $request, string $titulo, string $descripcion = '')
    {
        return view('modulos.placeholder', [
            'titulo' => $titulo,
            'descripcion' => $descripcion,
        ]);
    }
}
