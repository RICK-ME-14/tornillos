<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use Illuminate\Http\Request;

class MarcaController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');

        $marcas = Marca::withCount('productos')
            ->when($q, fn ($query) => $query->where('nombre', 'like', "%{$q}%"))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('marcas.index', compact('marcas', 'q'));
    }

    public function create()
    {
        return view('marcas.create');
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        Marca::create($data);

        return redirect()->route('marcas.index')
            ->with('success', 'Marca creada correctamente.');
    }

    public function edit(Marca $marca)
    {
        return view('marcas.edit', compact('marca'));
    }

    public function update(Request $request, Marca $marca)
    {
        $data = $this->validar($request);
        $marca->update($data);

        return redirect()->route('marcas.index')
            ->with('success', 'Marca actualizada correctamente.');
    }

    public function destroy(Marca $marca)
    {
        if ($marca->productos()->exists()) {
            return back()->with('error', 'No se puede eliminar: la marca tiene productos asociados.');
        }

        $marca->delete();

        return back()->with('success', 'Marca eliminada.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
        ]) + ['activo' => $request->boolean('activo')];
    }
}
