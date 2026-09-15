<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Marca;
use App\Models\Producto;
use App\Support\ExportsCsv;
use Illuminate\Http\Request;
use App\Support\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    use ExportsCsv;

    public function index(Request $request)
    {
        $q = $request->get('q');
        $categoriaId = $request->get('categoria');
        $estado = $request->get('estado'); // '', 'bajo'

        $productos = Producto::with(['categoria', 'marca'])
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%");
            }))
            ->when($categoriaId, fn ($query) => $query->where('categoria_id', $categoriaId))
            ->when($estado === 'bajo', fn ($query) => $query->stockBajo())
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        $categorias = Categoria::orderBy('nombre')->get();

        return view('productos.index', compact('productos', 'categorias', 'q', 'categoriaId', 'estado'));
    }

    public function export(Request $request)
    {
        $q = $request->get('q');
        $categoriaId = $request->get('categoria');
        $estado = $request->get('estado');

        $filas = Producto::with(['categoria', 'marca'])
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', "%{$q}%")->orWhere('codigo', 'like', "%{$q}%");
            }))
            ->when($categoriaId, fn ($query) => $query->where('categoria_id', $categoriaId))
            ->when($estado === 'bajo', fn ($query) => $query->stockBajo())
            ->orderBy('nombre')->get()
            ->map(fn ($p) => [
                $p->codigo, $p->nombre,
                $p->categoria->nombre ?? '', $p->marca->nombre ?? '', $p->unidad,
                number_format($p->precio_compra, 2, '.', ''),
                number_format($p->precio_venta, 2, '.', ''),
                $p->stock, $p->stock_minimo,
                $p->activo ? 'Activo' : 'Inactivo',
            ]);

        return $this->descargarCsv('productos',
            ['Código', 'Nombre', 'Categoría', 'Marca', 'Unidad', 'Precio compra', 'Precio venta', 'Stock', 'Stock mínimo', 'Estado'],
            $filas);
    }

    public function create()
    {
        return view('productos.create', [
            'producto' => new Producto([
                'activo' => true, 'stock_minimo' => 5, 'unidad' => 'UND',
                'tipo_afectacion_igv' => '10',
            ]),
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(),
            'marcas' => Marca::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data['imagen'] = $this->guardarImagen($request);

        Producto::create($data);

        return redirect()->route('productos.index')
            ->with('success', 'Producto registrado correctamente.');
    }

    public function edit(Producto $producto)
    {
        return view('productos.edit', [
            'producto' => $producto,
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(),
            'marcas' => Marca::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Producto $producto)
    {
        $data = $this->validar($request, $producto->id);

        if ($request->hasFile('imagen')) {
            if ($producto->imagen) {
                Storage::disk('public')->delete($producto->imagen);
            }
            $data['imagen'] = $this->guardarImagen($request);
        }

        $producto->update($data);

        return redirect()->route('productos.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto)
    {
        // Sin estas comprobaciones la clave foranea salta y el usuario recibe
        // una pantalla de error en vez de un motivo. El resto de modulos ya
        // avisaba; productos era el unico que faltaba.
        if ($producto->ventaDetalles()->exists()) {
            return back()->with('error', 'No se puede eliminar: el producto tiene ventas registradas. Desactívalo para que deje de aparecer en el POS.');
        }

        if ($producto->compraDetalles()->exists()) {
            return back()->with('error', 'No se puede eliminar: el producto tiene compras registradas. Desactívalo para que deje de aparecer en el POS.');
        }

        if ($producto->movimientos()->exists()) {
            return back()->with('error', 'No se puede eliminar: el producto tiene movimientos en el kardex. Desactívalo para conservar su historial.');
        }

        if ($producto->imagen) {
            Storage::disk('public')->delete($producto->imagen);
        }

        $producto->delete();

        return back()->with('success', 'Producto eliminado.');
    }

    private function validar(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'codigo' => [
                'required', 'string', 'max:50',
                // Único por empresa (tenant), no de forma global.
                Rule::unique('productos', 'codigo')
                    ->where(fn ($q) => $q->where('empresa_id', Tenant::id()))
                    ->ignore($id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'marca_id' => ['nullable', 'exists:marcas,id'],
            'unidad' => ['required', Rule::in(array_keys(Producto::UNIDADES))],
            'tipo_afectacion_igv' => ['required', Rule::in(array_keys(Producto::AFECTACIONES))],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'stock_minimo' => ['required', 'integer', 'min:0'],
            'imagen' => ['nullable', 'image', 'max:2048'],
        ], [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'Ya existe un producto con ese código.',
            'nombre.required' => 'El nombre es obligatorio.',
            'precio_venta.required' => 'El precio de venta es obligatorio.',
            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.max' => 'La imagen no debe superar 2 MB.',
        ]);

        $data['activo'] = $request->boolean('activo');
        unset($data['imagen']); // se maneja aparte

        return $data;
    }

    private function guardarImagen(Request $request): ?string
    {
        if ($request->hasFile('imagen')) {
            return $request->file('imagen')->store('productos', 'public');
        }
        return null;
    }
}
