@extends('layouts.app')

@section('title', 'Productos')

@section('content')
    <div class="page-head">
        <h1>PRODUCTOS</h1>
        <div class="breadcrumb">
            <a href="{{ route('dashboard') }}">Inicio</a>
            <span class="sep">/</span> Inventario <span class="sep">/</span> Productos
        </div>
    </div>

    <div class="toolbar">
        <form method="GET" action="{{ route('productos.index') }}" style="display:flex;gap:10px;flex-wrap:wrap">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" value="{{ $q }}" placeholder="Buscar por nombre o código...">
            </div>
            <div class="search-box no-ico">
                <select name="categoria" onchange="this.form.submit()">
                    <option value="">Todas las categorías</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" {{ (string)$categoriaId === (string)$cat->id ? 'selected' : '' }}>
                            {{ $cat->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="search-box no-ico">
                <select name="estado" onchange="this.form.submit()">
                    <option value="">Todo el stock</option>
                    <option value="bajo" {{ $estado === 'bajo' ? 'selected' : '' }}>Solo stock bajo</option>
                </select>
            </div>
            <button class="btn btn-light btn-sm"><i class="fa-solid fa-filter"></i> Filtrar</button>
        </form>
        <div class="spacer"></div>
        <a href="{{ route('productos.export', request()->only('q', 'categoria', 'estado')) }}" class="btn btn-light btn-sm">
            <i class="fa-solid fa-file-excel" style="color:#1e7e45"></i> Exportar
        </a>
        <a href="{{ route('productos.create') }}" class="btn btn-success btn-sm">
            <i class="fa-solid fa-plus"></i> Nuevo producto
        </a>
    </div>

    <div class="panel">
        <div class="panel-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th></th><th>Código</th><th>Producto</th><th>Categoría</th>
                    <th>P. Venta</th><th>Stock</th><th>Estado</th><th style="width:110px">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($productos as $p)
                @php
                    $clase = $p->stock <= 0 ? 'out' : ($p->stock <= $p->stock_minimo ? 'low' : 'ok');
                @endphp
                <tr>
                    <td>
                        @if($p->imagen)
                            <img src="{{ asset('storage/'.$p->imagen) }}" class="thumb" alt="">
                        @else
                            <span class="thumb"><i class="fa-solid fa-box"></i></span>
                        @endif
                    </td>
                    <td><span class="badge-soft">{{ $p->codigo }}</span></td>
                    <td><strong>{{ $p->nombre }}</strong><br>
                        <span style="color:#9aa3ab;font-size:12px">{{ $p->marca->nombre ?? '' }}</span></td>
                    <td>{{ $p->categoria->nombre ?? '—' }}</td>
                    <td>S/ {{ number_format($p->precio_venta, 2) }}</td>
                    <td>
                        <span class="badge-stock {{ $clase }}">
                            {{ $p->stock }} {{ $p->unidad }}
                        </span>
                    </td>
                    <td>
                        <span class="pill {{ $p->activo ? 'ok' : 'warn' }}">
                            {{ $p->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('productos.edit', $p) }}" class="btn-icon edit" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form method="POST" action="{{ route('productos.destroy', $p) }}"
                                  onsubmit="return confirm('¿Eliminar el producto {{ $p->nombre }}?')">
                                @csrf @method('DELETE')
                                <button class="btn-icon del" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;color:#9aa3ab;padding:24px">No se encontraron productos.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        {{ $productos->links() }}
    </div>
@endsection
