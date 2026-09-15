<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmpresaController extends Controller
{
    public function edit()
    {
        return view('configuracion.empresa', ['empresaCfg' => Empresa::actual()]);
    }

    public function update(Request $request)
    {
        $empresa = Empresa::actual();

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'ruc' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'moneda' => ['required', 'string', 'max:5'],
            'igv' => ['required', 'numeric', 'min:0', 'max:100'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ], [
            'nombre.required' => 'El nombre de la empresa es obligatorio.',
            'moneda.required' => 'Indica el símbolo de la moneda.',
            'igv.required' => 'Indica el porcentaje de IGV.',
            'logo.image' => 'El logo debe ser una imagen.',
            'logo.max' => 'El logo no debe superar 2 MB.',
        ]);

        if ($request->hasFile('logo')) {
            if ($empresa->logo) {
                Storage::disk('public')->delete($empresa->logo);
            }
            $data['logo'] = $request->file('logo')->store('empresa', 'public');
        }

        $empresa->update($data);

        return back()->with('success', 'Configuración de la empresa actualizada.');
    }
}
